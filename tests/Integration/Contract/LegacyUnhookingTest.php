<?php
/**
 * The legacy unhooking test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Contract;

use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * Code written for 2.x unhooks the plugin through the hook and filter managers:
 *
 *     $hook = RP4WP_Manager_Filter::get_filter_object( 'RP4WP_Filter_After_Post' );
 *     remove_filter( $hook->get_tag(), [ $hook, 'run' ], $hook->get_priority() );
 *
 * That must keep working for every 2.x hook and filter, wherever its callback lives now.
 *
 * @coversNothing
 */
final class LegacyUnhookingTest extends TestCase {

	/**
	 * Every 2.x hook and filter class, with its WordPress hook and priority.
	 *
	 * @return array<string, array{string, string, int}>
	 */
	public static function legacy_hooks(): array {
		$hooks = [
			'RP4WP_Hook_Admin_Scripts'           => [ 'admin_enqueue_scripts', 10 ],
			'RP4WP_Hook_Ajax_Delete_Link'        => [ 'wp_ajax_rp4wp_delete_link', 10 ],
			'RP4WP_Hook_Ajax_Install_Link_Posts' => [ 'wp_ajax_rp4wp_install_link_posts', 10 ],
			'RP4WP_Hook_Ajax_Install_Save_Words' => [ 'wp_ajax_rp4wp_install_save_words', 10 ],
			'RP4WP_Hook_Delete_Words'            => [ 'delete_post', 10 ],
			'RP4WP_Hook_Frontend_Css'            => [ 'wp_head', 10 ],
			'RP4WP_Hook_Link_Related_Screen'     => [ 'admin_menu', 10 ],
			'RP4WP_Hook_Meta_Box'                => [ 'admin_init', 10 ],
			'RP4WP_Hook_Meta_Box_Ajax_Sort'      => [ 'wp_ajax_rp4wp_related_sort', 10 ],
			'RP4WP_Hook_Page_Install'            => [ 'admin_menu', 10 ],
			'RP4WP_Hook_Post_Type'               => [ 'init', 10 ],
			'RP4WP_Hook_Related_Auto_Link'       => [ 'transition_post_status', 11 ],
			'RP4WP_Hook_Related_Update_Link'     => [ 'transition_post_status', 11 ],
			'RP4WP_Hook_Related_Save_Words'      => [ 'transition_post_status', 10 ],
			'RP4WP_Hook_Settings_Page'           => [ 'admin_menu', 10 ],
			'RP4WP_Hook_Shortcode'               => [ 'init', 10 ],
			'RP4WP_Hook_Widget'                  => [ 'widgets_init', 10 ],
			'RP4WP_Filter_After_Post'            => [ 'the_content', 99 ],
			'RP4WP_Filter_Plugin_Links'          => [ 'plugin_action_links_related-posts-for-wp/related-posts-for-wp.php', 10 ],
			'RP4WP_Filter_Set_Screen_Option'     => [ 'set-screen-option', 10 ],
			'RP4WP_Filter_Yoast_Duplicate_Post'  => [ 'duplicate_post_excludelist_filter', 10 ],
		];

		$data = [];
		foreach ( $hooks as $class => [ $tag, $priority ] ) {
			$data[ $class ] = [ $class, $tag, $priority ];
		}

		return $data;
	}

	/**
	 * The manager hands out an object that is the registered callback, and unhooking it works.
	 *
	 * @dataProvider legacy_hooks
	 *
	 * @param string $class    The 2.x class.
	 * @param string $tag      The WordPress hook.
	 * @param int    $priority The priority.
	 */
	public function test_can_be_unhooked_the_2x_way( string $class, string $tag, int $priority ): void {
		$hook = 0 === strpos( $class, 'RP4WP_Filter_' )
			? \RP4WP_Manager_Filter::get_filter_object( $class )
			: \RP4WP_Manager_Hook::get_hook_object( $class );

		$this->assertIsObject( $hook, "{$class} is not available from its manager." );
		$this->assertSame( $tag, $hook->get_tag() );
		$this->assertSame( $priority, $hook->get_priority() );
		$this->assertSame( $priority, has_filter( $hook->get_tag(), [ $hook, 'run' ] ), "{$class} is not the registered callback." );

		remove_filter( $hook->get_tag(), [ $hook, 'run' ], $hook->get_priority() );

		$this->assertFalse( has_filter( $hook->get_tag(), [ $hook, 'run' ] ) );
	}

	public function test_unhooking_the_content_filter_removes_the_related_posts(): void {
		$parent = self::factory()->post->create( [ 'post_content' => 'Parent content.' ] );
		( new \RP4WP_Post_Link_Manager() )->add( $parent, self::factory()->post->create( [ 'post_title' => 'A related post' ] ) );

		$this->assertStringContainsString( 'rp4wp-related-posts', $this->content_of( $parent ) );

		$hook = \RP4WP_Manager_Filter::get_filter_object( 'RP4WP_Filter_After_Post' );
		remove_filter( $hook->get_tag(), [ $hook, 'run' ], $hook->get_priority() );

		$this->assertStringNotContainsString( 'rp4wp-related-posts', $this->content_of( $parent ) );
	}

	/**
	 * The filtered content of a post, rendered on its own page.
	 *
	 * @param int $post_id The post.
	 *
	 * @return string
	 */
	private function content_of( int $post_id ): string {
		$this->go_to( get_permalink( $post_id ) );

		$content = '';
		while ( have_posts() ) {
			the_post();
			$content = (string) apply_filters( 'the_content', get_the_content() );
		}

		return $content;
	}
}
