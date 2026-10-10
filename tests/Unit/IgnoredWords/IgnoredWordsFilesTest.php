<?php
/**
 * The ignored words files test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Unit\IgnoredWords;

use LV2\WordPress\RelatedPostsForWP\Words\IgnoredWords;
use LV2\WordPress\RelatedPostsForWP\Words\Tokenizer;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * The shipped lists of ignored words (decision D52): 57 languages, every word normalized the way the tokenizer
 * normalizes words (with intl, as scripts/ignored-words.php writes them), sorted and without duplicates; the 12 files
 * per locale of 2.x are aliases.
 *
 * @coversNothing
 */
final class IgnoredWordsFilesTest extends TestCase {

	/**
	 * The locales 2.x ships a word list for, and their language.
	 */
	private const LOCALES = [
		'bg_BG' => 'bg',
		'cs_CZ' => 'cs',
		'de_DE' => 'de',
		'en_US' => 'en',
		'es_ES' => 'es',
		'fr_FR' => 'fr',
		'it_IT' => 'it',
		'nb_NO' => 'no',
		'nl_NL' => 'nl',
		'pt_BR' => 'pt',
		'ru_RU' => 'ru',
		'sv_SE' => 'sv',
	];

	/**
	 * Words that the tokenizer of 2.x made out of HTML.
	 */
	private const ARTIFACTS = [ 'amp', 'gt', 'lt', 'div', 'nbsp', 'quot', '39' ];

	/**
	 * Halves of English words with an apostrophe, which the tokenizer keeps whole now ("dont"). In Irish and Hausa,
	 * "don" is a word of its own.
	 */
	private const ENGLISH_HALVES = [ 'don', 'didn', 'doesn' ];

	public function test_57_languages_have_a_list(): void {
		$this->assertCount( 57, IgnoredWords::languages() );
		$this->assertContains( 'en', IgnoredWords::languages() );
		$this->assertContains( 'ja', IgnoredWords::languages() );
	}

	/**
	 * Each list is a sorted list of normalized words.
	 *
	 * @dataProvider languages
	 *
	 * @param string $language The language.
	 */
	public function test_a_list_has_normalized_words( string $language ): void {
		$words = self::words( $language );

		$this->assertNotEmpty( $words );
		$this->assertContainsOnly( 'string', $words );

		$sorted = array_values( array_unique( $words ) );
		sort( $sorted, SORT_STRING );
		$this->assertSame( $sorted, $words, 'Sorted, without duplicates.' );

		if ( extension_loaded( 'intl' ) ) {
			$tokenizer = new Tokenizer( true );
			foreach ( $words as $word ) {
				$this->assertSame( $word, $tokenizer->word( $word ), "\"{$word}\" is normalized." );
			}
		}

		$this->assertSame( [], array_values( array_intersect( 'en' === $language ? array_merge( self::ARTIFACTS, self::ENGLISH_HALVES ) : self::ARTIFACTS, $words ) ) );
	}

	/**
	 * The languages as a data provider.
	 *
	 * @return array<string, array{string}>
	 */
	public static function languages(): array {
		$languages = [];
		foreach ( IgnoredWords::languages() as $language ) {
			$languages[ $language ] = [ $language ];
		}

		return $languages;
	}

	public function test_the_files_per_locale_of_2x_are_aliases(): void {
		foreach ( self::LOCALES as $locale => $language ) {
			$this->assertSame( self::words( $language ), require self::directory() . "/{$locale}.php", $locale );
		}
	}

	/**
	 * The words of a list.
	 *
	 * @param string $language The language.
	 *
	 * @return string[]
	 */
	private static function words( string $language ): array {
		return require self::directory() . "/{$language}.php";
	}

	/**
	 * Where the word lists live.
	 *
	 * @return string
	 */
	private static function directory(): string {
		return dirname( __DIR__, 3 ) . '/resources/ignored-words';
	}
}
