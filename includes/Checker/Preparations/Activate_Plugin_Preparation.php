<?php
/**
 * Class WordPress\Plugin_Check\Checker\Preparations\Activate_Plugin_Preparation
 *
 * @package plugin-check
 */

namespace WordPress\Plugin_Check\Checker\Preparations;

use WordPress\Plugin_Check\Checker\Preparation;
use Exception;

/**
 * Class for the preparation step to activate the plugin.
 *
 * @since 1.0.0
 */
class Activate_Plugin_Preparation implements Preparation {

	/**
	 * Plugin basename.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $plugin_basename;

	/**
	 * Sets the plugin basename of the plugin to check.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_basename Plugin basename.
	 */
	public function __construct( $plugin_basename ) {
		$this->plugin_basename = $plugin_basename;
	}

	/**
	 * Runs this preparation step for the environment and returns a cleanup function.
	 *
	 * @since 1.0.0
	 *
	 * @return callable Cleanup function to revert any changes made here.
	 *
	 * @throws Exception Thrown when preparation fails.
	 */
	public function prepare() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		// Activate plugin if not active yet.
		if ( ! is_plugin_active( $this->plugin_basename ) ) {
			$result = activate_plugin( $this->plugin_basename, '', false, true );
			if ( is_wp_error( $result ) ) {
				throw new Exception(
					sprintf(
						/* translators: %s: WP error message */
						__( 'Could not activate plugin: %s', 'plugin-check' ),
						$result->get_error_message()
					)
				);
			}
			return function() {
				deactivate_plugins( array( $this->plugin_basename ), true );
			};
		}

		// Otherwise do nothing and return no-op cleanup function.
		return function() {};
	}
}
