<?php
/**
 * Normalize the lists of ignored words in resources/ignored-words/: every word the way the tokenizer normalizes words,
 * without the HTML artifacts and the broken halves of words with an apostrophe, sorted and without duplicates.
 *
 * Usage: php scripts/ignored-words.php [--import=<folder>]
 * With --import, the lists of that folder (files named by language code) replace the lists of the plugin first.
 *
 * Not shipped; run from the root of the plugin.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Words\Tokenizer;

// The word lists stop without it.
define( 'ABSPATH', __DIR__ . '/' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- The constant of WordPress that the word lists check.

require dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * Words that are only in the lists because the tokenizer of 2.x made them out of HTML: entities ("&amp;", "&#39;") and
 * tags. The tokenizer decodes entities and removes tags now.
 */
const RP4WP_HTML_ARTIFACTS = [ 'amp', 'gt', 'lt', 'div', 'nbsp', 'quot', '39', 'apos', 'hellip', 'ndash', 'mdash', 'rsquo', 'lsquo', 'rdquo', 'ldquo' ];

/**
 * Words before "n't" that are words of their own, and stay.
 */
const RP4WP_WORDS_OF_THEIR_OWN = [ 'can', 'won' ];

$rp4wp_options   = getopt( '', [ 'import:' ] );
$rp4wp_directory = dirname( __DIR__ ) . '/resources/ignored-words';
$rp4wp_source    = isset( $rp4wp_options['import'] ) ? rtrim( (string) $rp4wp_options['import'], '/' ) : $rp4wp_directory;
$rp4wp_tokenizer = new Tokenizer( true );

if ( ! $rp4wp_tokenizer->uses_intl() ) {
	fwrite( STDERR, "The intl extension is needed, so the lists are normalized like on a server with it.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
	exit( 1 );
}

foreach ( (array) glob( $rp4wp_source . '/[a-z][a-z].php' ) as $rp4wp_file ) {
	$rp4wp_language = basename( (string) $rp4wp_file, '.php' );
	$rp4wp_raw      = array_map( 'strval', (array) require $rp4wp_file );
	$rp4wp_halves   = [];

	// "don" and "doesn" were the halves of "don't" and "doesn't"; the tokenizer keeps "dont" and "doesnt" whole now.
	// "can" and "won" are words of their own as well.
	foreach ( $rp4wp_raw as $rp4wp_word ) {
		if ( 1 === preg_match( "/^(\\p{L}+n)['\x{2019}]t$/u", $rp4wp_word, $rp4wp_match ) && ! in_array( mb_strtolower( $rp4wp_match[1] ), RP4WP_WORDS_OF_THEIR_OWN, true ) ) {
			$rp4wp_halves[ mb_strtolower( $rp4wp_match[1] ) ] = true;
		}
	}

	$rp4wp_words = [];
	foreach ( $rp4wp_raw as $rp4wp_word ) {
		// Words mangled by an encoding error in an older list.
		if ( false !== strpos( $rp4wp_word, 'Ã' ) ) {
			continue;
		}

		$rp4wp_word = $rp4wp_tokenizer->word( $rp4wp_word );
		if ( '' === $rp4wp_word || in_array( $rp4wp_word, RP4WP_HTML_ARTIFACTS, true ) || isset( $rp4wp_halves[ $rp4wp_word ] ) ) {
			continue;
		}

		$rp4wp_words[ $rp4wp_word ] = true;
	}

	$rp4wp_words = array_map( 'strval', array_keys( $rp4wp_words ) );
	sort( $rp4wp_words, SORT_STRING );

	$rp4wp_php = "<?php\n\nif ( ! defined( 'ABSPATH' ) ) {\n\texit;\n} // Exit if accessed directly\n\nreturn array( "
		. implode(
			', ',
			array_map(
				static function ( $rp4wp_word ) {
					return var_export( $rp4wp_word, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Writes the word as PHP.
				},
				$rp4wp_words
			)
		)
		. " );\n";

	file_put_contents( "{$rp4wp_directory}/{$rp4wp_language}.php", $rp4wp_php ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A development script.

	echo sprintf( "%s: %d words (%d in the source)\n", $rp4wp_language, count( $rp4wp_words ), count( $rp4wp_raw ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
}
