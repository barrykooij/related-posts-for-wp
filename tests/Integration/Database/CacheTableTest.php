<?php
/**
 * The cache table migration test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Database;

use LV2\WordPress\RelatedPostsForWP\Database\Migration;
use LV2\WordPress\RelatedPostsForWP\Database\Migrator;
use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * The migration that gives the word cache table the shape of 3.0 (decision D48): utf8mb4, words of at most 64
 * characters compared exactly, the columns tf and version, and a covering index instead of premium's word indexes.
 *
 * The upgrade runs on a temporary table of the same name, which hides the real table in this connection; ALTER TABLE
 * ends the test's transaction, so nothing is written before it.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Install\Table
 */
final class CacheTableTest extends TestCase {

	public function tear_down(): void {
		global $wpdb;

		// Drops the temporary table (the test suite adds TEMPORARY), never the real one.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . Table::name() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Test cleanup.

		parent::tear_down();
	}

	public function test_the_table_has_the_shape_of_3_0(): void {
		$this->assertSame( 'varchar(64)', $this->column( 'word' )['Type'] );
		$this->assertSame( 'utf8mb4_bin', $this->column( 'word' )['Collation'] );
		$this->assertNotNull( $this->column( 'tf' ) );
		$this->assertNotNull( $this->column( 'version' ) );
		$this->assertSame( [ 'PRIMARY', 'word_cover' ], $this->indexes() );
	}

	public function test_the_table_of_2x_is_upgraded_and_back(): void {
		global $wpdb;

		$table = Table::name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- The table of 2.x, as premium 2.x left it.
		$wpdb->query(
			"CREATE TABLE `{$table}` (
				`post_id` bigint(20) unsigned NOT NULL,
				`word` varchar(255) CHARACTER SET utf8 NOT NULL,
				`weight` float unsigned NOT NULL,
				`post_type` varchar(20) CHARACTER SET utf8 NOT NULL,
				PRIMARY KEY (`post_id`,`word`), KEY `word` (`word`), KEY `word_2` (`word`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8"
		);
		$wpdb->query( "INSERT INTO `{$table}` (post_id, word, weight, post_type) VALUES (1, 'sourdough', 0.5, 'post'), (1, '" . str_repeat( 'x', 70 ) . "', 0.1, 'post')" );
		// phpcs:enable

		$migration = $this->migration();
		$this->assertTrue( $migration->up() );

		$this->assertSame( 'varchar(64)', $this->column( 'word' )['Type'] );
		$this->assertSame( 'utf8mb4_bin', $this->column( 'word' )['Collation'] );
		$this->assertSame( [ 'PRIMARY', 'word_cover' ], $this->indexes(), 'The word indexes of premium are gone.' );
		$this->assertSame( [ [ 'sourdough', '1' ] ], $this->rows(), 'The words stay with version 1; a word that is too long goes.' );
		$this->assertSame( '0', $this->column( 'version' )['Default'] );

		$this->assertTrue( $migration->up(), 'A second run does nothing.' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Test data.
		$wpdb->query( "INSERT INTO `{$table}` (post_id, word, weight, post_type, tf, version) VALUES (2, 'cake\u{1F382}', 0.5, 'post', 1, 2)" );

		$migration->down();

		$this->assertSame( 'varchar(255)', $this->column( 'word' )['Type'] );
		$this->assertMatchesRegularExpression( '/^utf8(mb3)?_/', (string) $this->column( 'word' )['Collation'], 'MariaDB calls utf8 utf8mb3.' );
		$this->assertNull( $this->column( 'tf' ) );
		$this->assertNull( $this->column( 'version' ) );
		$this->assertSame( [ 'PRIMARY' ], $this->indexes() );
		$this->assertSame( [ [ 'sourdough' ] ], $this->rows( false ), 'A word utf8 can not store goes.' );
	}

	/**
	 * A column of the table, as SHOW FULL COLUMNS describes it.
	 *
	 * @param string $name The column.
	 *
	 * @return array<string, mixed>|null
	 */
	private function column( string $name ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Schema check.
		$column = $wpdb->get_row( $wpdb->prepare( 'SHOW FULL COLUMNS FROM `' . Table::name() . '` LIKE %s', $name ), ARRAY_A );

		return is_array( $column ) ? $column : null;
	}

	/**
	 * The names of the indexes of the table.
	 *
	 * @return string[]
	 */
	private function indexes(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Schema check.
		$indexes = array_values( array_unique( (array) $wpdb->get_col( 'SHOW INDEX FROM `' . Table::name() . '`', 2 ) ) );
		sort( $indexes );

		return $indexes;
	}

	/**
	 * The rows of the table: word and version.
	 *
	 * @param bool $version Whether to add the version.
	 *
	 * @return array<int, string[]>
	 */
	private function rows( bool $version = true ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Reading the test table.
		return array_map( 'array_values', (array) $wpdb->get_results( 'SELECT word' . ( $version ? ', version' : '' ) . ' FROM `' . Table::name() . '` ORDER BY post_id', ARRAY_A ) );
	}

	/**
	 * The migration.
	 *
	 * @return Migration
	 */
	private function migration(): Migration {
		return ( new Migrator( 'free', dirname( __DIR__, 3 ) . '/migrations' ) )->migration( '2026_10_10_000007_upgrade_cache_table' );
	}
}
