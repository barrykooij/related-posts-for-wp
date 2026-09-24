<?php
/**
 * The shortcode class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Frontend;

use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
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
		$atts = shortcode_atts(
			[
				'id'     => get_the_ID(),
				'limit'  => -1,
				'offset' => 0,
			],
			$atts
		);

		return ( new Renderer( new LinkRepository(), Main::get()->settings() ) )->related_posts_html( (int) $atts['id'], (int) $atts['limit'], (int) $atts['offset'] );
	}
}
