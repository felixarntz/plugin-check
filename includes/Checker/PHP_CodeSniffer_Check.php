<?php
/**
 * Class WordPress\Plugin_Check\Checker\PHP_CodeSniffer_Check
 *
 * @package plugin-check
 */

namespace WordPress\Plugin_Check\Checker;

use Exception;

/**
 * Check for running one or more PHP CodeSniffer sniffs.
 *
 * @since 1.0.0
 */
class PHP_CodeSniffer_Check implements Check {

	/**
	 * Amends the given result by running the check on the associated plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param Check_Result $result The check result to amend, including the plugin context to check.
	 *
	 * @throws Exception Thrown when the check fails with a critical error (unrelated to any errors detected as part of
	 *                   the check).
	 */
	public function run( Check_Result $result ) {
		$path = $result->context()->path( 'vendor/squizlabs/php_codesniffer/autoload.php' );
		if ( file_exists( $path ) ) {
			include_once $path;
		}

		$orig_cmd_args   = $_SERVER['argv'];
		$_SERVER['argv'] = array(
			'',
			$result->plugin()->path( '' ),
			'--extensions=php',
			'--standard=WordPress-Core',
			'--report=Json',
			'--report-width=9999',
		);

		// Run PHPCS.
		try {
			ob_start();
			$runner = new \PHP_CodeSniffer\Runner();
			$runner->runPHPCS();
			$reports = ob_get_clean();
		} catch ( Exception $e ) {
			$_SERVER['argv'] = $orig_cmd_args;
			throw $e;
		}

		// Parse the reports into data to add to the overall $result.
		$reports = json_decode( trim( $reports ), true );
		if ( empty( $reports['files'] ) ) {
			return;
		}
		foreach ( $reports['files'] as $file_name => $file_results ) {
			if ( empty( $file_results['messages'] ) ) {
				continue;
			}
			foreach ( $file_results['messages'] as $file_message ) {
				$result->add_message(
					strtoupper( $file_message['type'] ) === 'ERROR',
					$file_message['message'],
					array(
						'code'   => $file_message['source'],
						'file'   => $file_name,
						'line'   => $file_message['line'],
						'column' => $file_message['column'],
					)
				);
			}
		}
	}
}
