<?php
/**
 * The free plugin's quality test file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Quality;

/**
 * How good the free plugin's related posts are, on every corpus that was built.
 *
 * @group quality
 *
 * @coversNothing
 */
final class QualityTest extends QualityTestCase {

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
