<?php
/**
 * The word extractor test file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Unit\Words;

use LV2\WordPress\RelatedPostsForWP\Words\Extractor;
use LV2\WordPress\RelatedPostsForWP\Words\IgnoredWords;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Character conversion. The full extraction is covered by the golden master in the integration suite.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Words\Extractor::convert_characters
 */
final class ExtractorTest extends TestCase {

	/**
	 * Conversions.
	 *
	 * @dataProvider conversions
	 *
	 * @param string $input    The input.
	 * @param string $expected The converted text.
	 */
	public function test_convert_characters( string $input, string $expected ): void {
		$this->assertSame( $expected, ( new Extractor( new IgnoredWords() ) )->convert_characters( $input ) );
	}

	/**
	 * Inputs and their conversion.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function conversions(): array {
		return [
			'plain words stay'                  => [ 'related posts', 'related posts' ],
			'accents are removed'               => [ 'crème brûlée à la façon', 'creme brulee a la facon' ],
			'ligatures become two letters'      => [ 'Æsop œuvre', 'AEsop oeuvre' ],
			'punctuation becomes a space'       => [ 'hello, world! (again)', 'hello  world   again ' ],
			'quotes and dashes become a space'  => [ "it's a well-known \"fact\"", 'it s a well known  fact ' ],
			'latin-1 input is converted first'  => [ "caf\xE9", 'cafe' ],
			'characters without plain form stay' => [ 'Ελληνικά 日本', 'Ελληνικά 日本' ],
			'emoji separate words'              => [ "cake\u{1F382}party \u{1F389}", 'cake party  ' ],
			'other 4-byte characters too'       => [ "\u{1D401}old \u{2000B}", ' old  ' ],
		];
	}
}
