<?php
/**
 * The deprecated notice classes test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Deprecated;

use LV2\WordPress\RelatedPostsForWP\Admin\Assets;
use LV2\WordPress\RelatedPostsForWP\Admin\Notices\Installing;
use LV2\WordPress\RelatedPostsForWP\Admin\Notices\Mbstring;
use LV2\WordPress\RelatedPostsForWP\Admin\Notices\Multisite;
use LV2\WordPress\RelatedPostsForWP\Admin\Notices\Playground;
use LV2\WordPress\RelatedPostsForWP\Admin\Notices\Review;

/**
 * The 2.x notices and admin helpers.
 *
 * @covers \RP4WP_Dependencies
 * @covers \RP4WP_Is_Installing_Notice
 * @covers \RP4WP_Javascript_Strings
 * @covers \RP4WP_Multisite_Notice
 * @covers \RP4WP_Nag_Manager
 * @covers \RP4WP_Playground
 */
final class NoticesTest extends ShimTestCase {

	public function set_up(): void {
		parent::set_up();

		set_current_screen( 'dashboard' );
		$this->act_as( 'administrator' );
	}

	public function tear_down(): void {
		delete_option( 'rp4wp_is_installing' );
		delete_option( Review::OPTION_INSTALL_DATE );

		parent::tear_down();
	}

	public function test_the_dependency_check_shows_the_mbstring_notice(): void {
		$this->expect_deprecated( 'RP4WP_Dependencies', 'RP4WP_Dependencies::check', 'RP4WP_Dependencies::display_mbstring_error' );

		$dependencies = new \RP4WP_Dependencies();
		$dependencies->check();

		// The test environment has mbstring, so there is no notice.
		$this->assertFalse( has_action( 'admin_notices', [ Mbstring::class, 'display' ] ) );
		$this->assertSame( $this->output( [ Mbstring::class, 'display' ] ), $this->output( [ $dependencies, 'display_mbstring_error' ] ) );
	}

	public function test_the_installing_notice_shows_while_the_wizard_is_not_finished(): void {
		$this->expect_deprecated( 'RP4WP_Is_Installing_Notice', 'RP4WP_Is_Installing_Notice::check', 'RP4WP_Is_Installing_Notice::content' );
		update_option( 'rp4wp_is_installing', '1' );

		$notice = new \RP4WP_Is_Installing_Notice();
		$notice->check();

		$this->assertSame( 10, has_action( 'admin_notices', [ Installing::class, 'display' ] ) );
		$this->assertSame( $this->output( [ Installing::class, 'display' ] ), $this->output( [ $notice, 'content' ] ) );
	}

	public function test_the_javascript_strings_are_those_of_the_admin_scripts(): void {
		$this->expect_deprecated( 'RP4WP_Javascript_Strings::get' );

		$this->assertSame( Assets::javascript_strings(), \RP4WP_Javascript_Strings::get() );
	}

	public function test_the_multisite_notice_is_the_same(): void {
		$this->expect_deprecated( 'RP4WP_Multisite_Notice::display' );

		$this->assertSame( $this->output( [ Multisite::class, 'display' ] ), $this->output( [ \RP4WP_Multisite_Notice::class, 'display' ] ) );
	}

	public function test_the_review_notice_shows_when_it_is_due(): void {
		$this->expect_deprecated( 'RP4WP_Nag_Manager', 'RP4WP_Nag_Manager::setup', 'RP4WP_Nag_Manager::display_admin_notice', 'RP4WP_Nag_Manager::catch_hide_notice' );
		update_option( Review::OPTION_INSTALL_DATE, gmdate( 'Y-m-d', (int) strtotime( '-30 days' ) ) );

		$nag = new \RP4WP_Nag_Manager();
		$nag->setup();
		// Nothing to dismiss: no redirect.
		$nag->catch_hide_notice();

		$this->assertSame( 10, has_action( 'admin_notices', [ Review::class, 'display' ] ) );
		$this->assertSame( $this->output( [ Review::class, 'display' ] ), $this->output( [ $nag, 'display_admin_notice' ] ) );
	}

	public function test_the_playground_helper_detects_and_notifies(): void {
		$this->expect_deprecated( 'RP4WP_Playground::is_playground', 'RP4WP_Playground::add_admin_notice' );

		$this->assertSame( Playground::is_playground(), \RP4WP_Playground::is_playground() );

		\RP4WP_Playground::add_admin_notice();
		$this->assertSame( 10, has_action( 'admin_notices', [ Playground::class, 'display' ] ) );
	}
}
