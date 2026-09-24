<?php
/**
 * The deprecated hook and filter classes test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Deprecated;

use LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen\Page as LinkScreen;
use LV2\WordPress\RelatedPostsForWP\Admin\MetaBox\ManageLinks;
use LV2\WordPress\RelatedPostsForWP\Admin\PluginLinks;
use LV2\WordPress\RelatedPostsForWP\Admin\Settings\Page as SettingsPage;
use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Frontend\ContentFilter;
use LV2\WordPress\RelatedPostsForWP\Frontend\Css;
use LV2\WordPress\RelatedPostsForWP\Frontend\Shortcode;
use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Integrations\YoastDuplicatePost;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;

/**
 * The 2.x hook and filter classes, their base classes and their managers.
 *
 * @covers \RP4WP_Hook
 * @covers \RP4WP_Filter
 * @covers \RP4WP_Manager_Hook
 * @covers \RP4WP_Manager_Filter
 */
final class HooksTest extends ShimTestCase {

	public function tear_down(): void {
		// The managers keep what they loaded in a static property; start the next test without it.
		foreach ( [
			\RP4WP_Manager_Hook::class => 'hooks',
			\RP4WP_Manager_Filter::class => 'filters',
		] as $class => $property ) {
			$reflection = new \ReflectionProperty( $class, $property );
			$reflection->setAccessible( true );
			$reflection->setValue( null, [] );
		}

		parent::tear_down();
	}

	/**
	 * Every 2.x hook and filter class, with its WordPress hook, priority and number of arguments.
	 *
	 * @return array<string, array{string, string, int, int}>
	 */
	public static function legacy_hooks(): array {
		$hooks = [
			'RP4WP_Hook_Admin_Scripts'           => [ 'admin_enqueue_scripts', 10, 1 ],
			'RP4WP_Hook_Ajax_Delete_Link'        => [ 'wp_ajax_rp4wp_delete_link', 10, 1 ],
			'RP4WP_Hook_Ajax_Install_Link_Posts' => [ 'wp_ajax_rp4wp_install_link_posts', 10, 1 ],
			'RP4WP_Hook_Ajax_Install_Save_Words' => [ 'wp_ajax_rp4wp_install_save_words', 10, 1 ],
			'RP4WP_Hook_Delete_Words'            => [ 'delete_post', 10, 1 ],
			'RP4WP_Hook_Frontend_Css'            => [ 'wp_head', 10, 1 ],
			'RP4WP_Hook_Link_Related_Screen'     => [ 'admin_menu', 10, 1 ],
			'RP4WP_Hook_Meta_Box'                => [ 'admin_init', 10, 1 ],
			'RP4WP_Hook_Meta_Box_Ajax_Sort'      => [ 'wp_ajax_rp4wp_related_sort', 10, 1 ],
			'RP4WP_Hook_Page_Install'            => [ 'admin_menu', 10, 1 ],
			'RP4WP_Hook_Post_Type'               => [ 'init', 10, 1 ],
			'RP4WP_Hook_Related_Auto_Link'       => [ 'transition_post_status', 11, 3 ],
			'RP4WP_Hook_Related_Update_Link'     => [ 'transition_post_status', 11, 3 ],
			'RP4WP_Hook_Related_Save_Words'      => [ 'transition_post_status', 10, 3 ],
			'RP4WP_Hook_Settings_Page'           => [ 'admin_menu', 10, 1 ],
			'RP4WP_Hook_Shortcode'               => [ 'init', 10, 1 ],
			'RP4WP_Hook_Widget'                  => [ 'widgets_init', 10, 1 ],
			'RP4WP_Filter_After_Post'            => [ 'the_content', 99, 1 ],
			'RP4WP_Filter_Plugin_Links'          => [ 'plugin_action_links_related-posts-for-wp/related-posts-for-wp.php', 10, 1 ],
			'RP4WP_Filter_Set_Screen_Option'     => [ 'set-screen-option', 10, 3 ],
			'RP4WP_Filter_Yoast_Duplicate_Post'  => [ 'duplicate_post_excludelist_filter', 10, 1 ],
		];

		$data = [];
		foreach ( $hooks as $class => [ $tag, $priority, $args ] ) {
			$data[ $class ] = [ $class, $tag, $priority, $args ];
		}

		return $data;
	}

	/**
	 * Creating a 2.x hook object hooks it in, like 2.x did.
	 *
	 * @dataProvider legacy_hooks
	 *
	 * @param string $class    The 2.x class.
	 * @param string $tag      The WordPress hook.
	 * @param int    $priority The priority.
	 * @param int    $args     The number of arguments.
	 */
	public function test_a_2x_hook_object_hooks_itself_in( string $class, string $tag, int $priority, int $args ): void {
		$base = 0 === strpos( $class, 'RP4WP_Filter_' ) ? 'RP4WP_Filter' : 'RP4WP_Hook';
		$this->expect_deprecated( $class, "{$base}::get_tag", "{$base}::get_priority", "{$base}::get_args" );

		$hook = new $class();

		$this->assertSame( $tag, $hook->get_tag() );
		$this->assertSame( $priority, $hook->get_priority() );
		$this->assertSame( $args, $hook->get_args() );
		$this->assertSame( $priority, has_filter( $tag, [ $hook, 'run' ] ) );
	}

