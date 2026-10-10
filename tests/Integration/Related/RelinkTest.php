<?php
/**
 * The core relink test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Related;

use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Links\PostState;
use LV2\WordPress\RelatedPostsForWP\Related\Finder;
use LV2\WordPress\RelatedPostsForWP\Related\Linker;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * Linking a post again by difference (decision D55): links added by hand keep their place, automatic links that are
 * still right stay, the others are replaced, and a post never ends up with fewer related posts than it had.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Related\Linker
 */
final class RelinkTest extends TestCase {

	/**
	 * The related posts the finder returns, by post.
	 *
	 * @var array<int, int[]>
	 */
	private array $related = [];

	public function test_links_added_by_hand_keep_their_place_and_the_rest_follows_the_finder(): void {
		[ $post, $a, $b, $c, $d ] = self::factory()->post->create_many( 5 );
		$links                    = new LinkRepository();

		$automatic = $links->insert(
			$post,
			[
				$a => 1,
				$b => 2,
			]
		);
		$manual    = $links->link( $post, $c, true );
		$links->set_positions( [ $manual => 0 ] );

		$this->assertSame( [ $c, $a, $b ], array_values( $links->child_ids( $post ) ) );
		$this->assertCount( 2, $automatic );

		$this->related[ $post ] = [ $d, $b ];
		$this->linker()->relink_post( $post, 3 );

		$this->assertSame( [ $c, $d, $b ], array_values( $links->child_ids( $post ) ) );
		$this->assertTrue( $links->find( $manual )['manual'] );
		$this->assertTrue( PostState::is_linked( $post ) );
	}

	public function test_a_post_keeps_its_links_when_nothing_is_found(): void {
		[ $post, $a, $b ] = self::factory()->post->create_many( 3 );
		$links            = new LinkRepository();
		$links->insert(
			$post,
			[
				$a => 0,
				$b => 1,
			]
		);

		$this->related[ $post ] = [];
		$this->linker()->relink_post( $post, 2 );

		$this->assertSame( [ $a, $b ], array_values( $links->child_ids( $post ) ) );
	}

	public function test_the_new_links_are_added_before_the_old_ones_go(): void {
		[ $post, $a, $b ] = self::factory()->post->create_many( 3 );
		$links            = new LinkRepository();
		$links->insert( $post, [ $a => 0 ] );

		$counts = [];
		add_action(
			'rp4wp_before_link_delete',
			static function () use ( $links, $post, &$counts ) {
				$counts[] = $links->children_count( $post );
			}
		);

		$this->related[ $post ] = [ $b ];
		$this->linker()->relink_post( $post, 1 );

		$this->assertSame( [ 2 ], $counts, 'While the old link goes, the new one is there.' );
		$this->assertSame( [ $b ], array_values( $links->child_ids( $post ) ) );
	}

	public function test_relinking_fires_the_relinked_action_with_the_added_and_removed_links(): void {
		[ $post, $a, $b ] = self::factory()->post->create_many( 3 );
		$links            = new LinkRepository();
		$old              = $links->insert( $post, [ $a => 0 ] )[ $a ];

		$seen = null;
		add_action(
			'rp4wp_post_relinked',
			static function ( $post_id, $added, $removed ) use ( &$seen ) {
				$seen = [ $post_id, $added, $removed ];
			},
			10,
			3
		);

		$this->related[ $post ] = [ $b ];
		$this->linker()->relink_post( $post, 1 );

		$this->assertSame( $post, $seen[0] );
		$this->assertSame( array_keys( $links->child_ids( $post ) ), $seen[1] );
		$this->assertSame( [ $old ], $seen[2] );
	}

	/**
	 * A linker whose finder returns the related posts this test sets.
	 *
	 * @return Linker
	 */
	private function linker(): Linker {
		$related = &$this->related;

		$finder = new class( $related ) extends Finder {

			/**
			 * The related posts, by post.
			 *
			 * @var array<int, int[]>
			 */
			private array $related;

			/**
			 * Set up.
			 *
			 * @param array<int, int[]> $related The related posts, by post.
			 */
			public function __construct( array &$related ) {
				$this->related = &$related;
			}

			/**
			 * The related posts this test set, leaving out the given posts.
			 *
			 * @param int   $post_id The post.
			 * @param int   $limit   How many.
			 * @param int[] $exclude Posts to leave out.
			 *
			 * @return object[]
			 */
			public function related_posts( int $post_id, int $limit = -1, array $exclude = [] ): array {
				$ids = array_values( array_diff( $this->related[ $post_id ] ?? [], $exclude ) );

				return array_map(
					static function ( int $id ) {
						return (object) [
							'ID'  => $id,
							'CMS' => 1,
						];
					},
					$limit > 0 ? array_slice( $ids, 0, $limit ) : $ids
				);
			}
		};

		return new Linker( $finder );
	}
}
