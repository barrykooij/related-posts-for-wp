<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'RP4WP_Filter_Plugin_Links' ) ) {

	class RP4WP_Filter_Plugin_Links extends RP4WP_Filter {
		protected $tag = 'plugin_action_links_related-posts-for-wp/related-posts-for-wp.php';

		/**
		 * Add custom plugin links
		 *
		 * @param array $links
		 *
		 * @since  1.4.0
		 * @access public
		 *
		 * @return array
		 */
		public function run( $links ) {
			array_unshift( $links, '<a href="' . admin_url( 'options-general.php?page=rp4wp' ) . '">' . __( 'Settings', 'related-posts-for-wp' ) . '</a>' );
			array_unshift( $links, '<a href="https://www.relatedpostsforwp.com/upgrade-premium/?utm_source=plugin&utm_medium=link&utm_campaign=plugins-page" target="_blank" style="color:green;font-weight:bold;">' . __( 'Upgrade to Premium', 'related-posts-for-wp' ) . '</a>' );

			return $links;
		}
	}

}