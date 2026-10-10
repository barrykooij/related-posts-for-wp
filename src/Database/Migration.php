<?php
/**
 * The migration class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Database;

/**
 * One change to the database, with the way back.
 *
 * A migration is a file in the `migrations/` folder of a plugin, named `YYYY_MM_DD_HHMMSS_what_it_does.php`, that
 * returns an object of an anonymous class extending this one. The migrator runs the files in the order of their names,
 * once each, and records them.
 *
 * up() returns true when it is done. A migration that converts much data works in slices: it keeps its place in a
 * cursor, returns false when time_left() says the slice is over, and is called again, in the same request or a later
 * one, until it returns true. down() undoes up(); a migration that can't restore something says so in description().
 */
abstract class Migration {

	/**
	 * The database.
	 *
	 * @var \wpdb
	 */
	protected \wpdb $db;

	/**
	 * The name: the file name without `.php`.
	 *
	 * @var string
	 */
	private string $name = '';

	/**
	 * When the current slice ends, as a Unix timestamp with microseconds; 0 for no end.
	 *
	 * @var float
	 */
	private float $deadline = 0.0;

	/**
	 * Set up.
	 */
	public function __construct() {
		global $wpdb;

		$this->db = $wpdb;
	}

	/**
	 * Make the change.
	 *
	 * @return bool Whether it is done; false to be called again.
	 *
	 * @throws \RuntimeException When a query fails.
	 */
	abstract public function up(): bool;

	/**
	 * Undo the change.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException When a query fails.
	 */
	abstract public function down(): void;

	/**
	 * What the migration does, for `wp rp4wp migrate status`.
	 *
	 * @return string
	 */
	public function description(): string {
		return '';
	}

	/**
	 * The name of the migration.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Set the name; the migrator does this.
	 *
	 * @param string $name The name.
	 *
	 * @return void
	 */
	public function set_name( string $name ): void {
		$this->name = $name;
	}

	/**
	 * Set when the current slice ends; the migrator does this.
	 *
	 * @param float $deadline A Unix timestamp with microseconds, or 0 for no end.
	 *
	 * @return void
	 */
	public function set_deadline( float $deadline ): void {
		$this->deadline = $deadline;
	}

	/**
	 * Whether there is time left in the current slice.
	 *
	 * @return bool
	 */
	protected function time_left(): bool {
		return 0.0 === $this->deadline || microtime( true ) < $this->deadline;
	}

	/**
	 * The full name of a table of the plugin.
	 *
	 * @param string $name One of the table constants of Schema.
	 *
	 * @return string
	 */
	protected function table( string $name ): string {
		return Schema::table( $name );
	}

	/**
	 * The character set and collation for a new table.
	 *
	 * @return string
	 */
	protected function charset_collate(): string {
		return $this->db->get_charset_collate();
	}

	/**
	 * Whether a table exists.
	 *
	 * @param string $table The full table name.
	 *
	 * @return bool
	 */
	protected function has_table( string $table ): bool {
		return Schema::has_table( $table );
	}

	/**
	 * Whether a table has an index.
	 *
	 * @param string $table The full table name.
	 * @param string $index The index.
	 *
	 * @return bool
	 */
	protected function has_index( string $table, string $index ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema check; table names can't be placeholders.
		return null !== $this->db->get_var( $this->db->prepare( "SHOW INDEX FROM `{$table}` WHERE Key_name = %s", $index ) );
	}

	/**
	 * Whether a table has a column.
	 *
	 * @param string $table  The full table name.
	 * @param string $column The column.
	 *
	 * @return bool
	 */
	protected function has_column( string $table, string $column ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema check; table names can't be placeholders.
		return null !== $this->db->get_var( $this->db->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column ) );
	}

	/**
	 * Run a query, and throw when it fails.
	 *
	 * @param string $sql The query, prepared.
	 *
	 * @return int The rows it changed.
	 *
	 * @throws \RuntimeException When the query fails.
	 */
	protected function query( string $sql ): int {
		$result = $this->db->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- Migrations prepare their own queries.

		if ( false === $result ) {
			throw new \RuntimeException( esc_html( '' !== $this->db->last_error ? $this->db->last_error : 'A query failed.' ) );
		}

		return (int) $result;
	}

	/**
	 * Empty a group of the object cache, when the object cache can do that.
	 *
	 * @param string $group The group.
	 *
	 * @return void
	 */
	protected function flush_cache_group( string $group ): void {
		if ( wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( $group );
		}
	}

	/**
	 * A value the migration keeps between its slices, such as the last ID it converted.
	 *
	 * @param string $key     The key.
	 * @param mixed  $default What to return when there is none.
	 *
	 * @return mixed
	 */
	protected function cursor( string $key, $default = 0 ) {
		$cursors = get_option( $this->cursor_option(), [] );

		return is_array( $cursors ) && array_key_exists( $key, $cursors ) ? $cursors[ $key ] : $default;
	}

	/**
	 * Keep a value between slices.
	 *
	 * @param string $key   The key.
	 * @param mixed  $value The value.
	 *
	 * @return void
	 */
	protected function save_cursor( string $key, $value ): void {
		$cursors         = get_option( $this->cursor_option(), [] );
		$cursors         = is_array( $cursors ) ? $cursors : [];
		$cursors[ $key ] = $value;

		update_option( $this->cursor_option(), $cursors, false );
	}

	/**
	 * Forget the values kept between slices; done when the migration is done.
	 *
	 * @return void
	 */
	public function clear_cursors(): void {
		delete_option( $this->cursor_option() );
	}

	/**
	 * The option with the values kept between slices.
	 *
	 * @return string
	 */
	private function cursor_option(): string {
		return 'rp4wp_migration_' . substr( md5( $this->name ), 0, 12 );
	}
}
