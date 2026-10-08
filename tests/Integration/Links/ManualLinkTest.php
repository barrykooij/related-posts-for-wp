<?php
/**
 * The manual link test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Links;

use LV2\WordPress\RelatedPostsForWP\Links\LinkPostType;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Related\Linker;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;

/**
 * Links added by hand are marked, so premium's refresh can keep them; automatic links are not.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Links\LinkRepository
 */
final class ManualLinkTest extends TestCase {

	public function test_a_link_added_by_hand_is_marked(): void {
		$link_id = ( new LinkRepository() )->add( self::factory()->post->create(), self::factory()->post->create() );

		$this->assertSame( '1', get_post_meta( $link_id, LinkPostType::META_MANUAL, true ) );
	}

	public function test_automatic_links_are_not_marked(): void {
		$posts = [
			self::factory()->post->create( [ 'post_content' => 'Roses climbing over a garden wall in summer.' ] ),
			self::factory()->post->create( [ 'post_content' => 'Climbing roses for a garden wall.' ] ),
		];

		$cache = new Cache();
		foreach ( $posts as $post_id ) {
			$cache->save_post( $post_id );
		}

		( new Linker() )->link_post( $posts[0], 1 );

		$children = ( new LinkRepository() )->get_children( $posts[0] );

		$this->assertCount( 1, $children );
		$this->assertSame( '', get_post_meta( (int) array_key_first( $children ), LinkPostType::META_MANUAL, true ) );
	}

	public function test_the_batch_data_of_automatic_linking_is_not_marked(): void {
		$data = ( new LinkRepository() )->insert_data( 1, 2 );

		$this->assertStringNotContainsString( LinkPostType::META_MANUAL, implode( ',', $data['meta'] ) );
	}
}
