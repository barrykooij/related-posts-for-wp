<?php
/**
 * Compares the results of the variants of an algorithm with its baseline, as Markdown: precision at 3 and coverage per
 * corpus, and whether the variant passes the gate of term frequency saturation (the 3.1 plan, phase 5): higher P@3 on
 * the blog and BBC, and no loss of P@3 or coverage on any corpus.
 *
 * Usage: php scripts/quality/compare-variants.php <plugin root> <algorithm>
 *
 * @package RelatedPostsForWP
 */

// phpcs:disable WordPress.WP.AlternativeFunctions,WordPress.Security.EscapeOutput,WordPress.NamingConventions.PrefixAllGlobals -- A command line script, outside WordPress.

$root      = $argv[1] ?? dirname( __DIR__, 2 );
$algorithm = $argv[2] ?? 'free';
$corpora   = [ 'golden', 'bbc', 'gnad', 'livedoor', 'blog', 'blog-nl' ];
$gain_on   = [ 'blog', 'bbc' ];

$read = static function ( string $file ): ?array {
	if ( ! is_readable( $file ) ) {
		return null;
	}

	$data = json_decode( (string) file_get_contents( $file ), true );

	return is_array( $data ) && isset( $data['metrics'] ) ? $data['metrics'] : null;
};

$baseline = [];
foreach ( $corpora as $corpus ) {
	$baseline[ $corpus ] = $read( "{$root}/tests/Quality/baseline/{$algorithm}-{$corpus}.json" );
}

// The report drops the + from the file names: free+tf-type-2 is in freetf-type-2-bbc.json.
$variants = [];
foreach ( (array) glob( "{$root}/artifacts/quality/results/{$algorithm}*.json" ) as $file ) {
	$name = basename( (string) $file, '.json' );
	foreach ( $corpora as $corpus ) {
		$suffix = '-' . $corpus;
		$rest   = substr( $name, strlen( $algorithm ), -strlen( $suffix ) );
		if ( substr( $name, -strlen( $suffix ) ) === $suffix && '' !== ltrim( $rest, '+' ) && ! preg_match( '/-blog$/', $rest ) ) {
			$variants[ ltrim( $rest, '+' ) ][ $corpus ] = $read( (string) $file );
		}
	}
}
ksort( $variants, SORT_NATURAL );

echo "# Term frequency saturation: {$algorithm}\n\n";
echo 'P@3 per corpus, with the difference to the baseline in brackets; coverage only where it changed. Gate: higher P@3 on ' . implode( ' and ', $gain_on ) . ", no loss of P@3 or coverage anywhere.\n\n";
echo '| Variant | ' . implode( ' | ', $corpora ) . " | Gate |\n";
echo '|---|' . str_repeat( '---|', count( $corpora ) ) . "---|\n";

$cells = [];
foreach ( $corpora as $corpus ) {
	$cells[] = null === $baseline[ $corpus ] ? 'n/a' : sprintf( '%.3f', $baseline[ $corpus ]['precision_at_3'] );
}
echo '| baseline | ' . implode( ' | ', $cells ) . " | |\n";

foreach ( $variants as $variant => $results ) {
	$cells = [];
	$pass  = true;
	foreach ( $corpora as $corpus ) {
		$result = $results[ $corpus ] ?? null;
		$base   = $baseline[ $corpus ];
		if ( null === $result || null === $base ) {
			$cells[] = 'n/a';
			$pass    = false;
			continue;
		}

		$delta    = $result['precision_at_3'] - $base['precision_at_3'];
		$coverage = $result['coverage'] - $base['coverage'];
		$cell     = sprintf( '%.3f (%+.3f)', $result['precision_at_3'], $delta );
		if ( abs( $coverage ) >= 0.0005 ) {
			$cell .= sprintf( ', cov %+.3f', $coverage );
		}
		$cells[] = $cell;

		if ( in_array( $corpus, $gain_on, true ) ? $delta <= 0.0 : $delta < 0.0 ) {
			$pass = false;
		}
		if ( $coverage < -0.0005 ) {
			$pass = false;
		}
	}

	echo "| {$variant} | " . implode( ' | ', $cells ) . ' | ' . ( $pass ? 'pass' : '' ) . " |\n";
}
