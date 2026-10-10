<?php
/**
 * The word statistics test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Words;

use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Install\Tasks\WeighWordsTask;
use LV2\WordPress\RelatedPostsForWP\Related\Finder;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;
use LV2\WordPress\RelatedPostsForWP\Words\Statistics;

/**
 * Two-pass caching (decision D54): the document frequencies and the number of posts follow the words of posts, a
 * recount corrects them, pass two weighs in the database as the formula says, and the finder compares the weights.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Words\Statistics
 * @covers \LV2\WordPress\RelatedPostsForWP\Words\Cache
 * @covers \LV2\WordPress\RelatedPostsForWP\Install\Tasks\WeighWordsTask
 * @covers \LV2\WordPress\RelatedPostsForWP\Related\FinderQuery
 */
final class StatisticsTest extends TestCase {

	/**
	 * The word statistics.
	 *
	 * @var Statistics
	 */
	private Statistics $statistics;

	public function set_up(): void {
		parent::set_up();

		$this->statistics = new Statistics();
		delete_option( WeighWordsTask::OPTION );
	}

	public function test_the_document_frequencies_follow_the_words_of_posts(): void {
		$first  = $this->post( 'Sourdough starter', 'Feed the sourdough starter every day.' );
		$second = $this->post( 'Sourdough bread', 'Bake sourdough bread in a hot oven.' );

		$this->assertSame( 2, $this->df( 'sourdough' ) );
		$this->assertSame( 1, $this->df( 'oven' ) );
		$this->assertSame( 2, $this->statistics->posts() );

		wp_update_post(
			[
				'ID'           => $first,
				'post_title'   => 'Rye starter',
				'post_content' => 'Feed the rye starter every day.',
			]
		);
		$this->assertSame( 1, $this->df( 'sourdough' ), 'An update takes the old words out.' );
		$this->assertSame( 1, $this->df( 'rye' ) );

		wp_update_post(
			[
				'ID'          => $second,
				'post_status' => 'draft',
			]
		);
		$this->assertNull( $this->df( 'sourdough' ), 'A word no post has is gone.' );
		$this->assertSame( 1, $this->statistics->posts() );

		wp_set_current_user( 0 );
		wp_delete_post( $first, true );
		$this->assertNull( $this->df( 'rye' ) );
		$this->assertSame( 0, $this->statistics->posts() );
	}

