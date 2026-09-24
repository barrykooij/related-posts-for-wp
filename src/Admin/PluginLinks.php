<?php
/**
 * The plugin links class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin;

use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * Links next to the plugin on the Plugins screen.
 */
class PluginLinks implements Module {

	/**
	 * Add the links on the Plugins screen.
	 *
	 * @return void
	 */
	public static function setup(): void {
		LegacyHooks::add_filter( 'RP4WP_Filter_Plugin_Links', 'plugin_action_links_' . plugin_basename( Main::file() ), [ self::class, 'add' ] );
	}

	/**
	 * Add the settings and upgrade links in front of the others.
	 *
	 * @param mixed $links The links.
	 *
	 * @return array<int|string, string>
	 */
	public static function add( $links ): array {
		$links = (array) $links;

		array_unshift( $links, '<a href="' . admin_url( 'options-general.php?page=rp4wp' ) . '">' . __( 'Settings', 'related-posts-for-wp' ) . '</a>' );
		array_unshift( $links, '<a href="https://www.relatedpostsforwp.com/upgrade-premium/?utm_source=plugin&utm_medium=link&utm_campaign=plugins-page" target="_blank" style="color:green;font-weight:bold;">' . __( 'Upgrade to Premium', 'related-posts-for-wp' ) . '</a>' );

		return $links;
	}
}
