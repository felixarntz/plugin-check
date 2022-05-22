<?php
/**
 * Interface WordPress\Plugin_Check\Checker\Preparation
 *
 * @package plugin-check
 */

namespace WordPress\Plugin_Check\Checker;

use Exception;

/**
 * Interface for a single preparation step.
 *
 * @since 1.0.0
 */
interface Preparation {

	/**
	 * Runs this preparation step for the environment and returns a cleanup function.
	 *
	 * @since 1.0.0
	 *
	 * @return callable Cleanup function to revert any changes made here.
	 *
	 * @throws Exception Thrown when preparation fails.
	 */
	public function prepare();
}
