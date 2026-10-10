<?php
/**
 * The quality report class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Quality;

/**
 * Writes the results of the quality harness, records baselines, and summarizes the results against the baselines.
 *
 * Results go to `artifacts/quality/results/<algorithm>-<corpus>.json` of the plugin under test, with a `summary.md` of
 * all results there. Baselines are committed in `tests/Quality/baseline/`.
 */
final class Report {

	/**
	 * The columns of the summary of a corpus run: metric => heading.
	 */
	private const QUALITY_COLUMNS = [
		'posts'                 => 'Posts',
		'evaluated'             => 'Labelled',
		'precision_at_3'        => 'P@3',
		'precision_at_5'        => 'P@5',
		'random_precision_at_3' => 'Random P@3',
		'success_at_3'          => 'Success@3',
		'full_lists'            => 'Full lists',
		'coverage'              => 'Coverage',
		'hub_share'             => 'Hub share',
		'max_inbound'           => 'Max inbound',
		'symmetry'              => 'Symmetry',
		'stop_word_leak'        => 'Stop-word leak',
		'title_share'           => 'Title share',
		'words_per_post'        => 'Words/post',
		'index_ms_per_post'     => 'Index ms/post',
		'related_ms_per_post'   => 'Related ms/post',
	];

	/**
	 * The columns of the summary of a scale run.
	 */
	private const SCALE_COLUMNS = [
		'posts'             => 'Posts',
		'related_ms_median' => 'Related ms (median)',
		'related_ms_p90'    => 'Related ms (p90)',
		'related_ms_max'    => 'Related ms (max)',
	];

	/**
	 * The root of the plugin under test.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * Set up.
	 *
	 * @param string $root The root of the plugin under test.
	 */
	public function __construct( string $root ) {
		$this->root = rtrim( $root, '/' );
	}

	/**
	 * Write the result of a run.
	 *
	 * @param array<string, mixed> $result The result.
	 *
	 * @return void
	 */
	public function write( array $result ): void {
		self::put( $this->results_directory() . '/' . self::file_name( $result ), $result );
	}

	/**
	 * Record the result of a run as the baseline.
	 *
	 * @param array<string, mixed> $result The result.
	 *
	 * @return void
	 */
	public function record( array $result ): void {
		self::put( $this->baseline_directory() . '/' . self::file_name( $result ), $result );
	}

	/**
	 * The baseline of an algorithm on a corpus, or null when none was recorded.
	 *
	 * @param string $algorithm The algorithm.
	 * @param string $corpus    The corpus.
	 *
	 * @return array<string, mixed>|null
	 */
	public function baseline( string $algorithm, string $corpus ): ?array {
		return self::get( $this->baseline_directory() . '/' . $algorithm . '-' . $corpus . '.json' );
	}

