<?php
/**
 * Plugin Name: Plugin Check (Proof of concept)
 * Plugin URI: https://github.com/felixarntz/plugin-check/
 * Description: A simple and easy way to test your plugin for all the latest WordPress standards and practices. A great plugin development tool!
 * Requires at least: 5.8
 * Requires PHP: 5.6
 * Version: 1.0.0
 * Author: WordPress Performance Group
 * Author URI: https://make.wordpress.org/core/tag/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: plugin-check
 *
 * @package plugin-check
 */

define( 'WP_PLUGIN_CHECK_VERSION', '1.0.0' );
define( 'WP_PLUGIN_CHECK_MINIMUM_PHP', '5.6' );

/**
 * Loads the plugin if basic requirements are met.
 *
 * @since 1.0.0
 */
function wp_plugin_check_load() {
	if ( version_compare( phpversion(), WP_PLUGIN_CHECK_MINIMUM_PHP, '<' ) ) {
		add_action( 'admin_notices', 'wp_plugin_check_display_php_version_notice' );
		return;
	}

	if ( ! file_exists( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' ) ) {
		add_action( 'admin_notices', 'wp_plugin_check_display_composer_autoload_notice' );
		return;
	}

	$class_name = 'WordPress\\Plugin_Check\\Plugin_Main';
	$instance   = new $class_name( __FILE__ );
	$instance->add_hooks();
}

/**
 * Displays admin notice about unmet PHP version requirement.
 *
 * @since 1.0.0
 */
function wp_plugin_check_display_php_version_notice() {
	echo '<div class="notice notice-error"><p>';
	printf(
		/* translators: 1: required version, 2: currently used version */
		__( 'Plugin Check requires at least PHP version %1$s. Your site is currently running on PHP %2$s.', 'plugin-check' ),
		WP_PLUGIN_CHECK_MINIMUM_PHP,
		phpversion()
	);
	echo '</p></div>';
}

/**
 * Displays admin notice about missing Composer autoload files.
 *
 * @since 1.0.0
 */
function wp_plugin_check_display_composer_autoload_notice() {
	echo '<div class="notice notice-error"><p>';
	echo wp_kses(
		__( 'Composer autoload files are missing. Please run <code>composer install</code>.', 'plugin-check' ),
		array(
			'code' => array(),
		)
	);
	echo '</p></div>';
}

wp_plugin_check_load();
