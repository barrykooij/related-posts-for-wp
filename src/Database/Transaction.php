<?php
/**
 * The transaction class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Database;

/**
 * Runs a few writes as one: they all happen, or none do, so a visitor never sees half of them.
 *
 * Inside a transaction someone else opened (autocommit off, as in the WordPress test suite), or inside one of its own,
 * a savepoint is used instead, because a new transaction would commit the open one. On a MyISAM table nothing is
 * atomic; callers order their writes so the result is still safe to show halfway.
 */
final class Transaction {

	/**
	 * How many transactions of this class are open in this request.
	 *
	 * @var int
	 */
	private static int $depth = 0;

	/**
	 * Run the work in a transaction. A write that fails, or anything the work throws, rolls it back.
	 *
	 * @template T
	 *
	 * @param callable(): T $work The writes.
	 *
	 * @return T What the work returns.
	 *
	 * @throws \Throwable What the work throws, after the rollback.
	 */
	public static function run( callable $work ) {
		global $wpdb;

		/**
		 * Filters whether writes that belong together, such as the new links of a post, run in a database
		 * transaction. Turn it off for a database that does not support transactions well; the writes are ordered so
		 * a post never shows fewer related posts halfway.
		 *
		 * @since 3.0.0
		 *
		 * @param bool $use Whether to use a transaction. Default true.
		 */
		if ( ! apply_filters( 'rp4wp_use_transactions', true ) ) {
			return $work();
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Transaction control has no API; the savepoint name comes from this class.
		$savepoint = self::$depth > 0 || '0' === (string) $wpdb->get_var( 'SELECT @@autocommit' );
		$name      = 'rp4wp_' . self::$depth;

		$wpdb->query( $savepoint ? "SAVEPOINT {$name}" : 'START TRANSACTION' );
		++self::$depth;

		try {
			$result = $work();
		} catch ( \Throwable $error ) {
			--self::$depth;
			$wpdb->query( $savepoint ? "ROLLBACK TO SAVEPOINT {$name}" : 'ROLLBACK' );

			throw $error;
		}

		--self::$depth;
		$wpdb->query( $savepoint ? "RELEASE SAVEPOINT {$name}" : 'COMMIT' );
		// phpcs:enable

		return $result;
	}

	/**
	 * Run a write, and throw when it fails, so run() rolls back.
	 *
	 * @param string $sql The query, prepared.
	 *
	 * @return int The rows it changed.
	 *
	 * @throws \RuntimeException When the query fails.
	 */
	public static function query( string $sql ): int {
		global $wpdb;

		$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- The callers prepare it.

		if ( false === $result ) {
			throw new \RuntimeException( esc_html( '' !== $wpdb->last_error ? $wpdb->last_error : 'A database write failed.' ) );
		}

		return (int) $result;
	}
}
