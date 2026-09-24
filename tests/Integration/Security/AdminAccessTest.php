<?php
/**
 * The admin access test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Security;

use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * Regression tests for the 2.3.1 changes to the install wizard page and notices.
 *
 * @covers \RP4WP_Hook_Page_Install
 * @covers \RP4WP_Is_Installing_Notice
 * @covers \RP4WP_Multisite_Notice
 */
final class AdminAccessTest extends TestCase {

	public function tear_down(): void {
		remove_all_actions( 'admin_notices' );
		delete_option( 'rp4wp_is_installing' );

		parent::tear_down();
	}

	public function test_install_page_requires_manage_options(): void {
		global $submenu;

		$this->act_as( 'administrator' );
		set_current_screen( 'dashboard' );
		$hook = \RP4WP_Manager_Hook::get_hook_object( 'RP4WP_Hook_Page_Install' );
		$this->assertInstanceOf( \RP4WP_Hook_Page_Install::class, $hook );
		$hook->run();

		$install_page = array_values(
			array_filter(
				(array) ( $submenu[''] ?? [] ),
				static function ( $item ) {
					return 'rp4wp_install' === $item[2];
				}
			)
		);

		$this->assertCount( 1, $install_page );
		$this->assertSame( 'manage_options', $install_page[0][1] );
	}

	public function test_installing_notice_is_hidden_from_non_admins(): void {
		update_option( 'rp4wp_is_installing', 'yes' );
		$this->act_as( 'editor' );

		$notice = new \RP4WP_Is_Installing_Notice();
		$notice->check();

		$this->assertFalse( has_action( 'admin_notices', [ $notice, 'content' ] ) );
	}

	public function test_installing_notice_is_shown_to_admins(): void {
		update_option( 'rp4wp_is_installing', 'yes' );
		$this->act_as( 'administrator' );

		$notice = new \RP4WP_Is_Installing_Notice();
		$notice->check();

		$this->assertSame( 10, has_action( 'admin_notices', [ $notice, 'content' ] ) );
	}

	public function test_non_admins_cannot_dismiss_the_installing_notice(): void {
		update_option( 'rp4wp_is_installing', 'yes' );
		$this->act_as( 'editor' );
		$_GET['rp4wp_hide_is_installing'] = 1;

		( new \RP4WP_Is_Installing_Notice() )->check();
		unset( $_GET['rp4wp_hide_is_installing'] );

		$this->assertSame( 'yes', get_option( 'rp4wp_is_installing' ) );
	}

	public function test_multisite_notice_renders_on_php_8(): void {
		ob_start();
		\RP4WP_Multisite_Notice::display();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'upgrade-premium', $output );
		$this->assertStringContainsString( 'Upgrade to the premium version</a>', $output );
	}
}
