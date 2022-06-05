<?php
/**
 * Class WordPress\Plugin_Check\CLI\Plugin_Check_Command
 *
 * @package plugin-check
 */

namespace WordPress\Plugin_Check\CLI;

use WordPress\Plugin_Check\Plugin_Context;
use WordPress\Plugin_Check\Checker\Checks;
use WP_CLI;
use WP_CLI_Command;
use Exception;

/**
 * CLI command class for running plugin checks.
 *
 * @since 1.0.0
 */
class Plugin_Check_Command extends WP_CLI_Command {

	/**
	 * Plugin context.
	 *
	 * @since 1.0.0
	 * @var Plugin_Context
	 */
	protected $context;

	/**
	 * Command group name that this command is registered under.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $command_group_name;

	/**
	 * Internal Checks instance to operate with.
	 *
	 * @since 1.0.0
	 * @var Checks
	 */
	protected $checks;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Plugin_Context $context            Plugin context.
	 * @param string         $command_group_name Optional. Command group name that this command is registered under.
	 *                                           Default 'plugin-check'.
	 * @param bool           $skip_prepare       Optional. Whether to skip adding command preparation hooks. Default
	 *                                           false.
	 */
	public function __construct(
		Plugin_Context $context,
		$command_group_name = 'plugin-check',
		$skip_prepare = false
	) {
		$this->context            = $context;
		$this->command_group_name = $command_group_name;

		// A bit of a hack, but when the check commands are invoked, some code needs to run early.
		if ( ! $skip_prepare ) {
			$this->prepare_for_commands();
		}
	}

	/**
	 * Checks a single plugin, running all available checks on it by default.
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>]
	 * : The plugin to check.
	 *
	 * [--check=<check>]
	 * : Only runs a single specific check instead of all checks.
	 *
	 * [--format=<format>]
	 * : Format to display results. Either 'table', 'csv', 'json', 'yaml', 'count', or 'urls'.
	 * ---
	 * default: 'table'
	 * ---
	 *
	 * [--ignore-warnings]
	 * : Limit displayed results to exclude warnings.
	 *
	 * [--ignore-errors]
	 * : Limit displayed results to exclude errors.
	 *
	 * [--fields=<fields>]
	 * : Limit displayed results to a subset of available fields.
	 *
	 * [--field=<field>]
	 * : Limit displayed results to a single field.
	 *
	 * ## EXAMPLES
	 *
	 *     wp plugin-check check-plugin hello
	 *     wp plugin-check check-plugin --check=escaping
	 *     wp plugin-check check-plugin --format=json
	 *
	 * @subcommand check-plugin
	 *
	 * @since 1.0.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function check_plugin( array $args, array $assoc_args ) {
		try {
			$plugin_basename = $this->get_plugin_from_args( $args );
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		$checks = $this->get_checks_instance( $plugin_basename );

		if ( ! empty( $assoc_args['check'] ) ) {
			$method      = 'run_single_check';
			$method_args = array( $assoc_args['check'] );
		} else {
			$method      = 'run_all_checks';
			$method_args = array();
		}

		// Run the actual checks.
		try {
			$result = call_user_func_array(
				array( $checks, $method ),
				$method_args
			);
		} catch ( Exception $e ) {
			WP_CLI::error(
				sprintf(
					'Checking plugin %1$s failed with critical error: %2$s',
					$plugin_basename,
					$e->getMessage()
				)
			);
		}

		// Get errors and warnings from the results.
		$errors = array();
		if ( empty( $assoc_args['ignore_errors'] ) ) {
			$errors = $result->get_errors();
		}
		$warnings = array();
		if ( empty( $assoc_args['ignore_warnings'] ) ) {
			$warnings = $result->get_warnings();
		}

		// Get formatter.
		$formatter = $this->get_formatter_from_assoc_args( $assoc_args );

		// Print the formatted results.
		// Go over all files with errors first and print them, combined with any warnings in the same file.
		foreach ( $errors as $file_name => $file_errors ) {
			$file_warnings = array();
			if ( isset( $warnings[ $file_name ] ) ) {
				$file_warnings = $warnings[ $file_name ];
				unset( $warnings[ $file_name ] );
			}
			$file_results = $this->flatten_file_results( $file_errors, $file_warnings );
			$this->display_file_results( $formatter, $file_name, $file_results );
		}

		// If there are any files left with only warnings, print those next.
		foreach ( $warnings as $file_name => $file_warnings ) {
			$file_results = $this->flatten_file_results( array(), $file_warnings );
			$this->display_file_results( $formatter, $file_name, $file_results );
		}
	}

	/**
	 * Gets the plugin base name (directory and main file) from the given positional arguments.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Positional arguments.
	 * @return string Plugin basename.
	 *
	 * @throws Exception Thrown when argument is missing or invalid (plugin not installed).
	 */
	protected function get_plugin_from_args( array $args ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		if ( empty( $args[0] ) ) {
			throw new Exception( 'Missing positional argument. Please provide the plugin slug as first positional argument.' );
		}

		$plugin_slug = $args[0];

		$plugins = get_plugins();

		// Is the provided value is a full plugin basename?
		if ( isset( $plugins[ $plugin_slug ] ) ) {
			return $plugin_slug;
		}
		if ( strpos( $plugin_slug, '/' ) ) {
			throw new Exception(
				sprintf(
					'Invalid positional argument. Plugin with basename %s is not installed.',
					$plugin_slug
				)
			);
		}

		foreach ( $plugins as $plugin_basename => $plugin_data ) {
			if ( strpos( $plugin_basename, $plugin_slug . '/' ) === 0 ) {
				return $plugin_basename;
			}
		}

		throw new Exception(
			sprintf(
				'Invalid positional argument. Plugin with slug %s is not installed.',
				$plugin_slug
			)
		);
	}

