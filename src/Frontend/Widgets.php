<?php
/**
 * The widget registration class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Frontend;

use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * Registers the related posts widget.
 */
class Widgets implements Module {

	/**
	 * The key the widget was registered under in 2.x. Themes pass it to the_widget() and unregister_widget().
	 */
	public const LEGACY_KEY = 'RP4WP_Related_Posts_Widget';

	/**
	 * Register the widget on widgets_init.
	 *
	 * @return void
	 */
	public static function setup(): void {
		LegacyHooks::add_action( 'RP4WP_Hook_Widget', 'widgets_init', [ self::class, 'register' ] );
	}

	/**
	 * Register the widget under its 2.x key, so the_widget( 'RP4WP_Related_Posts_Widget' ) and
	 * unregister_widget( 'RP4WP_Related_Posts_Widget' ) keep working. register_widget() would use the class name.
	 *
	 * @return void
	 */
	public static function register(): void {
		global $wp_widget_factory;

		$wp_widget_factory->widgets[ self::LEGACY_KEY ] = new Widget();
	}
}
