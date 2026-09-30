<?php
/**
 * The settings REST controller class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Rest;

use LV2\WordPress\RelatedPostsForWP\Main;

/**
 * `GET /rp4wp/v1/settings`: every settings page with its fields and values.
 * `PUT /rp4wp/v1/settings/{page}`: save fields of a page.
 */
class SettingsController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register(): void {
		register_rest_route(
			Routes::NAMESPACE,
			'/settings',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => [ Routes::class, 'can_manage' ],
			]
		);

		register_rest_route(
			Routes::NAMESPACE,
			'/settings/(?P<page>[a-z0-9_-]+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'show' ],
					'permission_callback' => [ Routes::class, 'can_manage' ],
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update' ],
					'permission_callback' => [ Routes::class, 'can_manage' ],
					'args'                => [
						'values' => [
							'description' => __( 'The values to save, by field ID. Fields that are left out keep their value.', 'related-posts-for-wp' ),
							'type'        => 'object',
							'required'    => true,
						],
					],
				],
			]
		);
	}

	/**
	 * Every settings page with its fields and values.
	 *
	 * @return \WP_REST_Response
	 */
	public function index(): \WP_REST_Response {
		$schema = Main::get()->settings_schema();
		$pages  = [];

		foreach ( $schema->pages() as $page ) {
			$values         = $schema->values( (string) $page['id'] );
			$page['values'] = is_wp_error( $values ) ? (object) [] : (object) $values;
			$pages[]        = $page;
		}

		return rest_ensure_response( [ 'pages' => $pages ] );
	}

	/**
	 * The values of one page.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( \WP_REST_Request $request ) {
		$page   = (string) $request['page'];
		$values = Main::get()->settings_schema()->values( $page );

		if ( is_wp_error( $values ) ) {
			return $values;
		}

		return rest_ensure_response(
			[
				'page'   => $page,
				'values' => (object) $values,
			]
		);
	}

	/**
	 * Save fields of a page.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update( \WP_REST_Request $request ) {
		$page   = (string) $request['page'];
		$values = Main::get()->settings_schema()->save( $page, (array) $request['values'] );

		if ( is_wp_error( $values ) ) {
			return $values;
		}

		return rest_ensure_response(
			[
				'page'   => $page,
				'values' => (object) $values,
			]
		);
	}
}
