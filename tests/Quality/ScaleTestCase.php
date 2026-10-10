<?php
/**
 * The scale test case class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Quality;

/**
 * How fast an algorithm finds related posts on a large site: 50,000 posts (RP4WP_QUALITY_SCALE to change it) with 25
 * words each (the default since 3.0), drawn from Zipf's law over 20,000 words, so a few words are in a large share of
 * the posts, as on a real site. The first word counts like a title word; the plugin counts the document frequencies
 * and weighs the words. The posts and their words are written straight to the database once for the class, outside the transaction of
 * the test, and the tables are analyzed, so the database plans the queries with real statistics, as on a site that is
 * up; the WordPress test suite removes the posts after the class. Then the related posts of 25 random posts are timed.
 *
 * The same setup as the scale benchmark of the research (`project/research-scripts/related-posts/scale-bench.php`),
 * but through the plugin's own code.
 */
abstract class ScaleTestCase extends \WP_UnitTestCase {

	/**
	 * The number of different words.
	 */
	private const VOCABULARY = 20000;

	/**
	 * The words per post.
	 */
	private const WORDS = 25;

	/**
	 * How many posts are timed.
	 */
	private const SAMPLES = 25;

	/**
	 * The ID of the first synthetic post; the others follow it. 0 when there are none.
	 *
	 * @var int
	 */
	private static int $first = 0;

	/**
	 * How many synthetic posts there are.
	 *
	 * @var int
	 */
	private static int $count = 0;

	/**
	 * How long writing them took, in seconds.
	 *
	 * @var float
	 */
	private static float $seed_seconds = 0.0;

	/**
	 * The algorithm to measure.
	 *
	 * @return Algorithm
	 */
	abstract protected static function algorithm(): Algorithm;

	/**
	 * The root of the plugin under test, for the results and the baselines.
	 *
	 * @return string
	 */
	abstract protected static function plugin_root(): string;

	/**
	 * Write the synthetic posts and their words, commit them, and analyze the tables. The WordPress test suite commits
	 * what this writes, and removes the posts after the class.
	 *
	 * @return void
	 */
	public static function wpSetUpBeforeClass(): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- The name the WordPress test suite calls.
		global $wpdb;

		$setting     = getenv( 'RP4WP_QUALITY_SCALE' );
		self::$count = false === $setting || '' === $setting ? 50000 : (int) $setting;
		self::$first = 0;

		if ( self::$count < 1 ) {
			return;
		}

		ini_set( 'memory_limit', '2048M' ); // phpcs:ignore WordPress.PHP.IniSet.memory_limit_Disallowed -- The seeding needs it.
		set_time_limit( 0 );

		$start       = microtime( true );
		self::$first = self::seed( static::algorithm(), self::$count );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Statistics of the test tables.
		$wpdb->query( 'COMMIT' );
		$wpdb->query( "ANALYZE TABLE {$wpdb->posts}, {$wpdb->postmeta}, {$wpdb->prefix}rp4wp_cache, {$wpdb->prefix}rp4wp_words" );
		// phpcs:enable

