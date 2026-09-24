<?php
/**
 * The AJAX permissions test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Security;

use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;

/**
 * Regression tests for the 2.3.1 security fixes in the AJAX handlers.
 *
 * The legacy handlers end with a bare exit() once they have done their work, which would stop PHPUnit. The tests
 * therefore only cover paths that return or wp_die() before that point, and use a sentinel link that throws just
 * before the exit where they need to look at the result of a successful loop. The E2E suite covers the full flows.
 *
 * @group ajax
 * @covers \LV2\WordPress\RelatedPostsForWP\Admin\MetaBox\Ajax
 * @covers \LV2\WordPress\RelatedPostsForWP\Admin\Wizard\Ajax
 */
final class AjaxPermissionsTest extends \WP_Ajax_UnitTestCase {

	/**
	 * A post owned by an administrator.
	 *
	 * @var int
	 */
	private int $admin_post;

	/**
	 * A link on the administrator's post.
	 *
	 * @var int
	 */
	private int $admin_link;

	public function set_up(): void {
		parent::set_up();

		$admin            = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->admin_post = self::factory()->post->create( [ 'post_author' => $admin ] );
		$this->admin_link = ( new LinkRepository() )->add( $this->admin_post, self::factory()->post->create() );
	}

	public function test_contributor_cannot_delete_a_link_on_someone_elses_post(): void {
		$this->act_as( 'contributor' );

		$_POST['id']    = $this->admin_link;
		$_POST['nonce'] = wp_create_nonce( 'rp4wp-ajax-nonce-omgrandomword' );

		$this->_handleAjax( 'rp4wp_delete_link' );

		$this->assertInstanceOf( \WP_Post::class, get_post( $this->admin_link ) );
	}

	public function test_delete_ignores_posts_that_are_not_links(): void {
		$this->act_as( 'administrator' );

		$_POST['id']    = $this->admin_post;
		$_POST['nonce'] = wp_create_nonce( 'rp4wp-ajax-nonce-omgrandomword' );

		$this->_handleAjax( 'rp4wp_delete_link' );

		$this->assertInstanceOf( \WP_Post::class, get_post( $this->admin_post ) );
	}

	public function test_delete_ignores_ids_that_do_not_exist(): void {
		$this->act_as( 'administrator' );

		$_POST['id']    = PHP_INT_MAX;
		$_POST['nonce'] = wp_create_nonce( 'rp4wp-ajax-nonce-omgrandomword' );

		$this->_handleAjax( 'rp4wp_delete_link' );

		$this->assertEmpty( $this->_last_response );
	}

