<?php
/**
 * The quality metrics test file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Unit\Quality;

use LV2\WordPress\RelatedPostsForWP\Tests\Quality\Metrics;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * The metrics of the quality harness, on cases small enough to work out by hand.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Tests\Quality\Metrics
 */
final class MetricsTest extends TestCase {

	public function test_precision_counts_hits_against_the_smaller_of_k_and_the_labels(): void {
		$metrics = Metrics::precision(
			6,
			[
				1 => [ 2, 4, 3, 5, 6 ],
				2 => [ 3, 4, 5 ],
			],
			[
				1 => [ 2, 3 ],
				2 => [ 1 ],
			]
		);

		// Post 1 finds both of its two related posts in its first 3; post 2 finds none.
		$this->assertSame( 2, $metrics['evaluated'] );
		$this->assertSame( 0.5, $metrics['precision_at_3'] );
		$this->assertSame( 0.5, $metrics['precision_at_5'] );
		$this->assertSame( 0.5, $metrics['success_at_3'] );

		// Three random picks out of the 5 other posts find 3 * 2 / 5 = 1.2 of post 1's two (0.6), and 3 * 1 / 5 = 0.6
		// of post 2's one.
		$this->assertSame( 0.6, $metrics['random_precision_at_3'] );
	}

	public function test_a_missing_related_post_is_a_miss_and_a_post_is_never_its_own_match(): void {
		$metrics = Metrics::precision(
			10,
			[
				1 => [ 2 ],
				3 => [],
			],
			[
				1 => [ 1, 2, 4, 5 ],
				2 => [ 1 ],
				3 => [ 3 ],
			]
		);

		// Post 1: one hit of 3 (2, not itself). Post 2 has no ranking at all: 0. Post 3 has only itself: not evaluated.
		$this->assertSame( 2, $metrics['evaluated'] );
		$this->assertSame( 0.1667, $metrics['precision_at_3'] );
		$this->assertSame( 0.5, $metrics['success_at_3'] );
	}

	public function test_no_labels_give_no_precision(): void {
		$metrics = Metrics::precision( 3, [ 1 => [ 2 ] ], [] );

		$this->assertSame( 0, $metrics['evaluated'] );
		$this->assertNull( $metrics['precision_at_3'] );
	}

	public function test_links_report_full_lists_coverage_hubs_and_symmetry(): void {
		$metrics = Metrics::links(
			[ 1, 2, 3, 4 ],
			[
				1 => [ 2, 3, 4, 2 ],
				2 => [ 1, 3 ],
				3 => [ 1 ],
			]
		);

		// Only post 1 shows 3 related posts (its fourth is cut off). Every post is shown somewhere. Six links, of which
		// 1-2, 1-3, 2-1 and 3-1 go both ways. The most shown post (1 percent, at least one) has 2 of the 6.
		$this->assertSame( 0.25, $metrics['full_lists'] );
		$this->assertSame( 1.0, $metrics['coverage'] );
		$this->assertSame( 0.6667, $metrics['symmetry'] );
		$this->assertSame( 0.3333, $metrics['hub_share'] );
		$this->assertSame( 2, $metrics['max_inbound'] );
	}

	public function test_word_metrics_skip_taxonomy_tokens(): void {
		$metrics = Metrics::words_metrics(
			[ 1 => [ 'the', 'bread', 'cat:12', 'post:7' ] ],
			[ 1 => 'Baking bread' ],
			Metrics::stop_set( [ 'the', 'and' ] )
		);

		$this->assertSame( 0.5, $metrics['stop_word_leak'] );
		$this->assertSame( 0.5, $metrics['title_share'] );
		$this->assertSame( 2.0, $metrics['words_per_post'] );
	}

	public function test_stop_words_match_in_every_spelling_the_plugins_store(): void {
		$set = Metrics::stop_set( [ 'für', 'Über' ] );

		$this->assertArrayHasKey( 'fur', $set );
		$this->assertArrayHasKey( 'fuer', $set );
		$this->assertArrayHasKey( 'uber', $set );
		$this->assertArrayHasKey( 'ueber', $set );
	}

	public function test_without_a_stop_word_list_the_leak_is_unknown(): void {
		$metrics = Metrics::words_metrics( [ 1 => [ 'bread' ] ], [ 1 => 'Bread' ], null );

		$this->assertNull( $metrics['stop_word_leak'] );
		$this->assertSame( 1.0, $metrics['title_share'] );
	}

	public function test_words_of_scripts_without_spaces_count_as_title_words_when_the_title_contains_them(): void {
		$metrics = Metrics::words_metrics( [ 1 => [ 'コー', '東京', 'ラーメン' ] ], [ 1 => '東京のコーヒー' ], null );

		$this->assertSame( 0.6667, $metrics['title_share'] );
	}

	public function test_words_are_lower_case_without_accents(): void {
		$this->assertSame( [ 'creme', 'brulee' ], Metrics::words( 'Crème brûlée!' ) );
	}
}
