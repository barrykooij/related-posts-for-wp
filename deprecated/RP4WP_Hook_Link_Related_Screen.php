<?php
/**
 * The deprecated RP4WP_Hook_Link_Related_Screen class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen\Page;
use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * The 2.x hook of the screen that links posts by hand.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen\Page.
 */
class RP4WP_Hook_Link_Related_Screen extends RP4WP_Hook {

	/**
	 * The action.
	 *
	 * @var string
	 */
	protected $tag = 'admin_menu';

	/**
	 * Constructor. Adds run() to the action, like 2.x did.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, Page::class );

		$this->attach_legacy_hook();
	}

	/**
	 * Handle link requests and register the screen.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function run() {
		Deprecation::method( __METHOD__, Page::class . '::register()' );

		Page::register();
	}

	/**
	 * Add the "posts per page" screen option.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function init_screen() {
		Deprecation::method( __METHOD__, Page::class . '::add_screen_options()' );

		Page::add_screen_options();
	}

	/**
	 * The screen.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function content() {
		Deprecation::method( __METHOD__, Page::class . '::render()' );

		Page::render();
	}
}
