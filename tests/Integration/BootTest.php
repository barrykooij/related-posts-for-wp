<?php
/**
 * The boot test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration;

/**
 * Checks that the plugin boots inside WordPress and registers what it always has.
 *
 * @coversNothing
 */
final class BootTest extends TestCase {

	public function test_plugin_boots(): void {
		$this->assertStringEndsWith( '/related-posts-for-wp.php', RP4WP_PLUGIN_FILE );
		$this->assertInstanceOf( \RP4WP::class, RP4WP() );
		$this->assertSame( 20, has_action( 'plugins_loaded', 'rp4wp_load_plugin' ) );
	}

	public function test_cache_table_exists(): void {
		global $wpdb;

		$table = \RP4WP_Related_Word_Manager::get_database_table();

		$this->assertSame( $table, $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Schema check.
	}

	public function test_link_post_type_is_registered(): void {
		$this->assertTrue( post_type_exists( 'rp4wp_link' ) );
		$this->assertFalse( get_post_type_object( 'rp4wp_link' )->public );
	}

	public function test_shortcode_is_registered(): void {
		$this->assertTrue( shortcode_exists( 'rp4wp' ) );
	}

	public function test_settings_are_set_up_on_init(): void {
		$this->assertInstanceOf( \RP4WP_Settings::class, RP4WP()->settings );
	}
}
