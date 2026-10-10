<?php
/**
 * The word statistics test file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Unit\Words;

use Brain\Monkey\Filters;
use LV2\WordPress\RelatedPostsForWP\Words\Statistics;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * The math of the word statistics (decisions D49 and D50): idf, the words a post keeps, and their weights.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Words\Statistics
 */
final class StatisticsTest extends TestCase {

	public function test_idf_is_ln_of_posts_plus_one_over_df(): void {
		$this->assertEqualsWithDelta( log( 11 / 2 ), Statistics::idf( 2, 10 ), 1e-9 );
		$this->assertEqualsWithDelta( log( 2 ), Statistics::idf( 1, 1 ), 1e-9, 'On a site with one post a word still weighs something.' );
		$this->assertEqualsWithDelta( log( 11 ), Statistics::idf( 0, 10 ), 1e-9, 'A word no post has counts as in one post.' );
		$this->assertSame( 0.0, Statistics::idf( 0, 0 ), 'On an empty table every word is the same.' );
		$this->assertSame( 0.0, Statistics::idf( 20, 10 ), 'Never below 0, even when the counts are off.' );
	}

	public function test_the_words_with_the_highest_tf_idf_are_kept(): void {
		$counts = [
			'wordpress' => 81,
			'reload'    => 80,
			'go'        => 3,
			'air'       => 2,
		];
		$df     = [
			'wordpress' => 90,
			'reload'    => 2,
			'go'        => 1,
			'air'       => 40,
		];

		// "wordpress" is in the title but in 90 of 100 posts: 81 * ln(101/90) is 9.3, less than 3 * ln(101/1) for "go".
		$this->assertSame(
			[
				'reload' => 80,
				'go'     => 3,
			],
			Statistics::pick( $counts, $df, 100, 2 )
		);
	}

	public function test_on_an_empty_table_the_words_that_count_most_are_kept_in_their_order(): void {
		$counts = [
			'b' => 5,
			'a' => 5,
			'c' => 1,
		];

		$this->assertSame(
			[
				'b' => 5,
				'a' => 5,
			],
			Statistics::pick( $counts, [], 0, 2 )
		);
	}

	public function test_numeric_words_stay_strings(): void {
		$this->assertSame( [ '2024' => 3 ], Statistics::pick( [ 2024 => 3 ], [], 0, 1 ) );
	}

	public function test_the_weights_of_a_post_have_length_1(): void {
		$weights = Statistics::weights(
			[
				'bread' => 80,
				'oven'  => 2,
				'flour' => 1,
			],
			[
				'bread' => 3,
				'oven'  => 10,
				'flour' => 1,
			],
			20
		);

		$this->assertEqualsWithDelta( 1.0, array_sum( array_map( static fn( $weight ) => $weight * $weight, $weights ) ), 1e-9 );
		$this->assertGreaterThan( $weights['oven'], $weights['bread'] );
		$this->assertEqualsWithDelta( 80 * log( 21 / 3 ) / ( 2 * log( 21 / 10 ) ), $weights['bread'] / $weights['oven'], 1e-9 );
	}

	public function test_a_post_whose_words_weigh_nothing_gets_weights_of_0(): void {
		$this->assertSame( [ 'word' => 0.0 ], Statistics::weights( [ 'word' => 3 ], [ 'word' => 1 ], 0 ) );
	}

	public function test_a_post_keeps_25_words_by_default(): void {
		$this->assertSame( 25, Statistics::amount() );

		Filters\expectApplied( 'rp4wp_cache_word_amount' )->andReturn( 6 );
		$this->assertSame( 6, Statistics::amount() );
	}
}
