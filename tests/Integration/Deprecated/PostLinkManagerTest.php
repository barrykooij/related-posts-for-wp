<?php
/**
 * The deprecated link manager test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Deprecated;

use LV2\WordPress\RelatedPostsForWP\Frontend\Renderer;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Main;

/**
 * RP4WP_Post_Link_Manager, including the 2.3.1 fixes for SQL injection through add().
 *
 * @covers \RP4WP_Post_Link_Manager
 */
final class PostLinkManagerTest extends ShimTestCase {

	/**
	 * The 2.x link manager.
	 *
	 * @var \RP4WP_Post_Link_Manager
	 */
	private \RP4WP_Post_Link_Manager $manager;

	/**
	 * A parent post.
	 *
	 * @var int
	 */
	private int $parent;

	/**
	 * Two other posts.
	 *
	 * @var int[]
	 */
	private array $children;

	public function set_up(): void {
		parent::set_up();

		$this->parent   = self::factory()->post->create( [ 'post_title' => 'Parent' ] );
		$this->children = self::factory()->post->create_many( 2 );

		$this->expect_deprecated( 'RP4WP_Post_Link_Manager' );
		$this->manager = new \RP4WP_Post_Link_Manager();
	}

	public function test_add_links_a_child_and_returns_the_link(): void {
		$this->expect_deprecated( 'RP4WP_Post_Link_Manager::add' );

		$link_id = $this->manager->add( $this->parent, $this->children[0] );

		$this->assertSame( 'rp4wp_link', get_post_type( $link_id ) );
		$this->assertSame( [ $this->children[0] ], $this->ids( ( new LinkRepository() )->get_children( $this->parent ) ) );
	}

	public function test_add_in_a_batch_returns_the_data_to_insert(): void {
		$this->expect_deprecated( 'RP4WP_Post_Link_Manager::add' );

		$data = $this->manager->add( $this->parent, $this->children[0], true );

		$this->assertIsArray( $data );
		$this->assertSame( [], ( new LinkRepository() )->get_children( $this->parent ) );
	}

	public function test_add_does_not_run_sql_from_the_child_id(): void {
		global $wpdb;

		$this->expect_deprecated( 'RP4WP_Post_Link_Manager::add' );

		$payload = $this->children[0] . "'), (1, 'rp4wp_injected', 'pwned";
		$link_id = $this->manager->add( $this->parent, $payload ); // @phpstan-ignore argument.type (The attack passes a string on purpose.)

		$injected = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'rp4wp_injected'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verifying the raw table.

		$this->assertSame( 0, $injected );
		$this->assertSame( (string) $this->children[0], get_post_meta( $link_id, 'rp4wp_child', true ) );
		$this->assertSame( (string) $this->parent, get_post_meta( $link_id, 'rp4wp_parent', true ) );
	}

	public function test_add_does_not_run_sql_from_the_parent_id(): void {
		global $wpdb;

		$this->expect_deprecated( 'RP4WP_Post_Link_Manager::add' );

		$payload = $this->parent . "'), (1, 'rp4wp_injected', 'pwned";
		$this->manager->add( $payload, $this->children[0] ); // @phpstan-ignore argument.type (The attack passes a string on purpose.)

		$injected = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'rp4wp_injected'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verifying the raw table.

		$this->assertSame( 0, $injected );
	}

	public function test_get_children_and_get_parents_read_the_links(): void {
		$links = new LinkRepository();
		$links->add( $this->parent, $this->children[0] );
		$links->add( $this->parent, $this->children[1] );

		$this->expect_deprecated( 'RP4WP_Post_Link_Manager::get_children', 'RP4WP_Post_Link_Manager::get_parents' );

		$this->assertSame( $this->children, $this->ids( $this->manager->get_children( $this->parent ) ) );
		$this->assertSame( [ $this->children[0] ], $this->ids( $this->manager->get_children( $this->parent, [ 'posts_per_page' => 1 ] ) ) );
		$this->assertSame( [ $this->parent ], $this->ids( $this->manager->get_parents( $this->children[1] ) ) );
	}

	public function test_delete_removes_a_link(): void {
		$link_id = ( new LinkRepository() )->add( $this->parent, $this->children[0] );

		$this->expect_deprecated( 'RP4WP_Post_Link_Manager::delete' );
		$this->manager->delete( $link_id );

		$this->assertNull( get_post( $link_id ) );
	}

	public function test_delete_links_related_to_removes_the_links_from_and_to_a_post(): void {
		$links = new LinkRepository();
		$links->add( $this->parent, $this->children[0] );
		$links->add( $this->children[1], $this->parent );

		$this->expect_deprecated( 'RP4WP_Post_Link_Manager::delete_links_related_to' );
		$this->manager->delete_links_related_to( $this->parent );

		$this->assertSame( [], $links->get_children( $this->parent ) );
		$this->assertSame( [], $links->get_children( $this->children[1] ) );
	}

	public function test_generate_children_list_renders_the_related_posts(): void {
		$links = new LinkRepository();
		$links->add( $this->parent, $this->children[0] );

		$this->expect_deprecated( 'RP4WP_Post_Link_Manager::generate_children_list' );

		$this->assertSame(
			( new Renderer( $links, Main::get()->settings() ) )->related_posts_html( $this->parent ),
			$this->manager->generate_children_list( $this->parent )
		);
	}

	public function test_sort_get_children_children_no_longer_has_an_order_to_sort_by(): void {
		$this->expect_deprecated( 'RP4WP_Post_Link_Manager::sort_get_children_children' );

		$this->assertSame( 0, $this->manager->sort_get_children_children( get_post( $this->children[0] ), get_post( $this->children[1] ) ) );
	}

	/**
	 * The IDs of posts, in order. The link manager keys them by link ID.
	 *
	 * @param array<int|string, \WP_Post|null> $posts The posts.
	 *
	 * @return int[]
	 */
	private function ids( array $posts ): array {
		return array_values( wp_list_pluck( $posts, 'ID' ) );
	}
}
