<?php
/**
 * The front-end CSS test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Frontend;

use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Frontend\Css;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Settings\Settings;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * Which CSS is printed, and where: the head of singular pages, or the footer of other pages that show related posts.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Frontend\Css
 * @covers \LV2\WordPress\RelatedPostsForWP\Settings\Settings::get_options
 */
final class CssTest extends TestCase {

	/**
	 * A post.
	 *
	 * @var int
	 */
	private int $post_id;

	public function set_up(): void {
		parent::set_up();

		$this->post_id = self::factory()->post->create();

		// Start every test on a clean page.
		$this->footer();
	}

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		$this->footer();

		parent::tear_down();
	}

	public function test_a_post_gets_the_new_default_in_the_head(): void {
		$this->go_to( get_permalink( $this->post_id ) );

		$this->assertSame( $this->style( $this->default_css() ), $this->head() );
	}

	public function test_a_page_gets_it_too(): void {
		$this->go_to( get_permalink( self::factory()->post->create( [ 'post_type' => 'page' ] ) ) );

		$this->assertSame( $this->style( $this->default_css() ), $this->head() );
	}

	public function test_a_site_that_stored_the_2x_default_gets_the_new_default(): void {
		update_option( Settings::OPTION, [ 'css' => str_replace( "\n", "\r\n", Settings::LEGACY_DEFAULT_CSS['ltr'] ) ] );
		$this->go_to( get_permalink( $this->post_id ) );

		$this->assertSame( $this->style( $this->default_css() ), $this->head() );
		$this->assertSame( str_replace( "\n", "\r\n", Settings::LEGACY_DEFAULT_CSS['ltr'] ), get_option( Settings::OPTION )['css'], 'The option is not rewritten.' );
	}

	public function test_a_site_that_styles_the_related_posts_in_the_additional_css_keeps_the_2x_default(): void {
		update_option( Settings::OPTION, [ 'css' => Settings::LEGACY_DEFAULT_CSS['ltr'] ] );
		wp_update_custom_css_post( '.rp4wp-related-post-image img { border-radius: 50%; }' );
		$this->go_to( get_permalink( $this->post_id ) );

		$this->assertSame( $this->style( Settings::LEGACY_DEFAULT_CSS['ltr'] ), $this->head() );
	}

	public function test_custom_css_is_printed_as_stored(): void {
		update_option( Settings::OPTION, [ 'css' => '.rp4wp-related-posts{display:none}' ] );
		$this->go_to( get_permalink( $this->post_id ) );

		$this->assertSame( $this->style( '.rp4wp-related-posts{display:none}' ), $this->head() );
	}

	public function test_empty_css_prints_nothing(): void {
		update_option( Settings::OPTION, [ 'css' => '' ] );
		$this->go_to( get_permalink( $this->post_id ) );
		$this->render_related_posts();

		$this->assertSame( '', $this->head() . $this->footer() );
	}

	public function test_the_filter_turns_the_css_off(): void {
		add_filter( 'rp4wp_disable_css', '__return_true' );
		$this->go_to( get_permalink( $this->post_id ) );
		$this->render_related_posts();

		$this->assertSame( '', $this->head() . $this->footer() );
	}

	public function test_an_archive_with_related_posts_gets_the_css_in_the_footer(): void {
		$this->go_to( home_url( '/' ) );

		$this->assertSame( '', $this->head() );

		$this->assertNotSame( '', $this->render_related_posts() );
		$this->assertSame( $this->style( $this->default_css() ), $this->footer() );
	}

	public function test_an_archive_without_related_posts_gets_no_css(): void {
		$this->go_to( home_url( '/' ) );

		$this->assertSame( '', $this->head() . $this->footer() );
	}

	public function test_the_footer_does_not_print_it_again(): void {
		$this->go_to( get_permalink( $this->post_id ) );
		$this->head();
		$this->render_related_posts();

		$this->assertSame( '', $this->footer() );
	}

	public function test_unhooking_the_css_from_the_head_also_keeps_it_out_of_the_footer(): void {
		// The object 2.x code gets from RP4WP_Manager_Hook::get_hook_object() (see LegacyUnhookingTest).
		$hook = LegacyHooks::get( 'RP4WP_Hook_Frontend_Css' );
		$this->assertNotNull( $hook );
		remove_action( $hook->get_tag(), [ $hook, 'run' ], $hook->get_priority() );

		$this->go_to( home_url( '/' ) );
		$this->render_related_posts();
		$footer = $this->footer();

		add_action( $hook->get_tag(), [ $hook, 'run' ], $hook->get_priority() );

		$this->assertSame( '', $footer );
	}

	/**
	 * Show the related posts of a post that has one.
	 *
	 * @return string
	 */
	private function render_related_posts(): string {
		( new LinkRepository() )->add( $this->post_id, self::factory()->post->create() );

		return Main::get()->renderer()->render( $this->post_id );
	}

	/**
	 * What the plugin prints in wp_head.
	 *
	 * @return string
	 */
	private function head(): string {
		ob_start();
		Css::print_css();

		return (string) ob_get_clean();
	}

	/**
	 * What the plugin prints in wp_footer; it ends the page.
	 *
	 * @return string
	 */
	private function footer(): string {
		ob_start();
		Css::print_late_css();

		return (string) ob_get_clean();
	}

	/**
	 * The style element for some CSS.
	 *
	 * @param string $css The CSS.
	 *
	 * @return string
	 */
	private function style( string $css ): string {
		return "<style type='text/css'>" . $css . '</style>' . PHP_EOL;
	}

	/**
	 * The new default CSS.
	 *
	 * @return string
	 */
	private function default_css(): string {
		return Main::get()->settings()->defaults()['css'];
	}
}
