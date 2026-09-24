<?php
/**
 * The review notice test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Admin;

use LV2\WordPress\RelatedPostsForWP\Admin\Notices\Review;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\Security\RedirectException;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * The notice that asks for a review on WordPress.org.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Admin\Notices\Review
 */
final class ReviewNoticeTest extends TestCase {

	/**
	 * The request URI before the test.
	 *
	 * @var string|null
	 */
	private ?string $request_uri = null;

	public function set_up(): void {
		parent::set_up();

		$this->request_uri = $_SERVER['REQUEST_URI'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Only saved to restore it.
		set_current_screen( 'dashboard' );
	}

	public function tear_down(): void {
		$_SERVER['REQUEST_URI'] = $this->request_uri;
		unset( $_GET[ Review::DISMISS_KEY ] );
		delete_option( Review::OPTION_INSTALL_DATE );

		parent::tear_down();
	}

	public function test_shows_from_ten_days_after_installation(): void {
		$this->act_as( 'administrator' );
		$this->installed( '-10 days' );

		Review::setup();

		$this->assertSame( 10, has_action( 'admin_notices', [ Review::class, 'display' ] ) );
	}

	public function test_does_not_show_sooner(): void {
		$this->act_as( 'administrator' );
		$this->installed( '-9 days' );

		Review::setup();

		$this->assertFalse( has_action( 'admin_notices', [ Review::class, 'display' ] ) );
	}

	public function test_sites_without_an_installation_date_start_counting_on_the_first_visit(): void {
		$this->act_as( 'administrator' );

		Review::setup();

		$this->assertSame( gmdate( 'Y-m-d' ), get_option( Review::OPTION_INSTALL_DATE ) );
		$this->assertFalse( has_action( 'admin_notices', [ Review::class, 'display' ] ) );
	}

	public function test_an_unreadable_installation_date_counts_from_today(): void {
		$this->act_as( 'administrator' );
		update_option( Review::OPTION_INSTALL_DATE, 'not a date' );

		Review::setup();

		$this->assertFalse( has_action( 'admin_notices', [ Review::class, 'display' ] ) );
	}

	public function test_is_not_shown_to_users_who_cannot_install_plugins(): void {
		$this->act_as( 'editor' );
		$this->installed( '-30 days' );

		Review::setup();

		$this->assertFalse( has_action( 'admin_notices', [ Review::class, 'display' ] ) );
	}

	public function test_is_not_shown_outside_the_admin(): void {
		set_current_screen( 'front' );
		$this->act_as( 'administrator' );
		$this->installed( '-30 days' );

		Review::setup();

		$this->assertFalse( has_action( 'admin_notices', [ Review::class, 'display' ] ) );
	}

	public function test_is_not_shown_once_the_user_dismissed_it(): void {
		$user = $this->act_as( 'administrator' );
		$this->installed( '-30 days' );
		add_user_meta( $user, Review::DISMISS_KEY, '1', true );

		Review::setup();

		$this->assertFalse( has_action( 'admin_notices', [ Review::class, 'display' ] ) );
	}

	public function test_dismissing_hides_it_for_the_user_and_reloads_the_page_without_the_argument(): void {
		$user                        = $this->act_as( 'administrator' );
		$_SERVER['REQUEST_URI']      = '/wp-admin/edit.php?post_type=page&rp4wp_hide_nag=1';
		$_GET[ Review::DISMISS_KEY ] = '1';
		add_filter( 'wp_redirect', [ self::class, 'throw_on_redirect' ] );

		try {
			Review::setup();
			$this->fail( 'Dismissing the notice did not redirect.' );
		} catch ( RedirectException $e ) {
			$this->assertSame( '/wp-admin/edit.php?post_type=page', $e->getMessage() );
		}

		$this->assertSame( '1', get_user_meta( $user, Review::DISMISS_KEY, true ) );
	}

	public function test_the_notice_links_to_the_reviews_and_dismisses_on_the_current_page(): void {
		$_SERVER['REQUEST_URI'] = '/wp-admin/edit.php?post_type=page&s=two%20words';

		ob_start();
		Review::display();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<a href="http://wordpress.org/support/view/plugin-reviews/related-posts-for-wp" target="_blank">Yes, take me there!</a>', $html );
		$this->assertStringContainsString( '<a href="/wp-admin/edit.php?post_type=page&#038;s=two+words&#038;rp4wp_hide_nag=1">I&#039;ve already done this!</a>', $html );
	}

	// phpcs:disable Squiz.Commenting.FunctionComment.InvalidNoReturn -- A filter callback must declare a return type, but this one always throws.
	/**
	 * The wp_redirect filter callback that stops the dismissal before its exit().
	 *
	 * @param string $location The redirect target.
	 *
	 * @return string Never returns: it always throws.
	 *
	 * @throws RedirectException Always.
	 */
	public static function throw_on_redirect( $location ): string {
		throw new RedirectException( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test-only exception.
	}
	// phpcs:enable

	/**
	 * Set the installation date.
	 *
	 * @param string $when A relative date, like "-10 days".
	 *
	 * @return void
	 */
	private function installed( string $when ): void {
		update_option( Review::OPTION_INSTALL_DATE, gmdate( 'Y-m-d', (int) strtotime( $when ) ) );
	}
}
