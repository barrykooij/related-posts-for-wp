<?php
/**
 * The deprecated RP4WP_Related_Posts_Widget class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\Frontend\Widget;

/**
 * The 2.x widget class. The widget stays registered under this name, so the_widget( 'RP4WP_Related_Posts_Widget' )
 * and unregister_widget( 'RP4WP_Related_Posts_Widget' ) work without this class.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Frontend\Widget.
 */
class RP4WP_Related_Posts_Widget extends Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, Widget::class );

		parent::__construct();
	}
}
