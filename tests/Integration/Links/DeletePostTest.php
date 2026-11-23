<?php
/**
 * The delete post test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Links;

use LV2\WordPress\RelatedPostsForWP\Database\Migrator;
use LV2\WordPress\RelatedPostsForWP\Database\Schema;
use LV2\WordPress\RelatedPostsForWP\Install\Update;
use LV2\WordPress\RelatedPostsForWP\Links\Deferred;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Links\PostLifecycle;
use LV2\WordPress\RelatedPostsForWP\Related\Linker;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * A post that is deleted loses every link to and from it, and the posts that showed it get a new related post (known
 * issue K2: 2.x and 3.0 kept the links, which then pointed at nothing). A migration removes the links that already
 * point at nothing.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Links\PostLifecycle::delete_links
 * @covers \LV2\WordPress\RelatedPostsForWP\Links\PostLifecycle::run_deferred
 * @covers \LV2\WordPress\RelatedPostsForWP\Links\Deferred
 */
final class DeletePostTest extends TestCase {

	/**
	 * The links.
	 *
	 * @var LinkRepository
	 */
	private LinkRepository $links;

	public function set_up(): void {
		parent::set_up();

		$this->links = new LinkRepository();
		delete_option( Update::OPTION );
		Deferred::take();
		update_option(
			'rp4wp',
			[
				'automatic_linking'             => 1,
				'automatic_linking_post_amount' => 2,
			]
		);
	}

	public function tear_down(): void {
		delete_option( 'rp4wp' );
		delete_option( Update::OPTION );
		Deferred::take();
		Schema::set_storage( Schema::STORAGE_LINKS );

		parent::tear_down();
	}

	public function test_every_link_to_and_from_it_goes_and_the_posts_that_showed_it_are_full_again(): void {
		$this->post( 'Espresso at home with a moka pot' );
		$this->post( 'Espresso beans and the grind' );
		$this->post( 'Espresso and milk for a flat white' );
		$parent = $this->post( 'Espresso basics for beginners' );
		( new Linker() )->relink_post( $parent, 2 );
		$deleted = array_values( $this->links->child_ids( $parent ) )[0];
		$this->links->add( $deleted, $parent );

		$fired = [];
		foreach ( [ 'rp4wp_before_link_delete', 'rp4wp_after_link_delete' ] as $hook ) {
			add_action(
				$hook,
				static function ( $link_id ) use ( &$fired, $hook ) {
					$fired[ $hook ][] = (int) $link_id;
				}
			);
		}
		$expected = $this->link_count( $deleted );

		wp_delete_post( $deleted, true );

		$this->assertSame( 0, $this->link_count( $deleted ) );
		$this->assertCount( $expected, $fired['rp4wp_before_link_delete'] ?? [], 'The hooks fire once per link.' );
		$this->assertCount( $expected, $fired['rp4wp_after_link_delete'] ?? [] );
		$this->assertCount( 2, $this->links->child_ids( $parent ), 'The post shows as many related posts as before.' );
		$this->assertNotContains( $deleted, $this->links->child_ids( $parent ) );
	}

	public function test_a_post_deleted_while_the_links_move_loses_its_links_afterwards(): void {
		$parent = $this->post( 'Hiking boots for the mountains' );
		$child  = $this->post( 'Breaking in new hiking boots' );
		$this->links->add( $parent, $child );

		Schema::set_storage( Schema::STORAGE_POST_STATE );
		wp_delete_post( $child, true );

		$this->assertTrue( Deferred::has() );

		Schema::set_storage( Schema::STORAGE_LINKS );
		PostLifecycle::run_deferred();

		$this->assertSame( 0, $this->link_count( $child ) );
		$this->assertFalse( Deferred::has() );
	}

	public function test_the_migration_removes_the_links_that_point_at_nothing_and_relinks_their_posts(): void {
		$parent = $this->post( 'Mountain huts on long hikes' );
		$kept   = $this->post( 'Booking a mountain hut' );
		$this->links->add( $parent, $kept );
		$gone = (int) self::factory()->post->create();
		$this->links->add( $parent, $gone );
		$this->links->add( $gone, $kept );

		// Deleted the way 2.x and 3.0 left it: the post is gone, its links are not.
		remove_action( 'delete_post', [ PostLifecycle::class, 'delete_links' ], 11 );
		wp_delete_post( $gone, true );
		add_action( 'delete_post', [ PostLifecycle::class, 'delete_links' ], 11, 1 );
		$this->assertSame( 2, $this->link_count( $gone ) );

		$migration = ( new Migrator( 'free', dirname( __DIR__, 3 ) . '/migrations' ) )->migration( '2026_11_23_000001_remove_links_to_deleted_posts' );
		$migration->set_deadline( 0.0 );
		$this->assertTrue( $migration->up() );

		$this->assertSame( 0, $this->link_count( $gone ) );
		$this->assertSame( [ $kept ], array_values( $this->links->child_ids( $parent ) ) );
		$this->assertSame(
			[
				'pending'  => true,
				'scope'    => 'relink',
				'versions' => [ '3.1.0' ],
				'post_ids' => [ $parent ],
				'notice'   => false,
			],
			get_option( Update::OPTION ),
			'The post that lost a related post is relinked, without a notice.'
		);
	}

	/**
	 * A published post with a title and the same text, so posts with similar titles are related.
	 *
	 * @param string $title The title.
	 *
	 * @return int
	 */
	private function post( string $title ): int {
		return self::factory()->post->create(
			[
				'post_title'   => $title,
				'post_content' => str_repeat( $title . '. ', 3 ),
				'post_status'  => 'publish',
			]
		);
	}

	/**
	 * How many links go to or from a post, in the links table.
	 *
	 * @param int $post_id The post.
	 *
	 * @return int
	 */
	private function link_count( int $post_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The plugin's own table.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE parent_id = %d OR child_id = %d', Schema::table( Schema::LINKS ), $post_id, $post_id ) );
	}
}
