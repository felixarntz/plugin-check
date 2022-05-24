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

		$cleanup = $this->prepare();

		try {
			// TODO: Run checks.
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
	 * @param string $check Check identifier.
	 * @return Check_Result Object containing all check results.
	 *
	 * @throws Exception Thrown when check fails with critical error.
	 */
	public function run_single_check( $check ) {
		$result = new Check_Result( $this->check_context );

		$cleanup = $this->prepare();

		try {
			// TODO: Run single check.
		} catch ( Exception $e ) {
			// Run clean up in case of any exception thrown from check.
			$cleanup();
			throw $e;
		}

		$cleanup();

		return $result;
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
