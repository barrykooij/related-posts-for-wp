<?php
/**
 * The quality test case class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Quality;

use LV2\WordPress\RelatedPostsForWP\Words\Tokenizer;

/**
 * Runs an algorithm on every corpus that was built, and reports how good its related posts are.
 *
 * Per corpus: import the posts into the test site (in the test's transaction, so nothing stays), cache the words of
 * every post, ask for the 5 related posts of every post, and compute the metrics. The result is written to the results
 * folder and compared with the baseline in the summary. With RP4WP_QUALITY_RECORD=1 the result becomes the baseline;
 * with RP4WP_QUALITY_GATE=1 the test fails when the result is not better than the baseline (see gate()).
 *
 * The free plugin and the premium add-on each extend this class with their algorithm.
 */
abstract class QualityTestCase extends \WP_UnitTestCase {

	/**
	 * How many related posts are asked for per post.
	 */
	private const LIMIT = 5;

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
	 * Prepare the site for a corpus, before its posts are imported; for the settings of an edition.
	 *
	 * @param Corpus $corpus The corpus.
	 *
	 * @return void
	 */
	protected function prepare( Corpus $corpus ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- For subclasses.
	}

	/**
	 * Start with an empty word cache; the WordPress test suite only cleans up its own tables.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		self::truncate_cache();
		parent::set_up_before_class();
	}

	/**
	 * Leave an empty word cache.
	 *
	 * @return void
	 */
	public static function tear_down_after_class() {
		parent::tear_down_after_class();
		self::truncate_cache();
	}

	/**
	 * The corpora that were built.
	 *
	 * @return array<string, array{string}>
	 */
	public static function corpora(): array {
		$cases = [];
		foreach ( Corpus::available() as $name ) {
			$cases[ $name ] = [ $name ];
		}

		return [] === $cases ? [ 'no corpora' => [ '' ] ] : $cases;
	}

	/**
	 * Measure the algorithm on a corpus.
	 *
	 * @dataProvider corpora
	 *
	 * @param string $name The corpus.
	 *
	 * @return void
	 */
	public function test_corpus( string $name ): void {
		if ( '' === $name ) {
			$this->markTestSkipped( 'No corpora were built; run `npm run quality:prepare` first.' );
		}

		ini_set( 'memory_limit', '2048M' ); // phpcs:ignore WordPress.PHP.IniSet.memory_limit_Disallowed -- The large corpora need it.
		set_time_limit( 0 );

		$corpus    = Corpus::load( $name );
		$algorithm = static::algorithm();
		$locale    = $corpus->locale;

		add_filter(
			'locale',
			static function () use ( $locale ): string {
				return $locale;
			}
		);

		if ( null !== $corpus->permalink_structure ) {
			$this->set_permalink_structure( $corpus->permalink_structure );
		}

		// The words a server without intl gets.
		if ( '1' === getenv( 'RP4WP_QUALITY_NO_INTL' ) ) {
			add_filter( 'rp4wp_tokenizer_use_intl', '__return_false' );
		}

		$this->prepare( $corpus );

		$author = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $author );

		$ids      = ( new Importer() )->import( $corpus, $author );
		$post_ids = array_values( $ids );

		$start = microtime( true );
		$algorithm->index( $post_ids );
		$index_ms = ( microtime( true ) - $start ) * 1000;

		$rankings = [];
		$start    = microtime( true );
		foreach ( $post_ids as $post_id ) {
			$rankings[ $post_id ] = $algorithm->related( $post_id, self::LIMIT );
		}
		$related_ms = ( microtime( true ) - $start ) * 1000;

		$relevant = [];
		foreach ( $corpus->relevant() as $corpus_id => $related_ids ) {
			if ( isset( $ids[ $corpus_id ] ) ) {
				$relevant[ $ids[ $corpus_id ] ] = array_values( array_intersect_key( $ids, array_flip( $related_ids ) ) );
			}
		}

		$tokens = [];
		$titles = [];
		foreach ( $corpus->posts as $post ) {
			$tokens[ $ids[ $post['id'] ] ] = $algorithm->tokens( $ids[ $post['id'] ] );
			$titles[ $ids[ $post['id'] ] ] = $post['title'];
		}

