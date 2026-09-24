<?php
/**
 * The uninstall test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Install;

use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

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
	 * Queries sent while uninstall.php ran.
	 *
	 * @var string[]
	 */
	private array $queries = [];

	public function set_up(): void {
		parent::set_up();

		$this->parent = self::factory()->post->create();
		$this->link   = ( new \RP4WP_Post_Link_Manager() )->add( $this->parent, self::factory()->post->create() );
		( new \RP4WP_Related_Word_Manager() )->save_words_of_post( $this->parent );

		update_post_meta( $this->parent, 'rp4wp_auto_linked', 1 );
		update_post_meta( $this->parent, 'rp4wp_cached', 1 );
		update_option( 'rp4wp_do_install', 1 );
		update_option( 'rp4wp_install_date', '2024-01-01' );
		update_option( 'rp4wp_hide_nag', 1 );
	}

	public function test_keeps_everything_when_cleaning_is_off(): void {
		update_option( 'rp4wp', [ 'clean_on_uninstall' => 0 ] );

		$this->uninstall();

		$this->assertInstanceOf( \WP_Post::class, get_post( $this->link ) );
		$this->assertSame( '1', get_post_meta( $this->parent, 'rp4wp_auto_linked', true ) );
		$this->assertNotFalse( get_option( 'rp4wp' ) );
		$this->assertNotFalse( get_option( 'rp4wp_install_date' ) );
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
		foreach ( [ 'rp4wp', 'rp4wp_do_install', 'rp4wp_install_date', 'rp4wp_hide_nag' ] as $option ) {
			$this->assertFalse( get_option( $option ), "Option {$option} should be deleted." );
		}

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
