<?php
/**
 * The admin app build trait file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Support;

use LV2\WordPress\RelatedPostsForWP\Main;

/**
 * Stands in for the build of the admin app (npm run build), which the integration tests run without.
 */
trait AdminAppBuild {

	/**
	 * The asset file this test wrote, to remove after it.
	 *
	 * @var string|null
	 */
	private ?string $admin_app_asset_file = null;

	/**
	 * The asset file of the build, written when there is no build.
	 *
	 * @return string The path of the asset file.
	 */
	protected function stand_in_for_admin_app_build(): string {
		$file = dirname( Main::file() ) . '/assets/build/admin/index.asset.php';

		if ( ! is_file( $file ) ) {
			wp_mkdir_p( dirname( $file ) );
			file_put_contents( $file, "<?php return array( 'dependencies' => array( 'react-jsx-runtime', 'wp-components', 'wp-element' ), 'version' => 'test' );\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
			$this->admin_app_asset_file = $file;
		}

		return $file;
	}

	/**
	 * Remove what stand_in_for_admin_app_build() wrote.
	 *
	 * @return void
	 */
	protected function remove_admin_app_build_stand_in(): void {
		if ( null !== $this->admin_app_asset_file ) {
			unlink( $this->admin_app_asset_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test fixture.
			$this->admin_app_asset_file = null;
		}
	}
}
