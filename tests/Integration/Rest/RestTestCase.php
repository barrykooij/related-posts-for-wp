<?php
/**
 * The REST test case class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Rest;

use LV2\WordPress\RelatedPostsForWP\Install\Jobs\JobStore;
use LV2\WordPress\RelatedPostsForWP\Install\Jobs\Queue;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Settings\Schema\Pages;
use LV2\WordPress\RelatedPostsForWP\Settings\Settings;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * Runs requests against a fresh REST server, with fresh settings, so filters on the settings sections take effect.
 */
abstract class RestTestCase extends TestCase {

	/**
	 * The settings service before the test.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	public function set_up(): void {
		parent::set_up();

		$this->settings = Main::get()->settings();

		$this->fresh_settings();

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		( new Queue() )->unschedule();
		delete_option( JobStore::OPTION );
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		( new Queue() )->unschedule();
		Main::get()->set_settings( $this->settings );
		Main::get()->set_settings_schema( new Pages( $this->settings ) );

		parent::tear_down();
	}

	/**
	 * Build the settings again, after changing the filters on them.
	 *
	 * @return void
	 */
	protected function fresh_settings(): void {
		$settings = new Settings();
		Main::get()->set_settings( $settings );
		Main::get()->set_settings_schema( new Pages( $settings ) );
	}

	/**
	 * Send a request.
	 *
	 * @param string               $method The method.
	 * @param string               $route  The route, after the namespace, for example `/settings`.
	 * @param array<string, mixed> $body   The JSON body.
	 *
	 * @return \WP_REST_Response
	 */
	protected function request( string $method, string $route, array $body = [] ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, '/rp4wp/v1' . $route );

		if ( [] !== $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * The error code of a response.
	 *
	 * @param \WP_REST_Response $response The response.
	 *
	 * @return string
	 */
	protected function error_code( \WP_REST_Response $response ): string {
		$data = $response->get_data();

		return is_array( $data ) ? (string) ( $data['code'] ?? '' ) : '';
	}
}
