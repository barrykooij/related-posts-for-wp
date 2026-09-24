<?php
/**
 * The deprecated helper classes test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Deprecated;

use LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen\ListTable;
use LV2\WordPress\RelatedPostsForWP\Admin\MetaBox\ManageLinks;
use LV2\WordPress\RelatedPostsForWP\Frontend\Widget;

/**
 * The 2.x helpers without a replacement, the meta box, and the classes that now extend their replacement.
 *
 * @covers \RP4WP_Cap_Manager
 * @covers \RP4WP_Class_Manager
 * @covers \RP4WP_Meta_Box_Manage
 * @covers \RP4WP_Related_Posts_Widget
 * @covers \RP4WP_Link_Related_Table
 */
final class HelpersTest extends ShimTestCase {

	public function test_the_capability_helper_still_answers(): void {
		$this->expect_deprecated( 'RP4WP_Cap_Manager::get_capability' );

		$this->assertSame( 'edit_posts', \RP4WP_Cap_Manager::get_capability( self::factory()->post->create() ) );
		$this->assertSame( 'edit_pages', \RP4WP_Cap_Manager::get_capability( self::factory()->post->create( [ 'post_type' => 'page' ] ) ) );
	}

	public function test_the_class_name_helper_still_answers(): void {
		$this->expect_deprecated( 'RP4WP_Class_Manager::format_class_name', 'RP4WP_Class_Manager::capitalize_part' );

		$this->assertSame( 'RP4WP_Hook_Frontend_Css', \RP4WP_Class_Manager::format_class_name( 'class-hook-frontend-css.php' ) );
		$this->assertSame( '_F', \RP4WP_Class_Manager::capitalize_part( [ '_f' ] ) );
	}

	public function test_the_meta_box_is_added_and_rendered(): void {
		global $wp_meta_boxes;

		require_once ABSPATH . 'wp-admin/includes/template.php';
		$this->expect_deprecated( 'RP4WP_Meta_Box_Manage', 'RP4WP_Meta_Box_Manage::add_meta_box', 'RP4WP_Meta_Box_Manage::callback' );
		set_current_screen( 'post' );
		$this->act_as( 'administrator' );
		$post = get_post( self::factory()->post->create() );

		$meta_box = new \RP4WP_Meta_Box_Manage();
		$this->assertSame( 10, has_action( 'add_meta_boxes', [ ManageLinks::class, 'add' ] ) );

		$backup = $wp_meta_boxes;
		$meta_box->add_meta_box();
		$added = isset( $wp_meta_boxes['post']['normal']['core'][ ManageLinks::ID ] );
		$wp_meta_boxes = $backup; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores what the test changed.

		$this->assertTrue( $added );
		$this->assertSame( $this->output( [ ManageLinks::class, 'render' ], $post ), $this->output( [ $meta_box, 'callback' ], $post ) );
	}

	public function test_the_widget_class_is_the_widget(): void {
		$this->expect_deprecated( 'RP4WP_Related_Posts_Widget' );

		$widget = new \RP4WP_Related_Posts_Widget();

		$this->assertInstanceOf( Widget::class, $widget );
		$this->assertSame( Widget::ID_BASE, $widget->id_base );
	}

	public function test_the_list_table_class_is_the_list_table(): void {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		$this->expect_deprecated( 'RP4WP_Link_Related_Table' );

		// WP_List_Table takes its screen from the hook suffix of the admin page.
		$GLOBALS['hook_suffix'] = 'admin_page_rp4wp_link_related';
		$table                  = new \RP4WP_Link_Related_Table();
		unset( $GLOBALS['hook_suffix'] );

		$this->assertInstanceOf( ListTable::class, $table );
	}
}
