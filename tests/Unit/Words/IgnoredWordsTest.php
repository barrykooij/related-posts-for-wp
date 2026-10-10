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
use LV2\WordPress\RelatedPostsForWP\Words\Tokenizer;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * The ignored words per language (decision D52): the shipped lists, the filter, and the normalization of added words.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Words\IgnoredWords
 */
final class IgnoredWordsTest extends TestCase {

	public function test_uses_the_language_of_the_site_by_default(): void {
		Functions\when( 'get_locale' )->justReturn( 'nl_NL' );

		$this->assertContains( 'het', $this->ignored()->get() );
	}

	public function test_takes_a_language_or_a_locale(): void {
		$ignored = $this->ignored();

		$this->assertSame( $ignored->get( 'de' ), $ignored->get( 'de_DE' ) );
		$this->assertContains( 'und', $ignored->get( 'de_DE' ) );
		$this->assertSame( $ignored->get( 'no' ), $ignored->get( 'nb_NO' ), 'Norwegian Bokmål uses the Norwegian list.' );
	}

	public function test_every_language_has_its_own_list(): void {
		$ignored = $this->ignored();

		$this->assertTrue( $ignored->is_ignored( 'the', 'en' ) );
		$this->assertFalse( $ignored->is_ignored( 'the', 'nl' ) );
		$this->assertTrue( $ignored->is_ignored( 'het', 'nl' ) );
	}

	public function test_words_the_filter_adds_are_normalized(): void {
		Filters\expectApplied( 'rp4wp_ignored_words' )
			->once()
			->with( \Mockery::type( 'array' ), 'en' )
			->andReturnUsing(
				static function ( array $words ): array {
					return array_merge( $words, [ 'WordPress', "Plugin's", 'Für', 'two words', '!' ] );
				}
			);

		$ignored = $this->ignored();
		$words   = $ignored->get( 'en' );

		foreach ( [ 'wordpress', 'plugins', 'fuer' ] as $word ) {
			$this->assertContains( $word, $words );
		}
		$this->assertNotContains( 'two words', $words );
		$this->assertNotContains( '!', $words );

		$ignored->get( 'en' );
	}

	public function test_a_language_without_a_list_has_only_the_words_the_filter_adds(): void {
		$this->assertSame( [], $this->ignored()->get( 'xx' ) );

		Filters\expectApplied( 'rp4wp_ignored_words' )->once()->andReturn( [ 'Extra' ] );
		$this->assertSame( [ 'extra' ], $this->ignored()->get( 'xx' ) );
	}

	public function test_path_traversal_is_refused(): void {
		$this->assertSame( [], $this->ignored()->get( '../../wp-config' ) );
	}

	public function test_the_languages_of_a_word(): void {
		$ignored = $this->ignored();

		$this->assertContains( 'en', $ignored->languages_of( 'the' ) );
		$this->assertContains( 'nl', $ignored->languages_of( 'het' ) );
		$this->assertSame( [], $ignored->languages_of( 'sourdough' ) );
	}

	/**
	 * Ignored words with a tokenizer without intl, so the test needs no filter for the tokenizer.
	 *
	 * @return IgnoredWords
	 */
	private function ignored(): IgnoredWords {
		return new IgnoredWords( new Tokenizer( false ) );
	}
}
