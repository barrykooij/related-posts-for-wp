<?php
/**
 * The admin app assets class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\App;

use LV2\WordPress\RelatedPostsForWP\Main;

/**
 * The script and styles of the admin app, built from assets/src/admin into assets/build/admin (npm run build).
 */
class Assets {

	/**
	 * The script handle. It is the handle of the 2.x settings script, which the app replaces.
	 */
	public const SCRIPT = 'rp4wp_settings_js';

	/**
	 * The style handle, also kept from 2.x.
	 */
	public const STYLE = 'rp4wp-settings-css';

	/**
	 * The folder of the build, from the plugin folder.
	 */
	private const BUILD = 'assets/build/admin';

	/**
	 * Enqueue the app. Without a build, admins see a notice instead.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		$asset = self::asset();

		if ( null === $asset ) {
			add_action( 'admin_notices', [ self::class, 'missing_build_notice' ] );

			return;
		}

		wp_enqueue_script( self::SCRIPT, self::url( 'index.js' ), $asset['dependencies'], $asset['version'], [ 'in_footer' => true ] );
		wp_add_inline_script( self::SCRIPT, 'window.rp4wpAdminData = ' . wp_json_encode( self::data() ) . ';', 'before' );
		wp_set_script_translations( self::SCRIPT, 'related-posts-for-wp' );
		wp_enqueue_style( self::STYLE, self::url( 'index.css' ), [ 'wp-components' ], $asset['version'] );

		/**
		 * Fires after the admin app is enqueued. Scripts that extend the app, with the app as a dependency, are
		 * enqueued here.
		 *
		 * @since 3.0.0
		 */
		do_action( 'rp4wp_admin_enqueue_scripts' );
	}

	/**
	 * What the app gets from PHP, as window.rp4wpAdminData.
	 *
	 * @return array<string, mixed>
	 */
	public static function data(): array {
		$data = [
			'version' => Main::VERSION,
			'edition' => 'free',
		];

		/**
		 * Filters what the admin app gets from PHP.
		 *
		 * @since 3.0.0
		 *
		 * @param array<string, mixed> $data The data.
		 */
		return (array) apply_filters( 'rp4wp_admin_data', $data );
	}

	/**
	 * Tell admins that the app was not built. Only happens in a copy of the repository without `npm run build`.
	 *
	 * @return void
	 */
	public static function missing_build_notice(): void {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'The settings screen of Related Posts for WordPress is missing its scripts. Run npm run build in the plugin folder.', 'related-posts-for-wp' );
		echo '</p></div>';
	}

	/**
	 * The dependencies and version of the build, from the asset file the build writes.
	 *
	 * @return array{dependencies: string[], version: string}|null Null without a build.
	 */
	private static function asset(): ?array {
		$file = dirname( Main::file() ) . '/' . self::BUILD . '/index.asset.php';

		if ( ! is_readable( $file ) ) {
			return null;
		}

		$asset = require $file;

		if ( ! is_array( $asset ) || ! isset( $asset['dependencies'], $asset['version'] ) || ! is_array( $asset['dependencies'] ) ) {
			return null;
		}

		return [
			'dependencies' => array_map( 'strval', $asset['dependencies'] ),
			'version'      => (string) $asset['version'],
		];
	}

	/**
	 * The URL of a file of the build.
	 *
	 * @param string $file The file name.
	 *
	 * @return string
	 */
	private static function url( string $file ): string {
		return plugins_url( '/' . self::BUILD . '/' . $file, Main::file() );
	}
}
