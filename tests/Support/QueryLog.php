<?php
/**
 * The query log class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Support;

/**
 * Records the database queries that a piece of code sends, through the `query` filter of wpdb.
 */
final class QueryLog {

	/**
	 * The queries, in the order they were sent.
	 *
	 * @var string[]
	 */
	public array $queries = [];

	/**
	 * What the callback returned.
	 *
	 * @var mixed
	 */
	public $result;

	/**
	 * Run a callback and record the queries it sends.
	 *
	 * @param callable $callback The code to measure.
	 *
	 * @return self
	 */
	public static function of( callable $callback ): self {
		$log    = new self();
		$record = static function ( $query ) use ( $log ) {
			$log->queries[] = (string) $query;

			return $query;
		};

		add_filter( 'query', $record, 1 );
		try {
			$log->result = $callback();
		} finally {
			remove_filter( 'query', $record, 1 );
		}

		return $log;
	}

	/**
	 * How many queries were sent.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->queries );
	}

	/**
	 * The queries as text, one per line, for an assertion message.
	 *
	 * @return string
	 */
	public function __toString(): string {
		return implode( PHP_EOL, $this->queries );
	}
}
