<?php
/**
 * The deprecated RP4WP_Hook_Ajax_Install_Save_Words class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Admin\Wizard\Ajax;
use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * The 2.x AJAX hook that caches the words of a batch of posts in the installation wizard.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Admin\Wizard\Ajax.
 */
class RP4WP_Hook_Ajax_Install_Save_Words extends RP4WP_Hook {

	/**
	 * The action.
	 *
	 * @var string
	 */
	protected $tag = 'wp_ajax_rp4wp_install_save_words';

	/**
	 * Constructor. Adds run() to the action, like 2.x did.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, Ajax::class );

		$this->attach_legacy_hook();
	}

	/**
	 * Cache the words of a batch of posts.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function run() {
		Deprecation::method( __METHOD__, Ajax::class . '::save_words()' );

		Ajax::save_words();
	}
}