	public function test_the_css_hook_prints_the_css(): void {
		$this->expect_deprecated( 'RP4WP_Hook_Frontend_Css', 'RP4WP_Hook_Frontend_Css::run' );
		$this->go_to( get_permalink( self::factory()->post->create() ) );

		$css = $this->output( [ new \RP4WP_Hook_Frontend_Css(), 'run' ] );

		$this->assertStringContainsString( '<style', $css );
		$this->assertSame( $this->output( [ Css::class, 'print_css' ] ), $css );
	}

	public function test_the_content_filter_adds_the_related_posts(): void {
		$this->expect_deprecated( 'RP4WP_Filter_After_Post', 'RP4WP_Filter_After_Post::run' );
		$parent = self::factory()->post->create();
		( new LinkRepository() )->add( $parent, self::factory()->post->create( [ 'post_title' => 'A related post' ] ) );
		$filter = new \RP4WP_Filter_After_Post();

		$this->go_to( get_permalink( $parent ) );
		$content = '';
		while ( have_posts() ) {
			the_post();
			$content = $filter->run( 'Content.' );
		}

		$this->assertStringContainsString( 'A related post', $content );
	}

	public function test_the_shortcode_hook_renders_the_shortcode(): void {
		$this->expect_deprecated( 'RP4WP_Hook_Shortcode', 'RP4WP_Hook_Shortcode::output' );
		$parent = self::factory()->post->create();
		( new LinkRepository() )->add( $parent, self::factory()->post->create( [ 'post_title' => 'A related post' ] ) );

		$html = ( new \RP4WP_Hook_Shortcode() )->output( [ 'id' => $parent ] );

		$this->assertStringContainsString( 'A related post', $html );
		$this->assertSame( Shortcode::render( [ 'id' => $parent ] ), $html );
	}

	public function test_the_lifecycle_hooks_cache_and_delete_words(): void {
		$this->expect_deprecated( 'RP4WP_Hook_Related_Save_Words', 'RP4WP_Hook_Related_Save_Words::run', 'RP4WP_Hook_Delete_Words', 'RP4WP_Hook_Delete_Words::run' );
		$this->act_as( 'administrator' );
		$post = get_post( self::factory()->post->create( [ 'post_content' => 'Sourdough bread needs flour, water and time.' ] ) );
		( new Cache() )->delete_post( $post->ID );

		( new \RP4WP_Hook_Related_Save_Words() )->run( 'publish', 'draft', $post );
		$this->assertGreaterThan( 0, $this->cached_words( $post->ID ) );

		( new \RP4WP_Hook_Delete_Words() )->run( $post->ID );
		$this->assertSame( 0, $this->cached_words( $post->ID ) );
	}

	public function test_the_admin_filters_filter_like_their_modules(): void {
		$this->expect_deprecated(
			'RP4WP_Filter_Plugin_Links',
			'RP4WP_Filter_Plugin_Links::run',
			'RP4WP_Filter_Set_Screen_Option',
			'RP4WP_Filter_Set_Screen_Option::run',
			'RP4WP_Filter_Yoast_Duplicate_Post',
			'RP4WP_Filter_Yoast_Duplicate_Post::run'
		);

		$this->assertSame( PluginLinks::add( [] ), ( new \RP4WP_Filter_Plugin_Links() )->run( [] ) );

		$screen_option = new \RP4WP_Filter_Set_Screen_Option();
		$this->assertSame( 50, $screen_option->run( false, LinkScreen::PER_PAGE_OPTION, 50 ) );
		$this->assertFalse( $screen_option->run( false, 'another_option', 50 ) );

		$this->assertSame( YoastDuplicatePost::exclude_meta( [ 'a' ] ), ( new \RP4WP_Filter_Yoast_Duplicate_Post() )->run( [ 'a' ] ) );
	}

	public function test_the_settings_page_hook_loads_the_styles_and_renders_the_page(): void {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		$this->expect_deprecated( 'RP4WP_Hook_Settings_Page', 'RP4WP_Hook_Settings_Page::enqueue_assets', 'RP4WP_Hook_Settings_Page::screen' );
		$hook = new \RP4WP_Hook_Settings_Page();

		$hook->enqueue_assets();
		$enqueued = wp_style_is( 'rp4wp-settings-css' );
		wp_dequeue_style( 'rp4wp-settings-css' );

		$this->assertTrue( $enqueued );
		$this->assertSame( $this->output( [ SettingsPage::class, 'render' ] ), $this->output( [ $hook, 'screen' ] ) );
	}

	public function test_the_meta_box_hook_adds_the_meta_box_in_the_admin(): void {
		$this->expect_deprecated( 'RP4WP_Hook_Meta_Box', 'RP4WP_Hook_Meta_Box::run' );
		set_current_screen( 'dashboard' );

		( new \RP4WP_Hook_Meta_Box() )->run();

		$this->assertSame( 10, has_action( 'add_meta_boxes', [ ManageLinks::class, 'add' ] ) );
	}

