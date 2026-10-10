<?php
/**
 * The link order test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Links;

use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * Links with the same position come back in the order they were added, also with a limit (known issue K1); the links
 * of 2.x all have position 0. `rp4wp_links_query` can change which links are read.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Links\LinkRepository
 */
final class LinkOrderTest extends TestCase {

	/**
	 * The parent post.
	 *
	 * @var int
	 */
	private int $parent;

	/**
	 * The children, in the order they were linked.
	 *
	 * @var int[]
	 */
	private array $children;

	public function set_up(): void {
		parent::set_up();

		$this->parent   = self::factory()->post->create();
		$this->children = self::factory()->post->create_many( 4 );

		// All at position 0, like the links the 2.x wizard wrote.
		( new LinkRepository() )->insert( $this->parent, array_fill_keys( $this->children, 0 ) );
	}

	public function test_ties_keep_the_order_the_links_were_added_in(): void {
		$this->assertSame( $this->children, $this->child_ids( ( new LinkRepository() )->get_children( $this->parent ) ) );
	}

	public function test_a_limit_and_offset_pick_from_that_order(): void {
		$repository = new LinkRepository();

		$this->assertSame( [ $this->children[0] ], $this->child_ids( $repository->get_children( $this->parent, [ 'posts_per_page' => 1 ] ) ) );
		$this->assertSame(
			[ $this->children[1], $this->children[2] ],
			$this->child_ids(
				$repository->get_children(
					$this->parent,
					[
						'posts_per_page' => 2,
						'offset' => 1,
					]
				)
			)
		);
	}

	public function test_order_desc_reverses_it(): void {
		$this->assertSame( array_reverse( $this->children ), $this->child_ids( ( new LinkRepository() )->get_children( $this->parent, [ 'order' => 'DESC' ] ) ) );
	}

	public function test_the_position_comes_first(): void {
		$repository = new LinkRepository();
		$links      = array_keys( $repository->get_children( $this->parent ) );

		$repository->set_positions( array_fill_keys( array_slice( $links, 0, 3 ), 1 ) );

		$this->assertSame( $this->children[3], $this->child_ids( $repository->get_children( $this->parent ) )[0] );
	}

	public function test_an_offset_without_a_limit_is_ignored_like_in_2x(): void {
		$this->assertSame( $this->children, $this->child_ids( ( new LinkRepository() )->get_children( $this->parent, [ 'offset' => 2 ] ) ) );
	}

	public function test_the_links_query_filter_gets_the_query_and_can_set_the_order(): void {
		$seen = null;
		add_filter(
			'rp4wp_links_query',
			static function ( $query, $post_id, $direction ) use ( &$seen ) {
				$seen           = [ $query, $post_id, $direction ];
				$query['order'] = 'DESC';

				return $query;
			},
			10,
			3
		);

		$children = $this->child_ids( ( new LinkRepository() )->get_children( $this->parent ) );

		$this->assertSame(
			[
				[
					'limit'  => -1,
					'offset' => 0,
					'order'  => 'ASC',
				],
				$this->parent,
				'children',
			],
			$seen
		);
		$this->assertSame( array_reverse( $this->children ), $children );
	}

	public function test_the_links_query_filter_can_limit_the_links(): void {
		add_filter(
			'rp4wp_links_query',
			static function ( $query ) {
				$query['limit']  = 2;
				$query['offset'] = 1;

				return $query;
			}
		);

		$this->assertSame( [ $this->children[1], $this->children[2] ], $this->child_ids( ( new LinkRepository() )->get_children( $this->parent ) ) );
	}

	/**
	 * The post IDs of children.
	 *
	 * @param array<int, \WP_Post|null> $children The children.
	 *
	 * @return int[]
	 */
	private function child_ids( array $children ): array {
		return array_values(
			array_map(
				static function ( $child ) {
					return (int) $child->ID;
				},
				$children
			)
		);
	}
}
