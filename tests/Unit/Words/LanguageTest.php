<?php
/**
 * The language test file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Unit\Words;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use LV2\WordPress\RelatedPostsForWP\Words\IgnoredWords;
use LV2\WordPress\RelatedPostsForWP\Words\Language;
use LV2\WordPress\RelatedPostsForWP\Words\Tokenizer;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * The language of a post (decision D52): what WPML or Polylang report, otherwise the language of its words, otherwise
 * the site's.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Words\Language
 */
final class LanguageTest extends TestCase {

	/**
	 * The tokenizer.
	 *
	 * @var Tokenizer
	 */
	private Tokenizer $tokenizer;

	/**
	 * The language detection.
	 *
	 * @var Language
	 */
	private Language $language;

	protected function set_up(): void {
		parent::set_up();

		require_once dirname( __DIR__, 2 ) . '/Fixtures/wp-post-stub.php';

		Functions\when( 'get_locale' )->justReturn( 'en_US' );
		// Once a test mocks Polylang, its function exists for the rest of the run; by default it reports nothing.
		Functions\when( 'pll_get_post_language' )->justReturn( false );

		$this->tokenizer = new Tokenizer( false );
		$this->language  = new Language( new IgnoredWords( $this->tokenizer ) );
	}

	/**
	 * Locales and language tags, and the code of their list.
	 *
	 * @dataProvider codes
	 *
	 * @param string $locale   The locale or tag.
	 * @param string $expected The code.
	 */
	public function test_the_code_of_a_locale( string $locale, string $expected ): void {
		$this->assertSame( $expected, Language::code( $locale ) );
	}

	/**
	 * Locales and their code.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function codes(): array {
		return [
			'a locale'                => [ 'en_US', 'en' ],
			'a language'              => [ 'de', 'de' ],
			'a tag with a dash'       => [ 'pt-br', 'pt' ],
			'a script variant'        => [ 'zh-hans', 'zh' ],
			'upper case'              => [ 'NL_be', 'nl' ],
			'Norwegian Bokmål'        => [ 'nb_NO', 'no' ],
			'Norwegian Nynorsk'       => [ 'nn_NO', 'no' ],
			'an old code for Hebrew'  => [ 'iw', 'he' ],
			'nothing'                 => [ '', '' ],
			'not a code'              => [ '../wp-config', '' ],
		];
	}

	/**
	 * Texts and the language found in them.
	 *
	 * @dataProvider texts
	 *
	 * @param string $text     The text.
	 * @param string $expected The language.
	 */
	public function test_the_language_of_words( string $text, string $expected ): void {
		$this->assertSame( $expected, $this->language->detect( $this->tokenizer->tokens( $text ) ) );
	}

	/**
	 * Texts and their language.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function texts(): array {
		return [
			'english'                  => [ 'The best way to bake bread is to give the dough all the time it needs.', 'en' ],
			'german'                   => [ 'Der beste Weg, Brot zu backen, ist, dem Teig die Zeit zu geben, die er braucht.', 'de' ],
			'dutch'                    => [ 'De beste manier om brood te bakken is het deeg de tijd te geven die het nodig heeft.', 'nl' ],
			'french'                   => [ 'La meilleure façon de faire du pain est de laisser au pâton le temps dont il a besoin.', 'fr' ],
			'too few ignored words'    => [ 'Sourdough starter hydration crumb oven spring', '' ],
			'no words'                 => [ '', '' ],
		];
	}

	public function test_a_higher_threshold_finds_nothing_in_a_title_with_few_ignored_words(): void {
		$tokens = $this->tokenizer->tokens( 'Baking sourdough bread at home with a starter' );

		$this->assertSame( 'en', $this->language->detect( $tokens ) );

		Filters\expectApplied( 'rp4wp_language_detection_threshold' )->andReturn( 0.9 );
		$this->assertSame( '', $this->language->detect( $tokens ) );
	}

	public function test_what_wpml_reports_comes_first(): void {
		Filters\expectApplied( 'wpml_post_language_details' )->with( null, 7 )->andReturn( [ 'language_code' => 'de' ] );

		$this->assertSame( 'de', $this->language->of_post( new \WP_Post( (object) [ 'ID' => 7 ] ), $this->tokenizer->tokens( 'The bread and the oven' ) ) );
	}

	public function test_what_polylang_reports_comes_next(): void {
		Functions\when( 'pll_get_post_language' )->justReturn( 'nl_NL' );

		$this->assertSame( 'nl', $this->language->of_post( new \WP_Post( (object) [ 'ID' => 7 ] ), $this->tokenizer->tokens( 'The bread and the oven' ) ) );
	}

	public function test_without_a_report_the_words_decide(): void {
		Functions\when( 'get_locale' )->justReturn( 'nl_NL' );

		$this->assertSame( 'en', $this->language->of_post( new \WP_Post( (object) [ 'ID' => 7 ] ), $this->tokenizer->tokens( 'The bread and the oven' ) ) );
	}

	public function test_without_a_report_or_enough_words_the_site_decides(): void {
		Functions\when( 'get_locale' )->justReturn( 'nl_NL' );

		$this->assertSame( 'nl', $this->language->of_post( new \WP_Post( (object) [ 'ID' => 7 ] ), $this->tokenizer->tokens( 'Sourdough starter hydration' ) ) );
	}

	public function test_a_filter_has_the_last_word(): void {
		Filters\expectApplied( 'rp4wp_post_language' )->with( 'en', 7 )->andReturn( 'fr_FR' );

		$this->assertSame( 'fr', $this->language->of_post( new \WP_Post( (object) [ 'ID' => 7 ] ), $this->tokenizer->tokens( 'The bread and the oven' ) ) );
	}
}
