<?php
/**
 * Class WordPress\Plugin_Check\Plugin_Main
 *
 * @package plugin-check
 */

namespace WordPress\Plugin_Check;

/**
 * Main class for the plugin.
 *
 * @since 1.0.0
 */
class Plugin_Main {

	/**
	 * Context instance for the plugin.
	 *
	 * @since 1.0.0
	 * @var Plugin_Context
	 */
	protected $context;

	/**
	 * Sets the plugin main file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $main_file Absolute path to the plugin main file.
	 */
	public function __construct( $main_file ) {
		$this->context = new Plugin_Context( $main_file );
	}

	/**
	 * Adds WordPress hooks for the plugin.
	 *
	 * @since 1.0.0
	 */
	public function add_hooks() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$command = new CLI\Plugin_Check_Command( $this->context, 'plugin-check' );
			\WP_CLI::add_command( 'plugin-check', $command );
		}
	}
}