		self::$seed_seconds = microtime( true ) - $start;
	}

	/**
	 * Empty the word cache too, and analyze the empty tables, so the next class starts with true statistics.
	 *
	 * @return void
	 */
	public static function tear_down_after_class() {
		global $wpdb;

		parent::tear_down_after_class();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Cleanup of the test tables.
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}rp4wp_cache" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}rp4wp_words" );
		delete_option( 'rp4wp_cached_posts' );
		$wpdb->query( "ANALYZE TABLE {$wpdb->posts}, {$wpdb->postmeta}, {$wpdb->prefix}rp4wp_cache, {$wpdb->prefix}rp4wp_words" );
		// phpcs:enable
	}

	/**
	 * Time the related posts of a large site.
	 *
	 * @group scale
	 *
	 * @return void
	 */
	public function test_scale(): void {
		if ( 0 === self::$first ) {
			$this->markTestSkipped( 'RP4WP_QUALITY_SCALE is 0.' );
		}

		$algorithm = static::algorithm();
		$count     = self::$count;
		$first     = self::$first;

		mt_srand( 7 );
		$samples = [];
		for ( $i = 0; $i < self::SAMPLES; $i++ ) {
			$samples[] = $first + mt_rand( 0, $count - 1 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- Seeded, so the same posts every run.
		}

		// Warm the buffer pool and the plugin's settings, like a site that is up.
		$algorithm->related( $samples[0], Metrics::SHOWN );

		$times = [];
		$found = 0;
		foreach ( $samples as $post_id ) {
			$start   = microtime( true );
			$found  += count( $algorithm->related( $post_id, Metrics::SHOWN ) );
			$times[] = ( microtime( true ) - $start ) * 1000;
		}

		sort( $times );

		$result = [
			'kind'      => 'scale',
			'algorithm' => $algorithm->name(),
			'corpus'    => 'synthetic-' . $count,
			'run'       => gmdate( 'Y-m-d H:i:s' ),
			'metrics'   => [
				'posts'             => $count,
				'related_ms_median' => round( $times[ (int) floor( count( $times ) / 2 ) ], 2 ),
				'related_ms_p90'    => round( $times[ (int) floor( count( $times ) * 0.9 ) ], 2 ),
				'related_ms_max'    => round( (float) end( $times ), 2 ),
				'seed_seconds'      => round( self::$seed_seconds, 1 ),
			],
		];

		$report = new Report( static::plugin_root() );
		$report->write( $result );

		if ( '1' === getenv( 'RP4WP_QUALITY_RECORD' ) ) {
			$report->record( $result );
		}

		$report->summarize();

		$this->assertGreaterThan( 0, $found, 'The sampled posts have related posts.' );
	}

	/**
	 * Write the posts and their words.
	 *
	 * @param Algorithm $algorithm The algorithm, which stores the words its own way.
	 * @param int       $count     The number of posts.
	 *
	 * @return int The ID of the first post; the others follow it.
	 */
	private static function seed( Algorithm $algorithm, int $count ): int {
		global $wpdb;

		$first = (int) $wpdb->get_var( "SELECT COALESCE( MAX( ID ), 0 ) + 1000 FROM {$wpdb->posts}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Seeding.

		mt_srand( 42 );
		$cumulative = [];
		$sum        = 0.0;
		for ( $rank = 1; $rank <= self::VOCABULARY; $rank++ ) {
			$sum         += 1 / $rank;
			$cumulative[] = $sum;
		}

		$posts   = [];
		$vectors = [];
		$last    = $count - 1;

		for ( $i = 0; $i < $count; $i++ ) {
			$post_id = $first + $i;
			$date    = gmdate( 'Y-m-d H:i:s', 1500000000 + $i * 600 );
			$posts[] = $wpdb->prepare( "(%d, 1, %s, %s, '', %s, '', 'publish', %s, '', '', %s, %s, '', 'post')", $post_id, $date, $date, 'Synthetic post ' . $post_id, 'synthetic-' . $post_id, $date, $date );

			$words  = [];
			$unique = 0;
			while ( $unique < self::WORDS ) {
				$words[ 'w' . self::zipf( $cumulative, $sum ) ] = true;
				$unique = count( $words );
			}

			$position = 0;
			foreach ( array_keys( $words ) as $word ) {
				$vectors[ $post_id ][ $word ] = 0 === $position++ ? 81 : 1 + mt_rand( 0, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- Seeded.
			}

			if ( count( $posts ) >= 2000 || $i === $last ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Each row is prepared above.
				$wpdb->query( "INSERT INTO {$wpdb->posts} (ID, post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_type) VALUES " . implode( ',', $posts ) );
				$algorithm->seed( $vectors );

				$posts   = [];
				$vectors = [];
			}
		}

		$algorithm->seeded();

		return $first;
	}

	/**
	 * A word rank drawn from Zipf's law.
	 *
	 * @param float[] $cumulative The cumulative weights of the ranks.
	 * @param float   $sum        Their total.
	 *
	 * @return int
	 */
	private static function zipf( array $cumulative, float $sum ): int {
		$target = mt_rand() / mt_getrandmax() * $sum; // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- Seeded.
		$low    = 0;
		$high   = count( $cumulative ) - 1;

		while ( $low < $high ) {
			$middle = ( $low + $high ) >> 1;
			if ( $cumulative[ $middle ] < $target ) {
				$low = $middle + 1;
			} else {
				$high = $middle;
			}
		}

		return $low + 1;
	}
}
