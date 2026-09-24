<?php
/**
 * Plugin Name: RP4WP E2E helpers
 * Description: Test-only helpers for the Playwright suite. Mounted into the wp-env test site only; never shipped.
 *
 * - REST endpoints to reset the site and to install the plugin the way the admin screen does.
 * - A recorder for PHP notices, warnings and deprecations raised from plugin code, so specs can fail on them.
 *
 * @package RelatedPostsForWP
 */

// The PHPUnit suite loads mu-plugins too; these helpers are for the browser tests only. The integration bootstrap
// defines WP_TESTS_CONFIG_FILE_PATH (WP_TESTS_DOMAIN is no signal: wp-env defines it for the normal site as well).
if ( defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	return;
}

/**
 * Option that holds the recorded problems.
 */
const RP4WP_E2E_ERRORS_OPTION = 'rp4wp_e2e_errors';

/**
 * Whether a file belongs to the plugin code under test (not its tests or dependencies).
 *
 * @param string $file The file path.
 *
 * @return bool
 */
function rp4wp_e2e_is_plugin_file( $file ) {
	$root = WP_PLUGIN_DIR . '/related-posts-for-wp/';

	return 0 === strpos( (string) $file, $root )
		&& 0 !== strpos( (string) $file, $root . 'vendor/' )
		&& 0 !== strpos( (string) $file, $root . 'tests/' );
}

/**
 * Store a problem.
 *
 * @param string $type    The kind of problem.
 * @param string $message The message.
 * @param string $file    The file.
 * @param int    $line    The line.
 *
 * @return void
 */
function rp4wp_e2e_record( $type, $message, $file, $line ) {
	$errors   = (array) get_option( RP4WP_E2E_ERRORS_OPTION, [] );
	$errors[] = [
		'type'    => $type,
		'message' => $message,
		'file'    => str_replace( WP_PLUGIN_DIR . '/related-posts-for-wp/', '', (string) $file ),
		'line'    => (int) $line,
		'url'     => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
	];

	update_option( RP4WP_E2E_ERRORS_OPTION, array_slice( $errors, -50 ), false );
}

/**
 * The first stack frame inside plugin code, if any.
 *
 * @return array{file: string, line: int}|null
 */
function rp4wp_e2e_plugin_frame() {
	foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $frame ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Test-only.
		if ( isset( $frame['file'] ) && rp4wp_e2e_is_plugin_file( $frame['file'] ) ) {
			return [
				'file' => $frame['file'],
				'line' => isset( $frame['line'] ) ? (int) $frame['line'] : 0,
			];
		}
	}

	return null;
}

// PHP notices, warnings and deprecations raised in plugin files.
set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Test-only.
	static function ( $errno, $errstr, $errfile, $errline ) {
		if ( rp4wp_e2e_is_plugin_file( $errfile ) ) {
			rp4wp_e2e_record( 'php:' . $errno, $errstr, $errfile, $errline );
		}

		return false; // Let PHP and WordPress handle it as usual.
	}
);

// WordPress deprecations and "doing it wrong" notices triggered from plugin code.
foreach ( [ 'deprecated_function_run', 'deprecated_argument_run', 'deprecated_hook_run', 'deprecated_class_run', 'doing_it_wrong_run' ] as $rp4wp_e2e_hook ) {
	add_action(
		$rp4wp_e2e_hook,
		static function ( $name ) use ( $rp4wp_e2e_hook ) {
			$frame = rp4wp_e2e_plugin_frame();
			if ( null !== $frame ) {
				rp4wp_e2e_record( $rp4wp_e2e_hook, (string) $name, $frame['file'], $frame['line'] );
			}
		}
	);
}
unset( $rp4wp_e2e_hook );

// Fatal errors in plugin files.
register_shutdown_function(
	static function () {
		$error = error_get_last();
		if ( null !== $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR ], true ) && rp4wp_e2e_is_plugin_file( $error['file'] ) ) {
			rp4wp_e2e_record( 'fatal', $error['message'], $error['file'], $error['line'] );
		}
	}
);