		$metrics = Metrics::compute( $post_ids, $rankings, $relevant, $tokens, $titles, $corpus->stop_words() );

		$metrics['posts']               = count( $post_ids );
		$metrics['index_ms_per_post']   = round( $index_ms / max( 1, count( $post_ids ) ), 2 );
		$metrics['related_ms_per_post'] = round( $related_ms / max( 1, count( $post_ids ) ), 2 );

		$result = $this->result( $algorithm, $corpus, $metrics );
		$report = new Report( static::plugin_root() );
		$report->write( $result );

		if ( '1' === getenv( 'RP4WP_QUALITY_RECORD' ) ) {
			$report->record( $result );
		}

		$report->summarize();

		$this->assertSame( count( $corpus->posts ), count( $post_ids ), 'Every post of the corpus is imported.' );

		foreach ( [ 'precision_at_3', 'precision_at_5', 'success_at_3', 'full_lists', 'coverage', 'hub_share', 'symmetry', 'title_share' ] as $metric ) {
			if ( null !== $metrics[ $metric ] ) {
				$this->assertGreaterThanOrEqual( 0, $metrics[ $metric ], $metric );
				$this->assertLessThanOrEqual( 1, $metrics[ $metric ], $metric );
			}
		}

		if ( '1' === getenv( 'RP4WP_QUALITY_GATE' ) ) {
			$this->gate( $metrics, $report->baseline( $algorithm->name(), $corpus->name ) );
		}

		if ( null !== $corpus->permalink_structure ) {
			$this->set_permalink_structure( '' );
		}
	}

	/**
	 * The gate a phase of the quality plan has to pass: precision at 3 is higher than the baseline, coverage is not
	 * lower (by more than a point), and less than 1 percent of the stored words are stop words.
	 *
	 * @param array<string, float|int|null> $metrics  The metrics of this run.
	 * @param array<string, mixed>|null     $baseline The baseline.
	 *
	 * @return void
	 */
	private function gate( array $metrics, ?array $baseline ): void {
		$this->assertNotNull( $baseline, 'The gate needs a recorded baseline.' );

		$was = (array) ( $baseline['metrics'] ?? [] );

		if ( null !== $metrics['precision_at_3'] && isset( $was['precision_at_3'] ) ) {
			$this->assertGreaterThan( $was['precision_at_3'], $metrics['precision_at_3'], 'Precision at 3 is higher than the baseline.' );
		}

		if ( null !== $metrics['coverage'] && isset( $was['coverage'] ) ) {
			$this->assertGreaterThanOrEqual( $was['coverage'] - 0.01, $metrics['coverage'], 'Coverage is not lower than the baseline.' );
		}

		if ( null !== $metrics['stop_word_leak'] ) {
			$this->assertLessThan( 0.01, $metrics['stop_word_leak'], 'Less than 1 percent of the stored words are stop words.' );
		}
	}

	/**
	 * The result of a run.
	 *
	 * @param Algorithm                     $algorithm The algorithm.
	 * @param Corpus                        $corpus    The corpus.
	 * @param array<string, float|int|null> $metrics   The metrics.
	 *
	 * @return array<string, mixed>
	 */
	private function result( Algorithm $algorithm, Corpus $corpus, array $metrics ): array {
		global $wpdb;

		$order = array_search( $corpus->name, array_column( self::corpora(), 0 ), true );

		return [
			'kind'         => 'quality',
			'algorithm'    => $algorithm->name(),
			'corpus'       => $corpus->name,
			'corpus_title' => $corpus->title,
			'locale'       => $corpus->locale,
			'order'        => false === $order ? 0 : $order,
			'run'          => gmdate( 'Y-m-d H:i:s' ),
			'environment'  => [
				'php'       => PHP_VERSION,
				'database'  => $wpdb->db_server_info(),
				'wordpress' => get_bloginfo( 'version' ),
				'intl'      => ( new Tokenizer() )->uses_intl(),
			],
			'metrics'      => $metrics,
		];
	}

	/**
	 * Empty the word cache table.
	 *
	 * @return void
	 */
	private static function truncate_cache(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}rp4wp_cache" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test cleanup of the plugin's own table.
	}
}
