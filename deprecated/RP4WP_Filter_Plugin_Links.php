<?php
/**
 * The deprecated RP4WP_Filter_Plugin_Links class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Admin\PluginLinks;
use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * The 2.x filter that adds links to the plugin on the plugins screen.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Admin\PluginLinks.
 */
class RP4WP_Filter_Plugin_Links extends RP4WP_Filter {

	/**
	 * The filter.
	 *
	 * @var string
	 */
	protected $tag = 'plugin_action_links_related-posts-for-wp/related-posts-for-wp.php';

	/**
	 * Constructor. Adds run() to the filter, like 2.x did.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, PluginLinks::class );

		$this->attach_legacy_hook();
	}

	/**
	 * Add the plugin links.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param array $links The links.
	 *
	 * @return array
	 */
	public function run( $links ) {
		Deprecation::method( __METHOD__, PluginLinks::class . '::add()' );

		return PluginLinks::add( $links );
	}
}
