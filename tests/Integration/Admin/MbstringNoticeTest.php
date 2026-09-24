<?php
/**
 * The mbstring notice test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Admin;

use LV2\WordPress\RelatedPostsForWP\Admin\Notices\Mbstring;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * The notice about the missing mbstring extension.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Admin\Notices\Mbstring
 */
final class MbstringNoticeTest extends TestCase {

	public function test_there_is_no_notice_when_the_extension_is_loaded(): void {
		$this->assertTrue( extension_loaded( 'mbstring' ), 'The test environment needs mbstring.' );

		Mbstring::setup();

		$this->assertFalse( has_action( 'admin_notices', [ Mbstring::class, 'display' ] ) );
	}

	public function test_the_notice_names_the_extension(): void {
		ob_start();
		Mbstring::display();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<div class="notice notice-error">', $html );
		$this->assertStringContainsString( 'The <strong>mbstring</strong> extension needs to be installed and activated for Related Posts for WP to work!', $html );
		$this->assertStringContainsString( 'Please contact your host and ask them to install the <strong>mbstring</strong> PHP extension.', $html );
	}
}
