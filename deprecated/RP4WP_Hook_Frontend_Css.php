<?php
/**
 * The deprecated RP4WP_Hook_Frontend_Css class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\Frontend\Css;

/**
 * The 2.x hook that prints the related posts CSS.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Frontend\Css.
 */
class RP4WP_Hook_Frontend_Css extends RP4WP_Hook {

	/**
	 * The action.
	 *
	 * @var string
	 */
	protected $tag = 'wp_head';

	/**
	 * Constructor. Adds run() to the action, like 2.x did.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, Css::class );

		$this->attach_legacy_hook();
	}

	/**
	 * Print the CSS.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function run() {
		Deprecation::method( __METHOD__, Css::class . '::print_css()' );

		Css::print_css();
	}
}
