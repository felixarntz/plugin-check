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
		$plugin = WP_PLUGIN_DIR . '/' . $this->plugin_basename;
		if ( ! file_exists( $plugin ) ) {
			throw new Exception(
				sprintf(
					/* translators: %s: plugin basename */
					__( 'Plugin file for %s not found.', 'plugin-check' ),
					$this->plugin_basename
				)
			);
		}

		// Override active plugins.
		add_filter( 'option_active_plugins', array( $this, 'filter_active_plugins' ) );
		add_filter( 'default_option_active_plugins', array( $this, 'filter_active_plugins' ) );

		$_wp_plugin_file = $plugin;
		include_once $plugin;
		$plugin = $_wp_plugin_file; // Avoid stomping of the $plugin variable in a plugin.

		return function() {
			remove_filter( 'option_active_plugins', array( $this, 'filter_active_plugins' ) );
			remove_filter( 'default_option_active_plugins', array( $this, 'filter_active_plugins' ) );
		};
	}

	/**
	 * Filters the active plugins option to ensure the plugin is active.
	 *
	 * @since 1.0.0
	 *
	 * @param array|mixed $active_plugins List of active plugin basenames.
	 * @return array Modified value of $active_plugins.
	 */
	public function filter_active_plugins( $active_plugins ) {
		if ( ! is_array( $active_plugins ) ) {
			return array( $this->plugin_basename );
		}

		if ( ! in_array( $this->plugin_basename, $active_plugins, true ) ) {
			$active_plugins[] = $this->plugin_basename;
		}

		return $active_plugins;
	}
}
