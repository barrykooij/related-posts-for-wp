<?php
/**
 * The install REST controller class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Rest;

use LV2\WordPress\RelatedPostsForWP\Install\Jobs\Queue;
use LV2\WordPress\RelatedPostsForWP\Main;

/**
 * The background installer:
 *
 * - `GET /rp4wp/v1/install`: whether the site was installed, the current or last job with its progress, and what a
 *   new installation accepts;
 * - `POST /rp4wp/v1/install`: start an installation;
 * - `DELETE /rp4wp/v1/install`: cancel it;
 * - `POST /rp4wp/v1/install/retry`: resume a failed or cancelled one;
 * - `POST /rp4wp/v1/install/tick`: run the next part of the job in this request, for when background requests don't.
 *
 * Every route answers with the same state as the GET route.
 */
class InstallController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register(): void {
		register_rest_route(
			Routes::NAMESPACE,
			'/install',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'show' ],
					'permission_callback' => [ Routes::class, 'can_manage' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'start' ],
					'permission_callback' => [ Routes::class, 'can_manage' ],
					'args'                => Main::get()->install_planner()->args(),
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'cancel' ],
					'permission_callback' => [ Routes::class, 'can_manage' ],
				],
			]
		);

		register_rest_route(
			Routes::NAMESPACE,
			'/install/retry',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'retry' ],
				'permission_callback' => [ Routes::class, 'can_manage' ],
			]
		);

		register_rest_route(
			Routes::NAMESPACE,
			'/install/tick',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'tick' ],
				'permission_callback' => [ Routes::class, 'can_manage' ],
			]
		);
	}

	/**
	 * The state of the installer.
	 *
	 * @return \WP_REST_Response
	 */
	public function show(): \WP_REST_Response {
		return rest_ensure_response( $this->state( new Queue() ) );
	}

	/**
	 * Start an installation.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function start( \WP_REST_Request $request ) {
		$planner = Main::get()->install_planner();
		$queue   = new Queue( $planner );
		$params  = [];

		// Only what the planner accepts, validated and with its defaults, as the REST API prepared it.
		foreach ( array_keys( $planner->args() ) as $key ) {
			if ( null !== $request[ $key ] ) {
				$params[ $key ] = $request[ $key ];
			}
		}

		$job = $queue->start( $params );

		if ( is_wp_error( $job ) ) {
			return $job;
		}

		$response = rest_ensure_response( $this->state( $queue ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Cancel the running installation.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function cancel() {
		$queue = new Queue();

		if ( null === $queue->cancel() ) {
			return new \WP_Error( 'rp4wp_install_not_running', __( 'No installation is running.', 'related-posts-for-wp' ), [ 'status' => 409 ] );
		}

		return rest_ensure_response( $this->state( $queue ) );
	}

	/**
	 * Resume a failed or cancelled installation.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function retry() {
		$queue = new Queue();
		$job   = $queue->retry();

		if ( is_wp_error( $job ) ) {
			return $job;
		}

		return rest_ensure_response( $this->state( $queue ) );
	}

	/**
	 * Run the next part of the job in this request.
	 *
	 * @return \WP_REST_Response
	 */
	public function tick(): \WP_REST_Response {
		$queue = new Queue();
		$ran   = $queue->tick();

		return rest_ensure_response( [ 'ran' => $ran ] + $this->state( $queue ) );
	}

	/**
	 * The state of the installer.
	 *
	 * @param Queue $queue The queue.
	 *
	 * @return array<string, mixed>
	 */
	private function state( Queue $queue ): array {
		$planner = Main::get()->install_planner();

		return [
			'installed' => $planner->is_installed(),
			'job'       => $queue->status(),
			'args'      => (object) $planner->args(),
		];
	}
}
