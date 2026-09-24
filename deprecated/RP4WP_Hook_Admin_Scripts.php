<?php
/**
 * The deprecated RP4WP_Hook_Admin_Scripts class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Admin\Assets;
use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * The 2.x hook that loads the admin scripts and styles.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Admin\Assets.
 */
class RP4WP_Hook_Admin_Scripts extends RP4WP_Hook {

	/**
	 * The action.
	 *
	 * @var string
	 */
	protected $tag = 'admin_enqueue_scripts';

	/**
	 * Constructor. Adds run() to the action, like 2.x did.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, Assets::class );

		$this->attach_legacy_hook();
	}

	/**
	 * Load the scripts and styles of the current admin screen.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function run() {
		Deprecation::method( __METHOD__, Assets::class . '::enqueue()' );

		Assets::enqueue();
	}
}