	/**
	 * Gets the formatter instance to format check results.
	 *
	 * @since 1.0.0
	 *
	 * @param array $assoc_args Associative arguments.
	 * @return WP_CLI\Formatter The formatter instance.
	 */
	protected function get_formatter_from_assoc_args( array $assoc_args ) {
		$default_fields = array(
			'line',
			'column',
			'code',
			'message',
		);

		// If both errors and warnings are included, display the type of each result too.
		if ( empty( $assoc_args['ignore_errors'] ) && empty( $assoc_args['ignore_warnings'] ) ) {
			$default_fields = array(
				'line',
				'column',
				'type',
				'code',
				'message',
			);
		}

		return new WP_CLI\Formatter(
			$assoc_args,
			$default_fields
		);
	}

	/**
	 * Flattens and combines the given associative array of file errors and file warnings into a two-dimensional array.
	 *
	 * @since 1.0.0
	 *
	 * @param array $file_errors   Errors from a Check_Result, for a specific file.
	 * @param array $file_warnings Warnings from a Check_Result, for a specific file.
	 * @return array Combined file results.
	 */
	protected function flatten_file_results( array $file_errors, array $file_warnings ) {
		$file_results = array();

		foreach ( $file_errors as $line => $line_errors ) {
			foreach ( $line_errors as $column => $column_errors ) {
				foreach ( $column_errors as $column_error ) {
					$file_results[] = array_merge(
						$column_error,
						array(
							'type'   => 'ERROR',
							'line'   => $line,
							'column' => $column,
						)
					);
				}
			}
		}
		foreach ( $file_warnings as $line => $line_warnings ) {
			foreach ( $line_warnings as $column => $column_warnings ) {
				foreach ( $column_warnings as $column_warning ) {
					$file_results[] = array_merge(
						$column_warning,
						array(
							'type'   => 'WARNING',
							'line'   => $line,
							'column' => $column,
						)
					);
				}
			}
		}

		usort(
			$file_results,
			function( $a, $b ) {
				if ( $a['line'] < $b['line'] ) {
					return -1;
				}
				if ( $a['line'] > $b['line'] ) {
					return 1;
				}
				if ( $a['column'] < $b['column'] ) {
					return -1;
				}
				if ( $a['column'] > $b['column'] ) {
					return 1;
				}
				return 0;
			}
		);

		return $file_results;
	}

	/**
	 * Displays file results.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_CLI\Formatter $formatter    The formatter instance.
	 * @param string           $file_name    Full path to the file.
	 * @param array            $file_results Flattened array of file results.
	 */
	protected function display_file_results( $formatter, $file_name, array $file_results ) {
		WP_CLI::line(
			sprintf(
				'FILE: %s',
				$file_name
			)
		);
		$formatter->display_items( $file_results );
		WP_CLI::line();
		WP_CLI::line();
	}

	/**
	 * Prepares the environment for the commands when relevant.
	 *
	 * @since 1.0.0
	 */
	protected function prepare_for_commands() {
		// Bail early if no command line arguments available.
		if ( ! isset( $_SERVER['argv'] ) ) {
			return;
		}
		$cmd_args = $_SERVER['argv'];

		// Bail early if not at least 3 command line arguments passed.
		if ( ! isset( $cmd_args[1], $cmd_args[2], $cmd_args[3] ) ) {
			return;
		}
		$command_group = $cmd_args[1];
		$command_name  = $cmd_args[2];
		$plugin_slug   = $cmd_args[3];

		// Bail early if the invoked command does not belong to this class.
		if ( $command_group !== $this->command_group_name ) {
			return;
		}

		// Bail early if the invoked command is not one of the commands that need preparation.
		if ( ! in_array( $command_name, array( 'check-plugin' ), true ) ) {
			return;
		}

		// Bail early if plugin basename cannot be determined.
		try {
			$plugin_basename = $this->get_plugin_from_args( array( $plugin_slug ) );
		} catch ( Exception $e ) {
			return;
		}

		// Create Checks instance and prepare the environment for it.
		$checks = $this->get_checks_instance( $plugin_basename );
		add_action(
			'plugins_loaded',
			function() use ( $checks ) {
				$cleanup = $checks->prepare();

				add_action(
					'shutdown',
					function() use ( $cleanup ) {
						$cleanup();
					}
				);
			},
			1
		);
	}

	/**
	 * Gets the Checks instance to use.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_basename Plugin basename for the Checks instance.
	 * @return Checks The Checks instance to use.
	 */
	protected function get_checks_instance( $plugin_basename ) {
		// Use already configured instance for the plugin if available.
		if ( isset( $this->checks ) && $this->checks->plugin_basename() === $plugin_basename ) {
			return $this->checks;
		}

		$this->checks = new Checks(
			$this->context,
			WP_PLUGIN_DIR . '/' . $plugin_basename
		);
		return $this->checks;
	}
}
