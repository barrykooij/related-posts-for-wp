<?php
/**
 * The saturation test file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Unit\Words;

use Brain\Monkey\Filters;
use LV2\WordPress\RelatedPostsForWP\Words\Statistics;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * How often a word is in a post saturates when the words are weighed (decision D66): `tf * ( k + 1 ) / ( tf + k )`.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Words\Statistics::saturate
 * @covers \LV2\WordPress\RelatedPostsForWP\Words\Statistics::saturation
 * @covers \LV2\WordPress\RelatedPostsForWP\Words\Statistics::weights
 */
final class SaturationTest extends TestCase {

	public function test_a_word_that_counts_once_still_counts_once(): void {
		$this->assertEqualsWithDelta( 1.0, Statistics::saturate( 1.0, 10.0 ), 1e-9 );
	}

	public function test_a_frequent_word_counts_less_than_it_is_there_but_never_less_than_a_rarer_one(): void {
		$five  = Statistics::saturate( 5.0, 10.0 );
		$forty = Statistics::saturate( 40.0, 10.0 );

		$this->assertEqualsWithDelta( 55 / 15, $five, 1e-9 );
		$this->assertLessThan( 5.0, $five );
		$this->assertGreaterThan( $five, $forty );
		$this->assertLessThan( 11.0, $forty, 'It never counts more than k + 1 times.' );
	}

	public function test_without_saturation_the_frequency_stays(): void {
		$this->assertSame( 40.0, Statistics::saturate( 40.0, 0.0 ) );
		$this->assertSame( 0.0, Statistics::saturate( 0.0, 10.0 ) );
	}

	public function test_the_filter_sets_it_and_never_below_zero(): void {
		$this->assertSame( Statistics::SATURATION, Statistics::saturation() );

		Filters\expectApplied( 'rp4wp_tf_saturation' )->andReturn( -3 );
		$this->assertSame( 0.0, Statistics::saturation() );
	}

	public function test_the_weights_use_the_saturated_frequency(): void {
		Filters\expectApplied( 'rp4wp_tf_saturation' )->twice()->andReturnValues( [ 0.0, 10.0 ] );

		$plain     = Statistics::weights(
			[
				'roses'  => 40,
				'garden' => 1,
			],
			[],
			100
		);
		$saturated = Statistics::weights(
			[
				'roses'  => 40,
				'garden' => 1,
			],
			[],
			100
		);

		$this->assertGreaterThan( $plain['garden'], $saturated['garden'], 'The rare word weighs more next to a saturated one.' );
	}
}
