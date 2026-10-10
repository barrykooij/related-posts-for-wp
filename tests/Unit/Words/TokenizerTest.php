<?php
/**
 * The tokenizer test file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Unit\Words;

use LV2\WordPress\RelatedPostsForWP\Words\Tokenizer;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * The rules of the tokenizer (decision D51), with and without intl.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Words\Tokenizer
 */
final class TokenizerTest extends TestCase {

	/**
	 * Texts and their words, the same with and without intl.
	 *
	 * @dataProvider texts
	 *
	 * @param string   $text     The text.
	 * @param string[] $expected The words.
	 */
	public function test_words_of_a_text( string $text, array $expected ): void {
		$this->assertSame( $expected, ( new Tokenizer( false ) )->tokens( $text ), 'Without intl.' );

		if ( extension_loaded( 'intl' ) ) {
			$this->assertSame( $expected, ( new Tokenizer( true ) )->tokens( $text ), 'With intl.' );
		}
	}

	/**
	 * Texts and their words.
	 *
	 * @return array<string, array{string, string[]}>
	 */
	public static function texts(): array {
		return [
			'words are lower-cased'                  => [ 'Related Posts', [ 'related', 'posts' ] ],
			'entities are decoded, not words'        => [ 'Tom &amp; Jerry &lt;3', [ 'tom', 'jerry' ] ],
			'an entity encoded twice too'            => [ 'Salt &amp;amp; pepper', [ 'salt', 'pepper' ] ],
			'a non-breaking space separates'         => [ "coffee&nbsp;beans coffee\u{00A0}cups", [ 'coffee', 'beans', 'coffee', 'cups' ] ],
			'script and style lose their contents'   => [ '<p>Bread</p><script>var dough = 1;</script><style>.crust{}</style>', [ 'bread' ] ],
			'tags separate words'                    => [ 'one<br>two<p>three</p>', [ 'one', 'two', 'three' ] ],
			'comments go'                            => [ 'before<!--more-->after', [ 'before', 'after' ] ],
			'apostrophes inside a word go'           => [ "don't it's rock’n’roll", [ 'dont', 'its', 'rocknroll' ] ],
			'quotes around a word go'                => [ "'quoted' \"words\"", [ 'quoted', 'words' ] ],
			'decimal numbers stay whole'             => [ 'Version 1.5 has 1,000 users', [ 'version', '1.5', 'has', '1,000', 'users' ] ],
			'digits are words'                       => [ 'top 10 of 2024', [ 'top', '10', 'of', '2024' ] ],
			'punctuation separates'                  => [ 'wp-config.php, e.g. (this)!', [ 'wp', 'config', 'php', 'this' ] ],
			'one character is too short'             => [ 'a b cd', [ 'cd' ] ],
			'64 characters fit, 65 do not'           => [ str_repeat( 'a', 64 ) . ' ' . str_repeat( 'b', 65 ), [ str_repeat( 'a', 64 ) ] ],
			'latin accents are folded'               => [ 'Crème brûlée à la façon', [ 'creme', 'brulee', 'la', 'facon' ] ],
			'umlauts and sharp s are spelled out'    => [ 'Für Straße Ökologie', [ 'fuer', 'strasse', 'oekologie' ] ],
			'ligatures become two letters'           => [ 'Æsop œuvre', [ 'aesop', 'oeuvre' ] ],
			'a separate accent mark is folded'       => [ "cafe\u{0301} a\u{0308}rger", [ 'cafe', 'aerger' ] ],
			'other scripts are kept'                 => [ 'Привет мир Ελληνικά', [ 'привет', 'мир', 'ελληνικά' ] ],
			'korean is split on spaces'              => [ '한국어 텍스트', [ '한국어', '텍스트' ] ],
			'emoji are not words'                    => [ "cake\u{1F382}party \u{1F389}", [ 'cake', 'party' ] ],
			'4-byte letters are kept'                => [ "\u{10330}\u{10331} name", [ "\u{10330}\u{10331}", 'name' ] ],
			'text that is not UTF-8 is read as Latin-1' => [ "caf\xE9 cr\xE8me", [ 'cafe', 'creme' ] ],
		];
	}

	public function test_chinese_and_japanese_become_pairs_of_characters_without_intl(): void {
		$this->assertSame( [ '東京', '京都', 'ラー', 'ーメ', 'メン' ], ( new Tokenizer( false ) )->tokens( '東京都 ラーメン' ) );
	}

	public function test_a_run_of_chinese_in_a_latin_word_is_split_off(): void {
		$this->assertSame( [ 'iphone', '用户' ], ( new Tokenizer( false ) )->tokens( 'iPhone用户' ) );
	}

	public function test_thai_stays_whole_without_intl(): void {
		$this->assertSame( [ 'ภาษาไทย' ], ( new Tokenizer( false ) )->tokens( 'ภาษาไทย' ) );
	}

	public function test_icu_finds_the_words_of_japanese_and_thai(): void {
		$tokenizer = new Tokenizer( true );
		if ( ! $tokenizer->uses_intl() ) {
			$this->markTestSkipped( 'Needs intl.' );
		}

		$japanese = $tokenizer->tokens( '東京都に住んでいます。ラーメンが好きです', 'ja' );
		$this->assertContains( '東京', $japanese );
		$this->assertContains( 'ラーメン', $japanese );
		$this->assertNotContains( '東京都に住んでいます', $japanese );

		$this->assertSame( [ 'ภาษา', 'ไทย' ], $tokenizer->tokens( 'ภาษาไทย', 'th' ) );
	}

	public function test_full_width_letters_are_plain_letters_with_intl(): void {
		$tokenizer = new Tokenizer( true );
		if ( ! $tokenizer->uses_intl() ) {
			$this->markTestSkipped( 'Needs intl.' );
		}

		$this->assertSame( [ 'fullwidth', 'file' ], $tokenizer->tokens( 'Ｆｕｌｌｗｉｄｔｈ ﬁle' ) );
	}

	/**
	 * Single words, normalized for lists such as the ignored words.
	 *
	 * @dataProvider words
	 *
	 * @param string $word     The word.
	 * @param string $expected The normalized word, or an empty string.
	 */
	public function test_a_single_word( string $word, string $expected ): void {
		$this->assertSame( $expected, ( new Tokenizer( false ) )->word( $word ) );
	}

	/**
	 * Words and their normalized form.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function words(): array {
		return [
			'a plain word'                     => [ 'The', 'the' ],
			'an apostrophe goes'               => [ "Don't", 'dont' ],
			'accents are folded'               => [ 'Für', 'fuer' ],
			'surrounding space is trimmed'     => [ ' word ', 'word' ],
			'two words are not one word'       => [ 'a lot', '' ],
			'a broken contraction is no word'  => [ "'ll", '' ],
			'punctuation is no word'           => [ '!', '' ],
			'one character is too short'       => [ 'a', '' ],
			'an entity is decoded'             => [ 'caf&eacute;', 'cafe' ],
			'japanese is not split'            => [ 'あのかた', 'あのかた' ],
		];
	}
}
