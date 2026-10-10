<?php
/**
 * The free plugin's scale test file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Quality;

/**
 * How fast the free plugin finds related posts on a large site.
 *
 * @group quality
 *
 * @coversNothing
 */
final class ScaleTest extends ScaleTestCase {

	/**
	 * The free plugin's algorithm.
	 *
	 * @return Algorithm
	 */
	protected static function algorithm(): Algorithm {
		return new CoreAlgorithm();
	}

	/**
	 * The root of the free plugin.
	 *
	 * @return string
	 */
	protected static function plugin_root(): string {
		return dirname( __DIR__, 2 );
	}
}
