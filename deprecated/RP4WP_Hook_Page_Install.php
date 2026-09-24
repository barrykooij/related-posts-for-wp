<?php
/**
 * The deprecated RP4WP_Hook_Page_Install class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Admin\Wizard\Page;
use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * The 2.x hook of the installation wizard page.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Admin\Wizard\Page.
 */
class RP4WP_Hook_Page_Install extends RP4WP_Hook {

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
	 * Register the page.
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
	 * Load the scripts and styles of the page.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function enqueue_install_assets() {
		Deprecation::method( __METHOD__, Page::class . '::enqueue_assets()' );

		Page::enqueue_assets();
	}

	/**
	 * The page.
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
