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

		$this->assertNotNull( ( new LinkRepository() )->find( $this->admin_link ) );
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
		$this->assertNull( ( new LinkRepository() )->find( $this->admin_link ) );
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
		$links = new LinkRepository();
		$links->set_positions( [ $this->admin_link => 7 ] );

		$this->act_as( 'contributor' );
		$_POST['rp4wp_items'] = implode( ',', [ $this->admin_post, $this->admin_link ] );
		$_POST['nonce']       = wp_create_nonce( 'rp4wp-ajax-nonce-omgrandomword' );

		$this->handle( 'rp4wp_related_sort' );

		$this->assertSame( 7, $links->find( $this->admin_link )['position'] );
	}

	public function test_admin_can_reorder_links(): void {
		$links  = new LinkRepository();
		$second = $links->add( $this->admin_post, self::factory()->post->create() );
		$links->set_positions(
			[
				$this->admin_link => 7,
				$second           => 7,
			]
		);

		$this->act_as( 'administrator' );
		$_POST['rp4wp_items'] = implode( ',', [ $second, $this->admin_link ] );
		$_POST['nonce']       = wp_create_nonce( 'rp4wp-ajax-nonce-omgrandomword' );

		$this->handle( 'rp4wp_related_sort' );

		$this->assertSame( 0, $links->find( $second )['position'] );
		$this->assertSame( 1, $links->find( $this->admin_link )['position'] );
	}

	/**
	 * The AJAX actions of the 2.x installation wizard are gone (decision D27): the installer runs in the background
	 * and is started through the REST API, for administrators only.
	 *
	 * @dataProvider install_actions
	 *
	 * @param string $action The AJAX action.
	 */
	public function test_the_install_wizard_actions_are_gone( string $action ): void {
		$this->act_as( 'administrator' );

		$this->assertFalse( has_action( 'wp_ajax_' . $action ) );
		$this->assertFalse( has_action( 'wp_ajax_nopriv_' . $action ) );
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
	 * Run an AJAX action to its end: wp_send_json() dies, which the test suite turns into an exception.
	 *
	 * @param string $action The AJAX action.
	 *
	 * @return void
	 */
	private function handle( string $action ): void {
		try {
			$this->_handleAjax( $action );
		} catch ( \WPAjaxDieContinueException $e ) {
			unset( $e );
		}
	}
}
