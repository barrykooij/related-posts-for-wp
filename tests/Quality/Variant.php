<?php
/**
 * The variant class file of the quality harness.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Quality;

/**
 * A variant of the algorithm to measure, from RP4WP_QUALITY_VARIANT: `tf-<k>` for another term frequency saturation
 * (`tf-0` for none; the 3.1 plan, phase 5). The results of a variant get its name after the algorithm, so they are kept
 * apart from the baseline: `free+tf-0-bbc.json`, which the report saves as `freetf-0-bbc.json`.
 */
final class Variant {

	/**
	 * The variant asked for; empty for none.
	 *
	 * @return string
	 */
	public static function name(): string {
		$variant = getenv( 'RP4WP_QUALITY_VARIANT' );

		return false === $variant ? '' : trim( (string) $variant );
	}

	/**
	 * What to add to the name of the algorithm: `+` and the variant, or nothing.
	 *
	 * @return string
	 */
	public static function suffix(): string {
		return '' === self::name() ? '' : '+' . self::name();
	}

	/**
	 * Add the filters of the variant. Call it in each test: the test suite removes the filters after every test.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException For a variant the harness does not know.
	 */
	public static function apply(): void {
		$name = self::name();
		if ( '' === $name ) {
			return;
		}

		if ( 1 !== preg_match( '/^tf-(\d+(?:\.\d+)?)$/', $name, $matches ) ) {
			throw new \InvalidArgumentException( "Unknown variant {$name}; use tf-<k>." );
		}

		$k = (float) $matches[1];

		add_filter(
			'rp4wp_tf_saturation',
			static function () use ( $k ): float {
				return $k;
			}
		);
	}
}
