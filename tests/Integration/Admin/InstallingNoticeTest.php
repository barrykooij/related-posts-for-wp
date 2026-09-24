<?php
/**
 * The installing notice test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Admin;

use LV2\WordPress\RelatedPostsForWP\Admin\Notices\Installing;
use LV2\WordPress\RelatedPostsForWP\Admin\Wizard\Page;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * The links of the notice that offers to resume the installation wizard. Who sees it is covered by AdminAccessTest.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Admin\Notices\Installing
 */
final class InstallingNoticeTest extends TestCase {

	/**
	 * The request URI before the test.
	 *
	 * @var string|null
	 */
	private ?string $request_uri = null;

	public function set_up(): void {
		parent::set_up();

		$this->request_uri = $_SERVER['REQUEST_URI'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Only saved to restore it.
	}

	public function tear_down(): void {
		$_SERVER['REQUEST_URI'] = $this->request_uri;

		parent::tear_down();
	}

	public function test_the_links_resume_the_wizard_or_dismiss_the_notice_on_the_current_page(): void {
		$this->act_as( 'administrator' );
		$_SERVER['REQUEST_URI'] = '/wp-admin/edit.php?post_type=page&s=two%20words';

		ob_start();
		Installing::display();
		$html = (string) ob_get_clean();

		$this->assertSame( 2, preg_match_all( '/<a href="([^"]+)">/', $html, $matches ) );
		[ $resume, $dismiss ] = array_map( 'html_entity_decode', $matches[1] );

		// The other query arguments of the page are kept as they were.
		$this->assertSame( '/wp-admin/edit.php?post_type=page&s=two+words&rp4wp_hide_is_installing=1', $dismiss );

		parse_str( (string) wp_parse_url( $resume, PHP_URL_QUERY ), $query );
		$this->assertSame( 'two words', $query['s'] );
		$this->assertSame( Page::SLUG, $query['page'] );
		$this->assertSame( 1, wp_verify_nonce( $query['rp4wp_nonce'], Page::NONCE ) );
	}
}
