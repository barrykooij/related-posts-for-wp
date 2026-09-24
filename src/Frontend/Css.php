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
 * Prints the CSS from the settings in the head of single posts.
 */
class Css implements Module {

	/**
	 * Print the CSS in wp_head.
	 *
	 * @return void
	 */
	public static function setup(): void {
		LegacyHooks::add_action( 'RP4WP_Hook_Frontend_Css', 'wp_head', [ self::class, 'print_css' ] );
	}

	/**
	 * Print the CSS on single posts, when there is any.
	 *
	 * @return void
	 */
	public static function print_css(): void {
		if ( ! is_single() ) {
			return;
		}

		$css = trim( (string) Main::get()->settings()->get( 'css' ) );
		if ( '' !== $css ) {
			echo "<style type='text/css'>" . strip_tags( $css ) . '</style>' . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- CSS from an admin setting, with tags removed like 2.x.
		}
	}
}
