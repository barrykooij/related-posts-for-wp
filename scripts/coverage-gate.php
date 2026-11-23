<?php
/**
 * Prints the line coverage of a Clover report as one line of Markdown, for the summary of a CI job, and fails when it
 * is below the minimum of the suite in coverage-gate.json.
 *
 * Usage: php scripts/coverage-gate.php <clover.xml> <label> <suite>
 *
 * The minimums only go up: a change that raises the coverage may raise its minimum, and lowering one needs a reason in
 * the pull request.
 *
 * @package RelatedPostsForWP
 */

// phpcs:disable WordPress.WP.AlternativeFunctions,WordPress.Security.EscapeOutput,WordPress.NamingConventions.PrefixAllGlobals -- A command line script, outside WordPress.

if ( $argc < 4 ) {
	fwrite( STDERR, "Usage: php scripts/coverage-gate.php <clover.xml> <label> <suite>\n" );
	exit( 2 );
}

$file  = $argv[1];
$label = $argv[2];
$suite = $argv[3];

if ( ! is_readable( $file ) ) {
	fwrite( STDERR, "No coverage report at {$file}.\n" );
	exit( 1 );
}

$clover = simplexml_load_file( $file );
if ( false === $clover || ! isset( $clover->project->metrics ) ) {
	fwrite( STDERR, "{$file} is not a Clover report.\n" );
	exit( 1 );
}

$gates = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/coverage-gate.json' ), true );
if ( ! is_array( $gates ) || ! isset( $gates[ $suite ] ) || ! is_numeric( $gates[ $suite ] ) ) {
	fwrite( STDERR, "coverage-gate.json has no minimum for {$suite}.\n" );
	exit( 2 );
}

$minimum    = (float) $gates[ $suite ];
$metrics    = $clover->project->metrics;
$statements = (int) $metrics['statements'];
$covered    = (int) $metrics['coveredstatements'];
$percent    = $statements > 0 ? 100 * $covered / $statements : 0.0;

printf( "- %s: %.2f%% of lines (%d of %d); the minimum is %s%%\n", $label, $percent, $covered, $statements, $minimum );

if ( $percent < $minimum ) {
	fwrite( STDERR, sprintf( "::error::The coverage of the %s is %.2f%%, below the minimum of %s%% in coverage-gate.json.\n", $label, $percent, $minimum ) );
	exit( 1 );
}
