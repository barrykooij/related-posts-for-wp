<?php
/**
 * The installing notice test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Admin;

use LV2\WordPress\RelatedPostsForWP\Admin\Notices\Installing;
use LV2\WordPress\RelatedPostsForWP\Admin\Wizard\Page;
use LV2\WordPress\RelatedPostsForWP\Install\Jobs\Job;
use LV2\WordPress\RelatedPostsForWP\Install\Jobs\JobStore;
use LV2\WordPress\RelatedPostsForWP\Install\Jobs\Queue;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * The notice on other admin screens about the background installation. Who sees it is covered by AdminAccessTest.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Admin\Notices\Installing
 */
final class InstallingNoticeTest extends TestCase {

	/**
	 * The request before the test.
	 *
	 * @var array<string, mixed>
	 */
	private array $get = [];

	public function set_up(): void {
		parent::set_up();

		$this->get = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only saved to restore it.
		$this->act_as( 'administrator' );
		set_current_screen( 'dashboard' );

		( new Queue() )->unschedule();
		delete_option( JobStore::OPTION );
		delete_option( Page::OPTION_IS_INSTALLING );
	}

	public function tear_down(): void {
		$_GET = $this->get;
		( new Queue() )->unschedule();
		remove_action( 'admin_notices', [ Installing::class, 'display' ] );
		set_current_screen( 'front' );

		parent::tear_down();
	}

	public function test_nothing_is_shown_without_an_installation(): void {
		$this->assertSame( '', $this->display() );
	}

	public function test_a_running_installation_shows_its_progress_and_links_to_it(): void {
		( new Queue() )->start( [] );

		$html = $this->display();

		$this->assertStringContainsString( 'notice-info', $html );
		$this->assertStringContainsString( 'linking your posts in the background (0%)', $html );
		$this->assertStringContainsString( 'href="' . esc_url( admin_url( 'options-general.php?page=rp4wp#/setup' ) ) . '">View progress</a>', $html );
		$this->assertStringNotContainsString( 'Dismiss', $html );
	}

	public function test_a_failed_installation_says_so(): void {
		$queue = new Queue();
		$queue->start( [] );
		$job = $queue->job();
		$job->end( Job::FAILED, 'Out of memory.' );
		( new JobStore() )->save( $job );

		$this->assertStringContainsString( 'notice-error', $this->display() );
	}

	public function test_a_2x_wizard_that_was_left_unfinished_can_be_finished_or_dismissed(): void {
		update_option( Page::OPTION_IS_INSTALLING, '1' );

		$html = $this->display();

		$this->assertStringContainsString( 'Finish the installation', $html );
		$this->assertSame( 1, preg_match( '/<a href="([^"]+)">Dismiss<\/a>/', $html, $matches ) );

		parse_str( (string) wp_parse_url( html_entity_decode( $matches[1] ), PHP_URL_QUERY ), $query );
		$this->assertSame( 1, wp_verify_nonce( $query['_wpnonce'], Installing::NONCE ) );

		// A dismiss link without the nonce, as 2.x made them (known issue K9), does nothing.
		$_GET = [ 'rp4wp_hide_is_installing' => '1' ];
		Installing::setup();
		$this->assertSame( '1', get_option( Page::OPTION_IS_INSTALLING ) );

		$_GET = $query;
		Installing::setup();
		$this->assertFalse( get_option( Page::OPTION_IS_INSTALLING ) );
	}

	public function test_the_settings_screen_shows_the_progress_itself(): void {
		$_GET = [ 'page' => 'rp4wp' ];

		Installing::setup();

		$this->assertFalse( has_action( 'admin_notices', [ Installing::class, 'display' ] ) );
	}

	public function test_the_progress_counts_each_step_the_same(): void {
		$status = [
			'steps' => [
				[
					'done'      => true,
					'total'     => 10,
					'remaining' => 0,
				],
				[
					'done'      => false,
					'total'     => 10,
					'remaining' => 5,
				],
				[
					'done'      => false,
					'total'     => null,
					'remaining' => null,
				],
			],
		];

		$this->assertSame( 50, Installing::percent( $status ) );
		$this->assertSame( 0, Installing::percent( null ) );
	}

	/**
	 * The notice as it is printed.
	 *
	 * @return string
	 */
	private function display(): string {
		ob_start();
		Installing::display();

		return (string) ob_get_clean();
	}
}