	/**
	 * Write `summary.md` from every result in the results folder, with the difference to its baseline.
	 *
	 * @return string The summary.
	 */
	public function summarize(): string {
		$quality = [];
		$scale   = [];

		foreach ( (array) glob( $this->results_directory() . '/*.json' ) as $file ) {
			$result = self::get( (string) $file );
			if ( null === $result ) {
				continue;
			}

			if ( 'scale' === ( $result['kind'] ?? 'quality' ) ) {
				$scale[] = $result;
			} else {
				$quality[] = $result;
			}
		}

		$summary = "# Related posts quality\n\nEach value shows the difference to the recorded baseline in brackets.\n";

		foreach ( self::by_algorithm( $quality ) as $algorithm => $results ) {
			$summary .= "\n## {$algorithm}: corpora\n\n" . $this->table( $results, self::QUALITY_COLUMNS );
		}

		foreach ( self::by_algorithm( $scale ) as $algorithm => $results ) {
			$summary .= "\n## {$algorithm}: scale\n\n" . $this->table( $results, self::SCALE_COLUMNS );
		}

		file_put_contents( $this->results_directory() . '/summary.md', $summary ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A local report.

		return $summary;
	}

	/**
	 * The folder with the results.
	 *
	 * @return string
	 */
	public function results_directory(): string {
		return $this->root . '/artifacts/quality/results';
	}

	/**
	 * The folder with the baselines.
	 *
	 * @return string
	 */
	public function baseline_directory(): string {
		return $this->root . '/tests/Quality/baseline';
	}

	/**
	 * A markdown table of results, one row per corpus.
	 *
	 * @param array<int, array<string, mixed>> $results The results.
	 * @param array<string, string>            $columns Metric => heading.
	 *
	 * @return string
	 */
	private function table( array $results, array $columns ): string {
		usort(
			$results,
			static function ( array $a, array $b ): int {
				return [ (int) ( $a['order'] ?? 0 ), (string) $a['corpus'] ] <=> [ (int) ( $b['order'] ?? 0 ), (string) $b['corpus'] ];
			}
		);

		$table  = '| Corpus | ' . implode( ' | ', $columns ) . " |\n";
		$table .= '|---|' . str_repeat( '---|', count( $columns ) ) . "\n";

		foreach ( $results as $result ) {
			$baseline = $this->baseline( (string) $result['algorithm'], (string) $result['corpus'] );
			$cells    = [];

			foreach ( array_keys( $columns ) as $metric ) {
				$value = $result['metrics'][ $metric ] ?? null;
				$was   = null === $baseline ? null : ( $baseline['metrics'][ $metric ] ?? null );
				$cells[] = self::cell( $value, $was );
			}

			$table .= '| ' . $result['corpus'] . ' | ' . implode( ' | ', $cells ) . " |\n";
		}

		return $table;
	}

	/**
	 * A value with its difference to the baseline.
	 *
	 * @param mixed $value The value.
	 * @param mixed $was   The baseline value.
	 *
	 * @return string
	 */
	private static function cell( $value, $was ): string {
		if ( null === $value ) {
			return 'n/a';
		}

		$text = is_float( $value ) ? self::number( $value ) : (string) $value;

		if ( is_numeric( $was ) && is_numeric( $value ) && abs( (float) $value - (float) $was ) > 0.00005 ) {
			$difference = (float) $value - (float) $was;
			$text      .= ' (' . ( $difference > 0 ? '+' : '' ) . self::number( $difference ) . ')';
		}

		return $text;
	}

	/**
	 * A number with three decimals, or one above 10.
	 *
	 * @param float $number The number.
	 *
	 * @return string
	 */
	private static function number( float $number ): string {
		return abs( $number ) >= 10 ? number_format( $number, 1, '.', '' ) : number_format( $number, 3, '.', '' );
	}

	/**
	 * Results grouped by algorithm.
	 *
	 * @param array<int, array<string, mixed>> $results The results.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private static function by_algorithm( array $results ): array {
		$grouped = [];
		foreach ( $results as $result ) {
			$grouped[ (string) $result['algorithm'] ][] = $result;
		}

		ksort( $grouped );

		return $grouped;
	}

	/**
	 * The file name of a result.
	 *
	 * @param array<string, mixed> $result The result.
	 *
	 * @return string
	 */
	private static function file_name( array $result ): string {
		return sanitize_file_name( $result['algorithm'] . '-' . $result['corpus'] ) . '.json';
	}

	/**
	 * Write JSON to a file, creating its folder.
	 *
	 * @param string               $file The file.
	 * @param array<string, mixed> $data The data.
	 *
	 * @return void
	 */
	private static function put( string $file, array $data ): void {
		wp_mkdir_p( dirname( $file ) );
		file_put_contents( $file, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A local report.
	}

	/**
	 * Read JSON from a file.
	 *
	 * @param string $file The file.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function get( string $file ): ?array {
		if ( ! is_file( $file ) ) {
			return null;
		}

		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file.

		return is_array( $data ) ? $data : null;
	}
}
