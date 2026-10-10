<?php
/**
 * The manual link test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Links;

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
		$links   = new LinkRepository();
		$link_id = $links->add( self::factory()->post->create(), self::factory()->post->create() );

		$this->assertTrue( $links->find( $link_id )['manual'] );
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

		$links    = new LinkRepository();
		$children = $links->get_children( $posts[0] );

		$this->assertCount( 1, $children );
		$this->assertFalse( $links->find( (int) array_key_first( $children ) )['manual'] );
	}

	public function test_a_link_that_exists_is_not_added_twice(): void {
		$links  = new LinkRepository();
		$parent = self::factory()->post->create();
		$child  = self::factory()->post->create();

		$first  = $links->add( $parent, $child );
		$second = $links->add( $parent, $child );

		$this->assertSame( $first, $second );
		$this->assertSame( 1, $links->children_count( $parent ) );
	}

	public function test_a_link_added_by_hand_comes_after_the_links_there_are(): void {
		$links    = new LinkRepository();
		$parent   = self::factory()->post->create();
		$children = self::factory()->post->create_many( 3 );

		$links->insert(
			$parent,
			[
				$children[0] => 0,
				$children[1] => 0,
			]
		);
		$links->add( $parent, $children[2] );

		$this->assertSame( $children, array_values( $links->child_ids( $parent ) ) );
	}
}
