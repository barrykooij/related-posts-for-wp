<?php
/**
 * The front-end CSS class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Frontend;

use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * Prints the CSS from the settings in the head of singular pages. Related posts shown elsewhere, by the shortcode or
 * the widget on an archive for example, get it in the footer.
 */
class Css implements Module {

	/**
	 * Whether the CSS was printed on this page.
	 *
	 * @var bool
	 */
	private static bool $printed = false;

	/**
	 * Whether related posts were shown on this page.
	 *
	 * @var bool
	 */
	private static bool $needed = false;

	/**
	 * Print the CSS in wp_head, and in wp_footer when the head left it out.
	 *
	 * @return void
	 */
	public static function setup(): void {
		LegacyHooks::add_action( 'RP4WP_Hook_Frontend_Css', 'wp_head', [ self::class, 'print_css' ] );
		add_action( 'wp_footer', [ self::class, 'print_late_css' ] );
	}

	/**
	 * Print the CSS on singular pages. Runs on wp_head.
	 *
	 * @return void
	 */
	public static function print_css(): void {
		if ( ! is_singular() ) {
			return;
		}

		self::print_style();
	}

	/**
	 * Note that related posts are on the page, so the footer prints the CSS when the head did not.
	 *
	 * @return void
	 */
	public static function needed(): void {
		self::$needed = true;
	}

	/**
	 * Print the CSS when related posts were shown but the head left it out. Code that unhooked the CSS from wp_head
	 * (the 2.x way) does not get it here either. Runs on wp_footer.
	 *
	 * @return void
	 */
	public static function print_late_css(): void {
		if ( self::$needed && ! self::$printed ) {
			$hook = LegacyHooks::get( 'RP4WP_Hook_Frontend_Css' );

			if ( null !== $hook && false !== has_action( $hook->get_tag(), [ $hook, 'run' ] ) ) {
				self::print_style();
			}
		}

		// The page is done; a process that serves more pages starts the next one clean.
		self::$needed  = false;
		self::$printed = false;
	}

	/**
	 * Print the CSS, when there is any.
	 *
	 * @return void
	 */
	private static function print_style(): void {
		/**
		 * Filters whether the CSS of the related posts is left out.
		 *
		 * @since 3.0.0
		 *
		 * @param bool $disable Whether to leave it out. Default false.
		 */
		if ( false !== apply_filters( 'rp4wp_disable_css', false ) ) {
			return;
		}

		$css = trim( (string) Main::get()->settings()->get( 'css' ) );
		if ( '' !== $css ) {
			echo "<style type='text/css'>" . strip_tags( $css ) . '</style>' . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- CSS from an admin setting, with tags removed like 2.x.
			self::$printed = true;
		}
	}
}
