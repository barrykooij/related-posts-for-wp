<?php
/**
 * The version test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Unit\Release;

use LV2\WordPress\RelatedPostsForWP\Main;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Every place that names the version and the requirements of the plugin names the same ones. The release workflow only
 * checks the header and, for a release to WordPress.org, the stable tag.
 *
 * @coversNothing
 */
final class VersionTest extends TestCase {

	public function test_the_header_the_code_and_the_package_have_the_same_version(): void {
		$package = json_decode( $this->file( 'package.json' ), true );

		$this->assertSame( Main::VERSION, $this->header( $this->file( 'related-posts-for-wp.php' ), 'Version' ), 'The Version header of the main file.' );
		$this->assertSame( Main::VERSION, $package['version'] ?? null, 'The version in package.json.' );
	}

	public function test_the_readme_has_the_requirements_of_the_header(): void {
		$main   = $this->file( 'related-posts-for-wp.php' );
		$readme = $this->file( 'readme.txt' );

		foreach ( [ 'Requires at least', 'Requires PHP' ] as $name ) {
			$this->assertSame( $this->header( $main, $name ), $this->header( $readme, $name ), "The {$name} header of the readme." );
		}
	}

	/**
	 * A file of the plugin.
	 *
	 * @param string $name The path, from the plugin folder.
	 *
	 * @return string
	 */
	private function file( string $name ): string {
		return (string) file_get_contents( dirname( __DIR__, 3 ) . '/' . $name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source file.
	}

	/**
	 * The value of a header in the main file or the readme.
	 *
	 * @param string $contents The file.
	 * @param string $name     The header.
	 *
	 * @return string|null
	 */
	private function header( string $contents, string $name ): ?string {
		return preg_match( '/^[ \t\/*#@]*' . preg_quote( $name, '/' ) . ':\s*(\S+)/m', $contents, $match ) ? $match[1] : null;
	}
}
