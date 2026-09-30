<?php
/**
 * The uninstall test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Install;

use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;

/**
 * What uninstall.php removes, and that it removes nothing unless "Remove data on uninstall" is on.
 *
 * The WordPress test suite rewrites DROP TABLE into DROP TEMPORARY TABLE, which leaves the real cache table alone,
 * so the drop is checked through the query that uninstall.php sends.
 *
 * @coversNothing
 */
final class UninstallTest extends TestCase {

	/**
	 * A content post that is linked to another.
	 *
	 * @var int
	 */
	private int $parent;

	/**
	 * The link post.
	 *
	 * @var int
	 */
	private int $link;

	/**
	 * A user who dismissed the review notice and chose the links per page.
	 *
	 * @var int
	 */
	private int $user;

	/**
	 * Queries sent while uninstall.php ran.
	 *
	 * @var string[]
	 */
	private array $queries = [];

	public function set_up(): void {
		parent::set_up();

		$this->parent = self::factory()->post->create();
		$this->link   = ( new LinkRepository() )->add( $this->parent, self::factory()->post->create() );
		( new Cache() )->save_post( $this->parent );

		update_post_meta( $this->parent, 'rp4wp_auto_linked', 1 );
		update_post_meta( $this->parent, 'rp4wp_cached', 1 );
		update_option( 'rp4wp_do_install', 1 );
		update_option( 'rp4wp_is_installing', 1 );
		update_option( 'rp4wp_install_date', '2024-01-01' );
		update_option( 'rp4wp_hide_nag', 1 );
		update_option( 'widget_rp4wp_related_posts_widget', [ 2 => [ 'title' => 'Related' ] ] );
		update_option( 'rp4wp_install_job', [ 'id' => 'job' ], false );
		update_option( 'rp4wp_install_lock', time() + 60, false );

		// A background action of the installer, and one of another plugin.
		as_enqueue_async_action( 'rp4wp_install_run', [ 'job' ], 'rp4wp' );
		as_enqueue_async_action( 'another_plugin_action', [], 'another-plugin' );

		$this->user = self::factory()->user->create();
		add_user_meta( $this->user, 'rp4wp_hide_nag', '1', true );
		update_user_meta( $this->user, 'rp4wp_per_page', 50 );
	}

	public function test_keeps_everything_when_cleaning_is_off(): void {
		update_option( 'rp4wp', [ 'clean_on_uninstall' => 0 ] );

		$this->uninstall();

		$this->assertInstanceOf( \WP_Post::class, get_post( $this->link ) );
		$this->assertSame( '1', get_post_meta( $this->parent, 'rp4wp_auto_linked', true ) );
		$this->assertNotFalse( get_option( 'rp4wp' ) );
		$this->assertNotFalse( get_option( 'rp4wp_install_date' ) );
		$this->assertNotFalse( get_option( 'rp4wp_is_installing' ) );
		$this->assertNotFalse( get_option( 'widget_rp4wp_related_posts_widget' ) );
		$this->assertSame( '1', get_user_meta( $this->user, 'rp4wp_hide_nag', true ) );
		$this->assertNotFalse( get_option( 'rp4wp_install_job' ) );
		$this->assertCount( 1, as_get_scheduled_actions( [ 'group' => 'rp4wp' ], 'ids' ) );
		$this->assertSame( [], $this->drop_queries() );
	}

	public function test_removes_the_plugin_data_when_cleaning_is_on(): void {
		update_option( 'rp4wp', [ 'clean_on_uninstall' => 1 ] );

		$this->uninstall();

		// Link posts and their meta.
		clean_post_cache( $this->link );
		$this->assertNull( get_post( $this->link ) );
		$this->assertSame( [], get_post_meta( $this->link ) );

		// Plugin meta on content posts; the content itself stays.
		$this->assertInstanceOf( \WP_Post::class, get_post( $this->parent ) );
		$this->assertSame( '', get_post_meta( $this->parent, 'rp4wp_auto_linked', true ) );
		$this->assertSame( '', get_post_meta( $this->parent, 'rp4wp_cached', true ) );

		// Options.
		foreach ( [ 'rp4wp', 'rp4wp_do_install', 'rp4wp_is_installing', 'rp4wp_install_date', 'rp4wp_hide_nag', 'widget_rp4wp_related_posts_widget', 'rp4wp_install_job', 'rp4wp_install_lock' ] as $option ) {
			$this->assertFalse( get_option( $option ), "Option {$option} should be deleted." );
		}

		// What users chose.
		$this->assertSame( '', get_user_meta( $this->user, 'rp4wp_hide_nag', true ) );
		$this->assertSame( '', get_user_meta( $this->user, 'rp4wp_per_page', true ) );

		// The actions of the background installer; the actions of other plugins stay.
		$this->assertSame( [], as_get_scheduled_actions( [ 'group' => 'rp4wp' ], 'ids' ) );
		$this->assertCount( 1, as_get_scheduled_actions( [ 'group' => 'another-plugin' ], 'ids' ) );

		// The word cache table.
		$this->assertCount( 1, $this->drop_queries() );
		$this->assertStringContainsString( 'rp4wp_cache', $this->drop_queries()[0] );
	}

	/**
	 * Run uninstall.php the way WordPress does, recording the queries it sends.
	 *
	 * @return void
	 */
	private function uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'related-posts-for-wp/related-posts-for-wp.php' );
		}

		// Record every query. Keep DROP TABLE from running: the test suite would turn it into DROP TEMPORARY TABLE,
		// which fails on the real table, and later tests need the table.
		$record = function ( $query ) {
			$this->queries[] = $query;

			return 0 === stripos( ltrim( $query ), 'DROP' ) ? 'SELECT 1' : $query;
		};

		add_filter( 'query', $record, 1 );
		include dirname( __DIR__, 3 ) . '/uninstall.php';
		remove_filter( 'query', $record, 1 );

		wp_cache_flush();
	}

	/**
	 * The DROP TABLE queries that uninstall.php sent.
	 *
	 * @return string[]
	 */
	private function drop_queries(): array {
		return array_values(
			array_filter(
				$this->queries,
				static function ( $query ) {
					return 0 === stripos( ltrim( $query ), 'DROP' );
				}
			)
		);
	}
}
