<?php
/**
 * Prints the line coverage of a Clover report as one line of Markdown, for the summary of a CI job.
 *
 * Usage: php scripts/coverage-summary.php <clover.xml> <label>
 *
 * @package RelatedPostsForWP
 */

// phpcs:disable WordPress.WP.AlternativeFunctions,WordPress.Security.EscapeOutput,WordPress.NamingConventions.PrefixAllGlobals -- A command line script, outside WordPress.

if ( $argc < 3 ) {
	fwrite( STDERR, "Usage: php scripts/coverage-summary.php <clover.xml> <label>\n" );
	exit( 2 );
}

$file  = $argv[1];
$label = $argv[2];

if ( ! is_readable( $file ) ) {
	fwrite( STDERR, "No coverage report at {$file}.\n" );
	exit( 1 );
}

$clover = simplexml_load_file( $file );
if ( false === $clover || ! isset( $clover->project->metrics ) ) {
	fwrite( STDERR, "{$file} is not a Clover report.\n" );
	exit( 1 );
}

$metrics    = $clover->project->metrics;
$statements = (int) $metrics['statements'];
$covered    = (int) $metrics['coveredstatements'];
$percent    = $statements > 0 ? 100 * $covered / $statements : 0.0;

printf( "- %s: %.1f%% of lines (%d of %d)\n", $label, $percent, $covered, $statements );
