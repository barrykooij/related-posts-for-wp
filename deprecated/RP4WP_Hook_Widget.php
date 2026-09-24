<?php
/**
 * The deprecated RP4WP_Hook_Widget class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\Frontend\Widgets;

/**
 * The 2.x hook that registers the related posts widget.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Frontend\Widgets.
 */
class RP4WP_Hook_Widget extends RP4WP_Hook {

	/**
	 * The action.
	 *
	 * @var string
	 */
	protected $tag = 'widgets_init';

	/**
	 * Constructor. Adds run() to the action, like 2.x did.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, Widgets::class );

		$this->attach_legacy_hook();
	}

	/**
	 * Register the widget.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function run() {
		Deprecation::method( __METHOD__, Widgets::class . '::register()' );

		Widgets::register();
	}
}
