<?php
/**
 * The ignored words test file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Unit\Words;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use LV2\WordPress\RelatedPostsForWP\Words\IgnoredWords;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Loading the ignored words per locale, with the 2.x caching and filter rules.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Words\IgnoredWords
 */
final class IgnoredWordsTest extends TestCase {

	protected function set_up(): void {
		parent::set_up();

		Functions\when( 'validate_file' )->alias(
			static function ( $file ) {
				return false !== strpos( $file, '..' ) ? 1 : 0;
			}
		);
	}

	public function test_uses_the_site_locale_by_default(): void {
		Functions\when( 'get_locale' )->justReturn( 'nl_NL' );

		$words = ( new IgnoredWords() )->get();

		$this->assertContains( 'het', $words );
	}

	public function test_applies_the_filter(): void {
		Filters\expectApplied( 'rp4wp_ignored_words' )->once()->andReturn( [ 'custom' ] );

		$this->assertSame( [ 'custom' ], ( new IgnoredWords() )->get( 'en_US' ) );
	}

	public function test_a_locale_without_a_list_gives_nothing_and_skips_the_filter(): void {
		Filters\expectApplied( 'rp4wp_ignored_words' )->never();

		$this->assertSame( [], ( new IgnoredWords() )->get( 'en_GB' ) );
	}

	public function test_path_traversal_is_refused(): void {
		$this->assertSame( [], ( new IgnoredWords() )->get( '../../wp-config' ) );
	}

	public function test_the_first_list_is_reused_for_other_locales(): void {
		Filters\expectApplied( 'rp4wp_ignored_words' )->once()->andReturnFirstArg();
		$ignored = new IgnoredWords();

		$english = $ignored->get( 'en_US' );

		$this->assertSame( $english, $ignored->get( 'nl_NL' ) );
	}

	public function test_an_empty_list_is_loaded_again(): void {
		Filters\expectApplied( 'rp4wp_ignored_words' )->twice()->andReturn( [] );
		$ignored = new IgnoredWords();

		$ignored->get( 'en_US' );
		$ignored->get( 'en_US' );
	}
}
