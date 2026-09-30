<?php
/**
 * The REST permissions test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Rest;

/**
 * Every route of the admin app is for administrators only.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Rest\Routes
 */
final class PermissionsTest extends RestTestCase {

	/**
	 * Every route and method, with a valid body.
	 *
	 * @return array<string, array{string, string, array<string, mixed>}>
	 */
	public function data_routes(): array {
		return [
			'read the settings'    => [ 'GET', '/settings', [] ],
			'read a page'          => [ 'GET', '/settings/general', [] ],
			'save a page'          => [ 'PUT', '/settings/general', [ 'values' => [ 'heading_text' => 'Hacked' ] ] ],
			'read the installer'   => [ 'GET', '/install', [] ],
			'start an install'     => [ 'POST', '/install', [ 'amount' => 3 ] ],
			'cancel an install'    => [ 'DELETE', '/install', [] ],
			'resume an install'    => [ 'POST', '/install/retry', [] ],
			'tick an install'      => [ 'POST', '/install/tick', [] ],
		];
	}

	/**
	 * Visitors who are not logged in (or sent no valid nonce) get 401.
	 *
	 * @dataProvider data_routes
	 *
	 * @param string               $method The method.
	 * @param string               $route  The route.
	 * @param array<string, mixed> $body   The body.
	 */
	public function test_visitors_are_not_allowed( string $method, string $route, array $body ): void {
		wp_set_current_user( 0 );

		$response = $this->request( $method, $route, $body );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'rest_forbidden', $this->error_code( $response ) );
	}

	/**
	 * Users who can't manage options get 403, editors included.
	 *
	 * @dataProvider data_routes
	 *
	 * @param string               $method The method.
	 * @param string               $route  The route.
	 * @param array<string, mixed> $body   The body.
	 */
	public function test_editors_and_below_are_not_allowed( string $method, string $route, array $body ): void {
		foreach ( [ 'subscriber', 'contributor', 'author', 'editor' ] as $role ) {
			$this->act_as( $role );

			$response = $this->request( $method, $route, $body );

			$this->assertSame( 403, $response->get_status(), "A user with the {$role} role." );
		}

		$this->assertNotSame( 'Hacked', get_option( 'rp4wp', [] )['heading_text'] ?? null );
		$this->assertNull( get_option( 'rp4wp_install_job', null ) );
	}

	public function test_administrators_are_allowed(): void {
		$this->act_as( 'administrator' );

		$this->assertSame( 200, $this->request( 'GET', '/settings' )->get_status() );
		$this->assertSame( 200, $this->request( 'GET', '/install' )->get_status() );
	}
}