add_action(
	'rest_api_init',
	static function () {
		$admin_only = static function () {
			return current_user_can( 'manage_options' );
		};

		// Put the site back to "plugin never installed": inactive, no data, no content.
		register_rest_route(
			'rp4wp-e2e/v1',
			'/reset',
			[
				'methods'             => 'POST',
				'permission_callback' => $admin_only,
				'callback'            => static function () {
					global $wpdb;

					require_once ABSPATH . 'wp-admin/includes/plugin.php';
					deactivate_plugins( 'related-posts-for-wp/related-posts-for-wp.php', true );

					foreach ( get_posts(
						[
							'post_type'      => [ 'post', 'page', 'rp4wp_link' ],
							'post_status'    => 'any',
							'posts_per_page' => -1,
							'fields'         => 'ids',
						]
					) as $post_id ) {
						wp_delete_post( $post_id, true );
					}

					$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'rp4wp%' OR option_name = 'widget_rp4wp_related_posts_widget'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test reset.
					$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'rp4wp%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test reset.
					$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rp4wp_cache" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Test reset.
					wp_cache_flush();

					// Take the related posts widget out of every sidebar.
					$sidebars = (array) get_option( 'sidebars_widgets', [] );
					foreach ( $sidebars as $sidebar => $widgets ) {
						if ( is_array( $widgets ) ) {
							$sidebars[ $sidebar ] = array_values(
								array_filter(
									$widgets,
									static function ( $widget ) {
										return 0 !== strpos( $widget, 'rp4wp_related_posts_widget-' );
									}
								)
							);
						}
					}
					update_option( 'sidebars_widgets', $sidebars );

					return [ 'reset' => true ];
				},
			]
		);

		// Activate the plugin the way the admin screen does. Activation over the REST API skips the activation hook in
		// 2.x, because the hook is only registered on admin requests (a known issue), so run it here.
		register_rest_route(
			'rp4wp-e2e/v1',
			'/install',
			[
				'methods'             => 'POST',
				'permission_callback' => $admin_only,
				'callback'            => static function () {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';

					$result = activate_plugin( 'related-posts-for-wp/related-posts-for-wp.php' );
					if ( is_wp_error( $result ) ) {
						return $result;
					}

					require_once WP_PLUGIN_DIR . '/related-posts-for-wp/includes/installer-functions.php';
					rp4wp_activate_plugin();
					delete_option( 'rp4wp_do_install' );

					return [ 'installed' => true ];
				},
			]
		);

		// Cache words and link every post, like finishing the installation wizard. Run after /install.
		register_rest_route(
			'rp4wp-e2e/v1',
			'/link',
			[
				'methods'             => 'POST',
				'permission_callback' => $admin_only,
				'callback'            => static function () {
					if ( ! class_exists( \LV2\WordPress\RelatedPostsForWP\Main::class ) || ! \LV2\WordPress\RelatedPostsForWP\Main::get()->is_set_up() ) {
						return new WP_Error( 'rp4wp_e2e_inactive', 'Activate the plugin first.', [ 'status' => 409 ] );
					}

					( new \LV2\WordPress\RelatedPostsForWP\Words\Cache() )->save_all();
					( new \LV2\WordPress\RelatedPostsForWP\Related\Linker() )->link_all( 3 );

					return [ 'linked' => true ];
				},
			]
		);

		// Put one related posts widget in a sidebar of the active (classic) theme.
		register_rest_route(
			'rp4wp-e2e/v1',
			'/widget',
			[
				'methods'             => 'POST',
				'permission_callback' => $admin_only,
				'args'                => [
					'sidebar' => [
						'type'     => 'string',
						'required' => true,
					],
				],
				'callback'            => static function ( WP_REST_Request $request ) {
					update_option(
						'widget_rp4wp_related_posts_widget',
						[
							2              => [],
							'_multiwidget' => 1,
						]
					);

					$sidebars                                  = (array) get_option( 'sidebars_widgets', [] );
					$sidebars[ (string) $request['sidebar'] ] = [ 'rp4wp_related_posts_widget-2' ];
					update_option( 'sidebars_widgets', $sidebars );

					return [ 'widget' => true ];
				},
			]
		);

		// Read or clear the recorded problems.
		register_rest_route(
			'rp4wp-e2e/v1',
			'/errors',
			[
				[
					'methods'             => 'GET',
					'permission_callback' => $admin_only,
					'callback'            => static function () {
						return array_values( (array) get_option( RP4WP_E2E_ERRORS_OPTION, [] ) );
					},
				],
				[
					'methods'             => 'DELETE',
					'permission_callback' => $admin_only,
					'callback'            => static function () {
						delete_option( RP4WP_E2E_ERRORS_OPTION );

						return [ 'cleared' => true ];
					},
				],
			]
		);
	}
);
