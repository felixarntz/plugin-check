<?php
/**
 * Class WordPress\Plugin_Check\Checker\Checks
 *
 * @package plugin-check
 */

namespace WordPress\Plugin_Check\Checker;

use WordPress\Plugin_Check\Plugin_Context;
use Exception;

/**
 * Class to run checks on a plugin.
 *
 * @since 1.0.0
 */
class Checks {

	/**
	 * Main context instance.
	 *
	 * @since 1.0.0
	 * @var Plugin_Context
	 */
	protected $main_context;

	/**
	 * Context for the plugin to check.
	 *
	 * @since 1.0.0
	 * @var Check_Context
	 */
	protected $check_context;

	/**
	 * Sets the main context and the main file of the plugin to check.
	 *
	 * @since 1.0.0
	 *
	 * @param Plugin_Context $main_context     Main context instance.
	 * @param string         $plugin_main_file Absolute path to the plugin main file.
	 */
	public function __construct( $main_context, $plugin_main_file ) {
		$this->main_context  = $main_context;
		$this->check_context = new Check_Context( $plugin_main_file );
	}

	/**
	 * Runs all checks against the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Result Object containing all check results.
	 *
	 * @throws Exception Thrown when checks fail with critical error.
	 */
	public function run_all_checks() {
		$result = new Check_Result( $this->check_context );
		$checks = $this->get_checks();

		$cleanup = $this->prepare();

		try {
			foreach ( $checks as $check ) {
				$check->run( $result );
			}
		} catch ( Exception $e ) {
			// Run clean up in case of any exception thrown from checks.
			$cleanup();
			throw $e;
		}

		$cleanup();

		return $result;
	}

	/**
	 * Runs a single check against the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param string $check Check class name.
	 * @return Check_Result Object containing all check results.
	 *
	 * @throws Exception Thrown when check fails with critical error.
	 */
	public function run_single_check( $check ) {
		$result = new Check_Result( $this->check_context );
		$checks = $this->get_checks();

		// Look up the check based on the $check variable.
		$check_index = array_search( $check, $checks, true );
		if ( false === $check_index ) {
			throw new Exception(
				sprintf(
					/* translators: %s: class name */
					__( 'Invalid check class name %s.', 'plugin-check' ),
					$check
				)
			);
		}

		$cleanup = $this->prepare();

		try {
			$checks[ $check_index ]->run( $result );
		} catch ( Exception $e ) {
			// Run clean up in case of any exception thrown from check.
			$cleanup();
			throw $e;
		}

		$cleanup();

		return $result;
	}

	/**
	 * Gets the available plugin check classes.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of plugin check classes implementing the Check interface.
	 */
	protected function get_checks() {
		// TODO: Implement checks.
		$checks = array();

		/**
		 * Filters the available plugin check classes.
		 *
		 * @since 1.0.0
		 *
		 * @param array $checks List of plugin check classes implementing the Check interface.
		 */
		return apply_filters( 'wp_plugin_check_checks', $checks );
	}

	/**
	 * Prepares the environment for running checks and returns a cleanup function.
	 *
	 * @since 1.0.0
	 *
	 * @return callable Cleanup function to revert any changes made here.
	 *
	 * @throws Exception Thrown when preparation fails.
	 */
	protected function prepare() {
		$preparations = array(
			new Preparations\Activate_Plugin_Preparation(
				$this->check_context->basename()
			),
			new Preparations\Use_Minimal_Theme_Preparation(
				'wp-empty-theme',
				$this->main_context()->path( '/themes' )
			),
		);

		$cleanups = array_map(
			function( Preparation $preparation ) {
				return $preparation->prepare();
			},
			$preparations
		);

		return function() use ( $cleanups ) {
			foreach ( $cleanups as $cleanup ) {
				$cleanup();
			}
		};
	}
}