	public function test_admin_can_delete_a_link_and_gets_the_2x_response(): void {
		$this->act_as( 'administrator' );

		$_POST['id']    = $this->admin_link;
		$_POST['nonce'] = wp_create_nonce( 'rp4wp-ajax-nonce-omgrandomword' );

		try {
			$this->_handleAjax( 'rp4wp_delete_link' );
		} catch ( \WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$this->assertSame( '{"success":true}', $this->_last_response );
		$this->assertNull( get_post( $this->admin_link ) );
	}

	public function test_admin_sort_gets_the_2x_response(): void {
		$this->act_as( 'administrator' );

		$_POST['rp4wp_items'] = (string) $this->admin_link;
		$_POST['nonce']       = wp_create_nonce( 'rp4wp-ajax-nonce-omgrandomword' );

		try {
			$this->_handleAjax( 'rp4wp_related_sort' );
		} catch ( \WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$this->assertSame( '{"success":true}', $this->_last_response );
	}

	public function test_contributor_cannot_reorder_normal_posts_or_other_users_links(): void {
		$this->set_menu_order( [ $this->admin_post, $this->admin_link ], 7 );
		$sentinel = $this->sentinel_link();

		$this->act_as( 'contributor' );
		$_POST['rp4wp_items'] = implode( ',', [ $this->admin_post, $this->admin_link, $sentinel ] );
		$_POST['nonce']       = wp_create_nonce( 'rp4wp-ajax-nonce-omgrandomword' );

		$this->handle_until_sentinel( 'rp4wp_related_sort' );

		$this->assertSame( 7, $this->get_menu_order( $this->admin_post ) );
		$this->assertSame( 7, $this->get_menu_order( $this->admin_link ) );
	}

	public function test_admin_can_reorder_links(): void {
		$second = ( new LinkRepository() )->add( $this->admin_post, self::factory()->post->create() );
		$this->set_menu_order( [ $this->admin_link, $second ], 7 );
		$sentinel = $this->sentinel_link();

		$this->act_as( 'administrator' );
		$_POST['rp4wp_items'] = implode( ',', [ $second, $this->admin_link, $sentinel ] );
		$_POST['nonce']       = wp_create_nonce( 'rp4wp-ajax-nonce-omgrandomword' );

		$this->handle_until_sentinel( 'rp4wp_related_sort' );

		$this->assertSame( 0, $this->get_menu_order( $second ) );
		$this->assertSame( 1, $this->get_menu_order( $this->admin_link ) );
	}

	/**
	 * The install handlers relink the whole site and change settings.
	 *
	 * @dataProvider install_actions
	 *
	 * @param string $action The AJAX action.
	 */
	public function test_contributor_cannot_run_the_install_wizard( string $action ): void {
		$this->act_as( 'contributor' );

		$_POST['nonce']      = wp_create_nonce( 'rp4wp-ajax-nonce-omgrandomword' );
		$_POST['ppr']        = 1;
		$_POST['rel_amount'] = 3;

		try {
			$this->_handleAjax( $action );
			$this->fail( 'Expected wp_die() for a contributor.' );
		} catch ( \WPAjaxDieStopException $e ) {
			$this->assertStringContainsString( 'not allowed to run the installation wizard', $e->getMessage() );
		}
	}

	public function test_admin_wizard_batches_answer_with_the_posts_left(): void {
		$this->act_as( 'administrator' );
		delete_post_meta_by_key( 'rp4wp_auto_linked' );

		$_POST['nonce'] = wp_create_nonce( 'rp4wp-ajax-nonce-omgrandomword' );
		$_POST['ppr']   = 1000;

		$this->assertSame( '0', $this->handle_expecting_die( 'rp4wp_install_save_words' ) );

		$_POST['rel_amount'] = 2;
		$this->assertSame( '0', $this->handle_expecting_die( 'rp4wp_install_link_posts' ) );

		// With everything linked, the chosen amount becomes the setting.
		$this->assertSame( 2, get_option( 'rp4wp' )['automatic_linking_post_amount'] );
	}

	/**
	 * Run an AJAX action that ends with wp_die( $message ), and return the message.
	 *
	 * @param string $action The AJAX action.
	 *
	 * @return string
	 */
	private function handle_expecting_die( string $action ): string {
		try {
			$this->_handleAjax( $action );
		} catch ( \WPAjaxDieStopException $e ) {
			return $e->getMessage();
		}

		$this->fail( "{$action} did not end with wp_die()." );
	}

	/**
	 * The install AJAX actions.
	 *
	 * @return array<string, array{string}>
	 */
	public static function install_actions(): array {
		return [
			'save words' => [ 'rp4wp_install_save_words' ],
			'link posts' => [ 'rp4wp_install_link_posts' ],
		];
	}

	/**
	 * Create a user with the given role and make them the current user.
	 *
	 * @param string $role The role.
	 *
	 * @return int The user ID.
	 */
	private function act_as( string $role ): int {
		$user_id = self::factory()->user->create( [ 'role' => $role ] );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Set menu_order directly, like the sort handler does.
	 *
	 * @param int[] $post_ids The posts.
	 * @param int   $order    The order value.
	 *
	 * @return void
	 */
	private function set_menu_order( array $post_ids, int $order ): void {
		global $wpdb;

		foreach ( $post_ids as $post_id ) {
			$wpdb->update( $wpdb->posts, [ 'menu_order' => $order ], [ 'ID' => $post_id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test setup.
			clean_post_cache( $post_id );
		}
	}

	/**
	 * Run an AJAX action that is expected to hit the sentinel link, and close the output buffer _handleAjax() opened.
	 *
	 * @param string $action The AJAX action.
	 *
	 * @return void
	 */
	private function handle_until_sentinel( string $action ): void {
		$level = ob_get_level();

		try {
			$this->_handleAjax( $action );
			$this->fail( 'The sentinel link should have stopped the handler before exit().' );
		} catch ( SentinelReachedException $e ) {
			unset( $e );
		}

		while ( ob_get_level() > $level ) {
			ob_end_clean();
		}
	}

	/**
	 * Read menu_order from the database. The handler writes with $wpdb and does not clear the post cache.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return int
	 */
	private function get_menu_order( int $post_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT menu_order FROM {$wpdb->posts} WHERE ID = %d", $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reading past the stale object cache.
	}

	/**
	 * A link whose parent lookup throws, so the sort loop stops right before the handler's exit().
	 *
	 * @return int The sentinel link ID.
	 */
	private function sentinel_link(): int {
		$sentinel = ( new LinkRepository() )->add( $this->admin_post, self::factory()->post->create() );

		add_filter(
			'get_post_metadata',
			static function ( $value, $object_id, $meta_key ) use ( $sentinel ) {
				if ( $sentinel === (int) $object_id && 'rp4wp_parent' === $meta_key ) {
					throw new SentinelReachedException();
				}

				return $value;
			},
			10,
			3
		);

		return $sentinel;
	}
}
