<?php
/**
 * The deprecated RP4WP_Hook_Shortcode class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\Frontend\Shortcode;

/**
 * The 2.x hook of the [rp4wp] shortcode.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Frontend\Shortcode.
 */
class RP4WP_Hook_Shortcode extends RP4WP_Hook {

	/**
	 * The action.
	 *
	 * @var string
	 */
	protected $tag = 'init';

	/**
	 * Constructor. Adds run() to the action, like 2.x did.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, Shortcode::class );

		$this->attach_legacy_hook();
	}

	/**
	 * Register the shortcode.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function run() {
		Deprecation::method( __METHOD__, Shortcode::class . '::register()' );

		Shortcode::register();
	}

	/**
	 * The related posts for the shortcode.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param array $atts The shortcode attributes.
	 *
	 * @return string
	 */
	public function output( $atts ) {
		Deprecation::method( __METHOD__, Shortcode::class . '::render()' );

		return Shortcode::render( $atts );
	}
}
