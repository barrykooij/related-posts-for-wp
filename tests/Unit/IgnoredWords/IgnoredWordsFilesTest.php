<?php
/**
 * The ignored words files test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Unit\IgnoredWords;

use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * The per-locale ignored words files keep their format when they move in the rework.
 *
 * @coversNothing
 */
final class IgnoredWordsFilesTest extends TestCase {

	/**
	 * The locales 2.x ships a word list for.
	 */
	private const LOCALES = [ 'bg_BG', 'cs_CZ', 'de_DE', 'en_US', 'es_ES', 'fr_FR', 'it_IT', 'nb_NO', 'nl_NL', 'pt_BR', 'ru_RU', 'sv_SE' ];

	public function test_every_2x_locale_has_a_word_list(): void {
		$files = array_map(
			static function ( $file ) {
				return basename( $file, '.php' );
			},
			glob( self::directory() . '/*.php' )
		);

		sort( $files );

		$this->assertSame( self::LOCALES, $files );
	}

	/**
	 * Each file returns a flat list of strings.
	 *
	 * @dataProvider locales
	 *
	 * @param string $locale The locale.
	 */
	public function test_word_list_returns_strings( string $locale ): void {
		$words = require self::directory() . "/{$locale}.php";

		$this->assertIsArray( $words );
		$this->assertNotEmpty( $words );
		$this->assertContainsOnly( 'string', $words );
		$this->assertSame( array_values( $words ), $words, 'The word list must be a plain list.' );
	}

	/**
	 * The locales as a data provider.
	 *
	 * @return array<string, array{string}>
	 */
	public static function locales(): array {
		return array_combine(
			self::LOCALES,
			array_map(
				static function ( $locale ) {
					return [ $locale ];
				},
				self::LOCALES
			)
		);
	}

	/**
	 * Where the word lists live in 2.x.
	 *
	 * @return string
	 */
	private static function directory(): string {
		return dirname( __DIR__, 3 ) . '/classes/ignored-words';
	}
}
