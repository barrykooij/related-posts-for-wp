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
 * Links with the same menu_order come back in the order they were added, also with a limit (known issue K1), while
 * the 2.x query filters keep getting the 2.x arguments.
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

		$links = new LinkRepository();
		foreach ( $this->children as $child ) {
			$links->add( $this->parent, $child );
		}
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

	public function test_menu_order_still_comes_first(): void {
		global $wpdb;

		$links = array_keys( ( new LinkRepository() )->get_children( $this->parent ) );
		$wpdb->update( $wpdb->posts, [ 'menu_order' => -1 ], [ 'ID' => $links[3] ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Same write as the sort handler.
		clean_post_cache( $links[3] );

		$this->assertSame( $this->children[3], $this->child_ids( ( new LinkRepository() )->get_children( $this->parent ) )[0] );
	}

	public function test_filters_get_the_2x_arguments_and_can_still_set_the_order(): void {
		$seen = null;
		add_filter(
			'rp4wp_get_children_link_args',
			static function ( $args ) use ( &$seen ) {
				$seen          = $args;
				$args['order'] = 'DESC';

				return $args;
			}
		);

		$children = $this->child_ids( ( new LinkRepository() )->get_children( $this->parent ) );

		$this->assertSame( 'menu_order', $seen['orderby'] );
		$this->assertSame( 'ASC', $seen['order'] );
		$this->assertSame( array_reverse( $this->children ), $children );
	}

	public function test_a_filter_can_replace_the_order_completely(): void {
		add_filter(
			'rp4wp_get_children_link_args',
			static function ( $args ) {
				$args['orderby'] = 'ID';
				$args['order']   = 'DESC';

				return $args;
			}
		);

		$this->assertSame( array_reverse( $this->children ), $this->child_ids( ( new LinkRepository() )->get_children( $this->parent ) ) );
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
