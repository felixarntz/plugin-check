<?php
/**
 * Interface WordPress\Plugin_Check\Checker\Check
 *
 * @package plugin-check
 */

namespace WordPress\Plugin_Check\Checker;

use Exception;

/**
 * Interface for a single check.
 *
 * @since 1.0.0
 */
interface Check {

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
	public function run( Check_Result $result );
}
