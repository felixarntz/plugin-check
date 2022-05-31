<?php
/**
 * Class WordPress\Plugin_Check\Checker\Checks\Abstract_Request_Check
 *
 * @package plugin-check
 */

namespace WordPress\Plugin_Check\Checker\Checks;

use WordPress\Plugin_Check\Checker\Check;
use WordPress\Plugin_Check\Checker\Check_Result;
use WordPress\Plugin_Check\Checker\Preparation;
use Exception;

/**
 * Abstract class for checks that require URL and query context.
 *
 * @since 1.0.0
 */
abstract class Abstract_Request_Check implements Check, Preparation {

	/**
	 * List of relevant global query variables to modify.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $query_globals = array(
		'query_string',
		'id',
		'postdata',
		'authordata',
		'day',
		'currentmonth',
		'page',
		'pages',
		'multipage',
		'more',
		'numpages',
		'pagenow',
		'current_screen',
	);

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
		// Store the original values for any global state that may be modified.
		$get    = $_GET;
		$post   = $_POST;
		$server = $_SERVER;

		$global_vars = array();
		foreach ( $this->query_globals as $query_global ) {
			if ( isset( $GLOBALS[ $query_global ] ) ) {
				$global_vars[ $query_global ] = $GLOBALS[ $query_global ];
			}
		}
		if ( isset( $GLOBALS['wp_query'] ) ) {
			$global_vars['wp_query'] = $GLOBALS['wp_query'];
		}
		if ( isset( $GLOBALS['wp_the_query'] ) ) {
			$global_vars['wp_the_query'] = $GLOBALS['wp_the_query'];
		}
		if ( isset( $GLOBALS['wp'] ) ) {
			$global_vars['wp'] = $GLOBALS['wp'];
		}

		// Return a function that cleans up any global state potentially modified.
		return function() use ( $get, $post, $server, $global_vars ) {
			$_GET    = $get;
			$_POST   = $post;
			$_SERVER = $server;

			foreach ( $this->query_globals as $query_global ) {
				if ( isset( $global_vars[ $query_global ] ) ) {
					$GLOBALS[ $query_global ] = $global_vars[ $query_global ];
				} else {
					unset( $GLOBALS[ $query_global ] );
				}
			}
			if ( isset( $global_vars['wp_query'] ) ) {
				$GLOBALS['wp_query'] = $global_vars['wp_query'];
			} else {
				unset( $GLOBALS['wp_query'] );
			}
			if ( isset( $global_vars['wp_the_query'] ) ) {
				$GLOBALS['wp_the_query'] = $global_vars['wp_the_query'];
			} else {
				unset( $GLOBALS['wp_the_query'] );
			}
			if ( isset( $global_vars['wp'] ) ) {
				$GLOBALS['wp'] = $global_vars['wp'];
			} else {
				unset( $GLOBALS['wp'] );
			}
		};
	}

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
		$urls = $this->get_urls();
		foreach ( $urls as $url ) {
			$this->go_to( $url );
			$this->run_for_url( $result, $url );
		}
	}

	/**
	 * Gets the list of URLs to run this check for.
	 *
	 * @since 1.0.0
	 *
	 * @return string Array of URL strings (either full URLs or paths).
	 */
	abstract protected function get_urls();

	/**
	 * Amends the given result by running the check for the given URL.
	 *
	 * @since 1.0.0
	 *
	 * @param Check_Result $result The check result to amend, including the plugin context to check.
	 * @param string       $url    URL to run the check for.
	 *
	 * @throws Exception Thrown when the check fails with a critical error (unrelated to any errors detected as part of
	 *                   the check).
	 */
	abstract protected function run_for_url( Check_Result $result, $url );

	/**
	 * Sets the global state to as if a given URL has been requested.
	 *
	 * This implementation is very similar to the one from the WordPress core test suite.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url URL to simulate request for.
	 */
	protected function go_to( $url ) {
		$_GET  = array();
		$_POST = array();
		foreach ( $this->query_globals as $v ) {
			if ( isset( $GLOBALS[ $v ] ) ) {
				unset( $GLOBALS[ $v ] );
			}
		}

		$parts = parse_url( $url );
		if ( isset( $parts['scheme'] ) ) {
			$req = isset( $parts['path'] ) ? $parts['path'] : '';
			if ( isset( $parts['query'] ) ) {
				$req .= '?' . $parts['query'];
				// Parse the URL query vars into $_GET.
				parse_str( $parts['query'], $_GET );
			}
		} else {
			$req = $url;
		}

		if ( ! isset( $parts['query'] ) ) {
			$parts['query'] = '';
		}

		$_SERVER['REQUEST_URI'] = $req;
		unset( $_SERVER['PATH_INFO'] );

		wp_cache_flush();

		unset( $GLOBALS['wp_query'], $GLOBALS['wp_the_query'] );
		$GLOBALS['wp_the_query'] = new \WP_Query();
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];

		$public_query_vars  = $GLOBALS['wp']->public_query_vars;
		$private_query_vars = $GLOBALS['wp']->private_query_vars;

		$GLOBALS['wp']                     = new \WP();
		$GLOBALS['wp']->public_query_vars  = $public_query_vars;
		$GLOBALS['wp']->private_query_vars = $private_query_vars;

		// Clean up query vars.
		foreach ( $GLOBALS['wp']->public_query_vars as $v ) {
			unset( $GLOBALS[ $v ] );
		}
		foreach ( $GLOBALS['wp']->private_query_vars as $v ) {
			unset( $GLOBALS[ $v ] );
		}

		// Set up query vars for taxonomies and post types.
		foreach ( get_taxonomies( array(), 'objects' ) as $t ) {
			if ( $t->publicly_queryable && ! empty( $t->query_var ) ) {
				$GLOBALS['wp']->add_query_var( $t->query_var );
			}
		}
		foreach ( get_post_types( array(), 'objects' ) as $t ) {
			if ( is_post_type_viewable( $t ) && ! empty( $t->query_var ) ) {
				$GLOBALS['wp']->add_query_var( $t->query_var );
			}
		}

		$GLOBALS['wp']->main( $parts['query'] );
	}
}