	public function test_a_recount_corrects_the_counts(): void {
		global $wpdb;

		$this->post( 'Sourdough starter', 'Feed the sourdough starter every day.' );
		$this->post( 'Sourdough bread', 'Bake sourdough bread in a hot oven.' );
		$wpdb->query( 'UPDATE ' . Statistics::words_table() . " SET df = 7 WHERE word = 'sourdough'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Test data.
		$wpdb->query( 'INSERT INTO ' . Statistics::words_table() . " (word, df) VALUES ('ghost', 3)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Test data.
		update_option( Statistics::OPTION_POSTS, 40 );

		$this->statistics->recount();

		$this->assertSame( 2, $this->df( 'sourdough' ) );
		$this->assertNull( $this->df( 'ghost' ) );
		$this->assertSame( 2, $this->statistics->posts() );
	}

	public function test_pass_two_weighs_as_the_formula_says(): void {
		$posts = [
			$this->post( 'Sourdough starter', 'Feed the sourdough starter every day with flour and water.' ),
			$this->post( 'Sourdough bread', 'Bake sourdough bread in a hot oven with flour.' ),
			$this->post( 'Banana bread', 'Mash bananas into the dough of a sweet bread.' ),
		];

		( new WeighWordsTask( 0 ) )->run_batch();

		foreach ( $posts as $post_id ) {
			$rows     = $this->rows( $post_id );
			$counts   = array_map( 'intval', array_column( $rows, 'tf', 'word' ) );
			$expected = Statistics::weights( $counts, $this->statistics->df( array_keys( $counts ) ), 3 );

			foreach ( $rows as $row ) {
				$this->assertEqualsWithDelta( $expected[ $row['word'] ], (float) $row['weight'], 1e-5, "The weight of {$row['word']}." );
			}

			$this->assertEqualsWithDelta( 1.0, array_sum( array_map( static fn( $row ) => (float) $row['weight'] ** 2, $rows ) ), 1e-4, 'The weights of a post have length 1.' );
		}
	}

	public function test_a_post_keeps_the_25_words_that_say_most_about_it(): void {
		$words   = [];
		$letters = range( 'a', 'z' );
		foreach ( $letters as $first ) {
			foreach ( [ 'x', 'y' ] as $second ) {
				$words[] = 'word' . $first . $second;
			}
		}
		$post_id = $this->post( 'Sourdough notebook', implode( ' ', $words ) );

		$this->assertCount( 25, $this->rows( $post_id ) );
		$this->assertArrayHasKey( 'sourdough', $this->rows( $post_id ), 'A title word counts 5 times.' );
	}

	public function test_the_score_is_a_cosine_and_ties_go_by_id(): void {
		$first  = $this->post( 'Climbing roses', 'Climbing roses on a garden wall.' );
		$second = $this->post( 'Climbing roses', 'Climbing roses on a garden wall.' );
		$third  = $this->post( 'Climbing roses', 'Climbing roses on a garden wall.' );
		( new WeighWordsTask( 0 ) )->run_batch();

		$related = ( new Finder() )->related_posts( $third, 5 );

		$this->assertSame( [ $first, $second ], array_map( 'intval', array_column( $related, 'ID' ) ) );
		$this->assertEqualsWithDelta( 1.0, (float) $related[0]->CMS, 1e-4, 'The same words give a score of 1.' );
		$this->assertSame( 'Climbing roses', $related[0]->post_title );
	}

	public function test_words_above_the_ceiling_are_not_compared(): void {
		$first  = $this->post( 'Pruning roses', 'Prune the roses in spring.' );
		$second = $this->post( 'Climbing roses', 'Roses climb a wall.' );

		$this->assertContains( $first, $this->related( $second ) );

		add_filter(
			'rp4wp_word_df_ceiling',
			static function () {
				return 1;
			}
		);

		$this->assertNotContains( $first, $this->related( $second ), 'They only share "roses", which is in 2 posts.' );
	}

	public function test_the_weighing_step_counts_first_and_then_weighs_in_batches(): void {
		global $wpdb;

		$posts = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$posts[] = $this->post( "Garden post {$i}", "Compost and roses number {$i}." );
		}
		$wpdb->query( 'UPDATE ' . Table::name() . ' SET weight = 0' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Test data.
		add_filter( 'rp4wp_install_batch_size', '__return_true' );

		$task = new WeighWordsTask( 1000 );
		$this->assertSame( 4, $task->remaining(), 'The count and 3 posts.' );

		$this->assertFalse( $task->run_batch(), 'The count.' );
		$this->assertSame( 3, $task->remaining() );
		$this->assertFalse( $task->run_batch() );
		$this->assertFalse( $task->run_batch() );
		$this->assertTrue( $task->run_batch() );
		$this->assertSame( 0, $task->remaining() );
		$this->assertGreaterThan( 0, (float) $this->rows( $posts[2] )['compost']['weight'] );

		$this->assertSame( 4, ( new WeighWordsTask( 2000 ) )->remaining(), 'A new job starts over.' );
	}

	/**
	 * A published post.
	 *
	 * @param string $title   The title.
	 * @param string $content The content.
	 *
	 * @return int
	 */
	private function post( string $title, string $content ): int {
		return self::factory()->post->create(
			[
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'publish',
			]
		);
	}

	/**
	 * In how many posts a word is, or null when the words table does not have it.
	 *
	 * @param string $word The word.
	 *
	 * @return int|null
	 */
	private function df( string $word ): ?int {
		return $this->statistics->df( [ $word ] )[ $word ] ?? null;
	}

	/**
	 * The stored words of a post, by word.
	 *
	 * @param int $post_id The post.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function rows( int $post_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Reading our own table.
		return array_column( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT word, weight, tf FROM ' . Table::name() . ' WHERE post_id = %d', $post_id ), ARRAY_A ), null, 'word' );
	}

	/**
	 * The related posts the finder finds.
	 *
	 * @param int $post_id The post.
	 *
	 * @return int[]
	 */
	private function related( int $post_id ): array {
		return array_map( 'intval', array_column( ( new Finder() )->related_posts( $post_id, 5 ), 'ID' ) );
	}
}
