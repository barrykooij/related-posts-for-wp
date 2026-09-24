<?php
/**
 * The deprecated RP4WP_Filter_Set_Screen_Option class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen\Page;
use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * The 2.x filter that saves the "posts per page" screen option of the link screen.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen\Page.
 */
class RP4WP_Filter_Set_Screen_Option extends RP4WP_Filter {

	/**
	 * The filter.
	 *
	 * @var string
	 */
	protected $tag = 'set-screen-option';

	/**
	 * The number of arguments.
	 *
	 * @var int
	 */
	protected $args = 3;

	/**
	 * Constructor. Adds run() to the filter, like 2.x did.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, Page::class );

		$this->attach_legacy_hook();
	}

	/**
	 * Save the screen option.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param mixed  $status The value to save, false by default.
	 * @param string $option The option.
	 * @param mixed  $value  The submitted value.
	 *
	 * @return mixed
	 */
	public function run( $status, $option, $value ) {
		Deprecation::method( __METHOD__, Page::class . '::save_screen_option()' );

		return Page::save_screen_option( $status, $option, $value );
	}
}
