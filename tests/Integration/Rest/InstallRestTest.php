<?php
/**
 * The install REST test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Rest;

use LV2\WordPress\RelatedPostsForWP\Install\Jobs\Queue;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;

/**
 * Starting, following, cancelling and resuming the background installer through the REST API.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Rest\InstallController
 */
final class InstallRestTest extends RestTestCase {

	public function set_up(): void {
		parent::set_up();

		$this->act_as( 'administrator' );
		self::truncate_cache();
		add_filter( 'rp4wp_install_time_budget', '__return_zero' );
	}

	public function test_a_site_without_words_is_not_installed_and_has_no_job(): void {
		update_option( 'rp4wp', [ 'automatic_linking_post_amount' => 5 ] );

		$state = $this->request( 'GET', '/install' )->get_data();

		$this->assertFalse( $state['installed'] );
		$this->assertNull( $state['job'] );
		$this->assertSame( 5, $state['args']->amount['default'], 'The next installation starts from the setting.' );
		$this->assertSame( 50, $state['args']->amount['maximum'] );
	}

	public function test_starting_answers_with_the_running_job(): void {
		$response = $this->request( 'POST', '/install', [ 'amount' => 2 ] );

		$this->assertSame( 201, $response->get_status() );
		$job = $response->get_data()['job'];
		$this->assertSame( 'running', $job['status'] );
		$this->assertSame(
			[
				'amount' => 2,
				'skip_linking' => false,
				'rebuild' => false,
			],
			$job['request']
		);
		$this->assertSame( [ 'cache_words', 'save_amount', 'link_posts' ], array_column( $job['steps'], 'id' ) );
	}

	/**
	 * Requests that are not valid.
	 *
	 * @return array<string, array{array<string, mixed>}>
	 */
	public function data_invalid_starts(): array {
		return [
			'no related posts'    => [ [ 'amount' => 0 ] ],
			'too many'            => [ [ 'amount' => 51 ] ],
			'not a number'        => [ [ 'amount' => 'three' ] ],
			'rebuild as text'     => [ [ 'rebuild' => 'sure' ] ],
		];
	}

	/**
	 * Invalid requests are rejected with 400, and nothing starts.
	 *
	 * @dataProvider data_invalid_starts
	 *
	 * @param array<string, mixed> $body The body.
	 */
	public function test_invalid_requests_do_not_start_an_installation( array $body ): void {
		$response = $this->request( 'POST', '/install', $body );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $this->error_code( $response ) );
		$this->assertNull( get_option( 'rp4wp_install_job', null ) );
	}

	public function test_only_one_installation_runs_at_a_time(): void {
		$this->request( 'POST', '/install', [] );

		$response = $this->request( 'POST', '/install', [] );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'rp4wp_install_running', $this->error_code( $response ) );
	}

	public function test_cancelling_and_resuming(): void {
		$this->assertSame( 409, $this->request( 'DELETE', '/install' )->get_status(), 'Nothing runs yet.' );
		$this->assertSame( 409, $this->request( 'POST', '/install/retry' )->get_status(), 'Nothing to resume yet.' );

		$this->request( 'POST', '/install', [] );

		$cancelled = $this->request( 'DELETE', '/install' );
		$this->assertSame( 200, $cancelled->get_status() );
		$this->assertSame( 'cancelled', $cancelled->get_data()['job']['status'] );

		$resumed = $this->request( 'POST', '/install/retry' );
		$this->assertSame( 200, $resumed->get_status() );
		$this->assertSame( 'running', $resumed->get_data()['job']['status'] );
	}

	public function test_a_tick_runs_the_job_in_the_request_and_answers_with_its_progress(): void {
		update_option( 'rp4wp', [ 'automatic_linking' => 0 ] );
		self::factory()->post->create_many( 3 );
		self::truncate_cache();

		$this->request( 'POST', '/install', [ 'skip_linking' => true ] );
		$response = $this->request( 'POST', '/install/tick' );

		$this->assertTrue( $response->get_data()['ran'] );
		$this->assertSame( 0, ( new Cache() )->uncached_post_count() );
		$this->assertTrue( $response->get_data()['job']['steps'][0]['done'] );
	}

	public function test_a_finished_installation_makes_the_site_installed(): void {
		update_option( 'rp4wp', [ 'automatic_linking' => 0 ] );
		self::factory()->post->create( [ 'post_content' => 'Words about sourdough bread and ovens.' ] );
		self::truncate_cache();

		$job = $this->request( 'POST', '/install', [] )->get_data()['job'];

		for ( $i = 0; $i < 10 && 'running' === ( new Queue() )->job()->status; $i++ ) {
			do_action( Queue::HOOK, $job['id'] );
		}

		$state = $this->request( 'GET', '/install' )->get_data();
		$this->assertTrue( $state['installed'] );
		$this->assertSame( 'done', $state['job']['status'] );
	}
}
