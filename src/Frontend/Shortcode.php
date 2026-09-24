<?php
/**
 * The shortcode class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Frontend;

use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * The [rp4wp] shortcode: [rp4wp id="123" limit="3" offset="1"].
 */
class Shortcode implements Module {

	/**
	 * The shortcode tag.
	 */
	public const TAG = 'rp4wp';

	/**
	 * Register the shortcode on init.
	 *
	 * @return void
	 */
	public static function setup(): void {
		LegacyHooks::add_action(
			'RP4WP_Hook_Shortcode',
			'init',
			[ self::class, 'register' ],
			10,
			1,
			[ 'output' => [ self::class, 'render' ] ]
		);
	}

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_shortcode( self::TAG, [ self::class, 'render' ] );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param mixed $atts The shortcode attributes.
	 *
	 * @return string
	 */
	public static function render( $atts ): string {
		// The premium add-on also reads template and heading_text; the free plugin has one layout and ignores them.
		$atts = shortcode_atts(
			[
				'id'           => get_the_ID(),
				'limit'        => -1,
				'offset'       => 0,
				'template'     => 'related-posts-default.php',
				'heading_text' => null,
			],
			$atts
		);

		return Main::get()->renderer()->render(
			(int) $atts['id'],
			[
				'template'     => $atts['template'],
				'limit'        => (int) $atts['limit'],
				'heading_text' => $atts['heading_text'],
				'offset'       => (int) $atts['offset'],
			]
		);
	}
}