	public function test_a_class_that_extends_the_hook_base_still_hooks_in(): void {
		$this->expect_deprecated( 'RP4WP_Hook', 'RP4WP_Hook::register' );

		$hook = new Legacy_Action_Fixture();
		do_action( 'rp4wp_test_legacy_action' );
		$this->assertSame( 1, $hook->runs );

		remove_action( 'rp4wp_test_legacy_action', [ $hook, 'run' ] );
		$hook->register();
		do_action( 'rp4wp_test_legacy_action' );
		$this->assertSame( 2, $hook->runs );
	}

	public function test_a_class_that_extends_the_filter_base_still_filters(): void {
		$this->expect_deprecated( 'RP4WP_Filter' );

		new Legacy_Filter_Fixture();

		$this->assertSame( 'filtered', apply_filters( 'rp4wp_test_legacy_filter', 'value' ) );
	}

	public function test_a_hook_without_a_tag_is_reported_instead_of_stopping_the_site(): void {
		$this->expect_deprecated( 'RP4WP_Hook' );
		$this->setExpectedIncorrectUsage( Legacy_Action_Without_Tag_Fixture::class );

		$hook = new Legacy_Action_Without_Tag_Fixture();

		$this->assertInstanceOf( \RP4WP_Hook::class, $hook );
	}

	public function test_the_hook_manager_loads_2x_hooks_by_name(): void {
		$this->expect_deprecated( 'RP4WP_Manager_Hook', 'RP4WP_Manager_Hook::load_hooks', 'RP4WP_Manager_Hook::load_hook', 'RP4WP_Hook_Frontend_Css', 'RP4WP_Manager_Hook::get_hook_object' );

		( new \RP4WP_Manager_Hook( [ 'frontend_css' ] ) )->load_hooks();

		$this->assertInstanceOf( \RP4WP_Hook_Frontend_Css::class, \RP4WP_Manager_Hook::get_hook_object( 'RP4WP_Hook_Frontend_Css' ) );
	}

	public function test_the_filter_manager_loads_2x_filters_by_name(): void {
		$this->expect_deprecated( 'RP4WP_Manager_Filter', 'RP4WP_Manager_Filter::load_filters', 'RP4WP_Manager_Filter::load_filter', 'RP4WP_Filter_After_Post', 'RP4WP_Manager_Filter::get_filter_object' );

		( new \RP4WP_Manager_Filter( [ 'after_post' ] ) )->load_filters();

		$this->assertInstanceOf( \RP4WP_Filter_After_Post::class, \RP4WP_Manager_Filter::get_filter_object( 'RP4WP_Filter_After_Post' ) );
	}

	public function test_the_managers_hand_out_the_hooks_of_the_modules(): void {
		$this->expect_deprecated( 'RP4WP_Manager_Hook::get_hook_object', 'RP4WP_Manager_Filter::get_filter_object' );

		$this->assertSame( LegacyHooks::get( 'RP4WP_Hook_Frontend_Css' ), \RP4WP_Manager_Hook::get_hook_object( 'RP4WP_Hook_Frontend_Css' ) );
		$this->assertSame( LegacyHooks::get( 'RP4WP_Filter_After_Post' ), \RP4WP_Manager_Filter::get_filter_object( 'RP4WP_Filter_After_Post' ) );
		$this->assertNull( \RP4WP_Manager_Hook::get_hook_object( 'RP4WP_Hook_Unknown' ) );
	}

	/**
	 * The number of cached words of a post.
	 *
	 * @param int $post_id The post.
	 *
	 * @return int
	 */
	private function cached_words( int $post_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Table::name() . ' WHERE post_id = %d', $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Reading our own table.
	}
}

/**
 * An action of a plugin that built on the 2.x hook class.
 */
final class Legacy_Action_Fixture extends \RP4WP_Hook {

	/**
	 * The action.
	 *
	 * @var string
	 */
	protected $tag = 'rp4wp_test_legacy_action';

	/**
	 * How often run() ran.
	 *
	 * @var int
	 */
	public int $runs = 0;

	/**
	 * The callback.
	 *
	 * @return void
	 */
	public function run(): void {
		++$this->runs;
	}
}

/**
 * A filter of a plugin that built on the 2.x filter class.
 */
final class Legacy_Filter_Fixture extends \RP4WP_Filter {

	/**
	 * The filter.
	 *
	 * @var string
	 */
	protected $tag = 'rp4wp_test_legacy_filter';

	/**
	 * The callback.
	 *
	 * @return string
	 */
	public function run(): string {
		return 'filtered';
	}
}

/**
 * A 2.x hook subclass that forgot its tag.
 */
final class Legacy_Action_Without_Tag_Fixture extends \RP4WP_Hook {

	/**
	 * The callback.
	 *
	 * @return void
	 */
	public function run(): void {
	}
}
