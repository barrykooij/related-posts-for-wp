<?php
/**
 * The term tokens test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Words;

use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Install\Tasks\WeighWordsTask;
use LV2\WordPress\RelatedPostsForWP\Related\Finder;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * Tokens for what a post is part of and points to (decision D53): its categories but the default one, its tags, itself
 * and the posts it links to. Posts that share a tag, or link to each other, are related without sharing a word.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Words\Extractor
 */
final class TermTokensTest extends TestCase {

	public function test_a_post_gets_tokens_for_its_terms_itself_and_the_posts_it_links_to(): void {
		$category = self::factory()->category->create( [ 'name' => 'Baking' ] );
		$linked   = $this->post( 'Rye starter', 'Feed the rye starter.' );
		$post_id  = $this->post( 'Sourdough bread', 'Read about the <a href="' . get_permalink( $linked ) . '">starter</a> first.' );
		wp_set_post_categories( $post_id, [ $category, (int) get_option( 'default_category' ) ] );
		wp_set_post_tags( $post_id, [ 'Bread' ] );
		$tag = (int) get_term_by( 'name', 'Bread', 'post_tag' )->term_id;
		$this->save( $post_id );

		$tf = array_map( 'intval', array_column( $this->rows( $post_id ), 'tf', 'word' ) );

		$this->assertSame( 1, $tf[ 'post:' . $post_id ] );
		$this->assertSame( 10, $tf[ 'post:' . $linked ], 'A linked post counts as the link weight.' );
		$this->assertSame( 10, $tf[ 'cat:' . $category ] );
		$this->assertSame( 5, $tf[ 'tag:' . $tag ] );
		$this->assertArrayNotHasKey( 'cat:' . get_option( 'default_category' ), $tf, 'The default category says nothing.' );
		$this->assertArrayHasKey( 'baking', $tf, 'The name of a category is a word too.' );
		$this->assertArrayHasKey( 'rye', $tf, 'So are the words of the title of a linked post.' );
	}

	public function test_posts_with_the_same_tag_are_related_without_a_shared_word(): void {
		$first  = $this->post( 'Pruning roses', 'Cut back in early spring.' );
		$second = $this->post( 'Tomato seeds', 'Dry them on a paper towel.' );
		$third  = $this->post( 'Laptop stickers', 'Peel slowly to avoid bubbles.' );
		wp_set_post_tags( $first, [ 'Garden' ] );
		wp_set_post_tags( $second, [ 'Garden' ] );
		$this->save( $first, $second, $third );

		$this->assertSame( [ $first ], $this->related( $second ) );
	}

	public function test_a_post_and_a_post_it_links_to_are_related(): void {
		$target = $this->post( 'Composting basics', 'Brown and green matter.' );
		$source = $this->post( 'Raised beds', 'Fill them with <a href="' . get_permalink( $target ) . '">compost</a>.' );
		$this->post( 'Train travel', 'Night trains across Europe.' );
		$this->save( $target, $source );

		$this->assertContains( $target, $this->related( $source ) );
		$this->assertContains( $source, $this->related( $target ) );
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
	 * Cache the words of posts again, now their terms are set, and weigh every post.
	 *
	 * @param int ...$post_ids The posts.
	 *
	 * @return void
	 */
	private function save( int ...$post_ids ): void {
		$cache = new \LV2\WordPress\RelatedPostsForWP\Words\Cache();
		foreach ( $post_ids as $post_id ) {
			$cache->save_post( $post_id );
		}

		( new WeighWordsTask( 0 ) )->run_batch();
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
		return array_column( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT word, tf FROM ' . Table::name() . ' WHERE post_id = %d', $post_id ), ARRAY_A ), null, 'word' );
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
