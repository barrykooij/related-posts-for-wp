<?php
/**
 * The variant class file of the quality harness.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Quality;

/**
 * A variant of the algorithm to measure, from RP4WP_QUALITY_VARIANT: parts separated by commas, such as `tf-<k>` for
 * another term frequency saturation (`tf-0` for none; the 3.1 plan, phase 5). The results of a variant get its name
 * after the algorithm, so they are kept apart from the baseline: `free+tf-0-bbc.json`, which the report saves as
 * `freetf-0-bbc.json`.
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
	 * Add the filters of the variant that the free plugin knows. Call it in each test: the test suite removes the
	 * filters after every test.
	 *
	 * @return void
	 */
	public static function apply(): void {
		foreach ( self::parts() as $part ) {
			if ( 1 !== preg_match( '/^tf-(\d+(?:\.\d+)?)$/', $part, $matches ) ) {
				continue;
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

	/**
	 * The parts of the variant, separated by commas, for example `tf-10,phrases-3`. The free plugin knows `tf-<k>`; an
	 * algorithm adapter can read the others, such as premium's `phrases-<weight>`.
	 *
	 * @return string[]
	 */
	public static function parts(): array {
		return array_values( array_filter( array_map( 'trim', explode( ',', self::name() ) ) ) );
	}
}
