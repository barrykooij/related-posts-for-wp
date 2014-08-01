<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class SRP_Meta_Box_Manage {

	public function __construct() {

		// Check if we're in the admin/backend
		if ( !is_admin() ) {
			return;
		}

		// Add meta boxes
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

	}

	/**
	 * Add metabox to dashboard
	 *
	 * @access public
	 * @return void
	 */
	public function add_meta_box() {

		// Add meta box to parent
		add_meta_box(
			'srp_metabox_related_posts',
			__( 'Related Posts', 'simple-related-posts' ),
			array( $this, 'callback' ),
			'post',
			'normal',
			'core'
		);

	}

	/**
	 * Metabox content
	 *
	 * @access public
	 * @return void
	 */
	public function callback( $post ) {
		echo "<div class='srp_mb_manage'>\n";

		// Add nonce
		echo "<input type='hidden' name='srp-ajax-nonce' id='srp-ajax-nonce' value='" . wp_create_nonce( 'srp-ajax-nonce-omgrandomword' ) . "' />\n";

		// Output plugin URL in hidden val
		echo "<input type='hidden' name='srp-dir-img' id='srp-dir-img' value='" . plugins_url( '/assets/images/', Simple_Related_Posts::get_plugin_file() ) . "' />\n";

		// Create a Post Link Manager object
		$post_link_manager = new SRP_Post_Link_Manager();

		// Get the children
		$children = $post_link_manager->get_children( $post->ID );

		echo "<div class='srp_button_holder'>\n";


		// Build the related post link
		$url = get_admin_url() . "admin.php?page=srp_link_related&amp;srp_parent=" . $post->ID;

		// WPML check
		if ( isset( $_GET['lang'] ) ) {
			$url .= "&amp;lang=" . $_GET['lang'];
		}

		echo "<span id='view-post-btn'>";
		echo "<a href='" . $url . "' class='button button-primary'>";
		_e( 'Add Related Posts', 'simple-related-posts' );
		echo "</a>";
		echo "</span>\n";


		echo "</div>\n";

		if ( count( $children ) > 0 ) {

			// Managet table
			echo "<table class='wp-list-table widefat fixed pages srp_table_manage sortable'>\n";

			echo "<tbody>\n";
			$i = 0;
			foreach ( $children as $link_id => $child ) {
				$child_id = $child->ID;

				$edit_url = get_admin_url() . "post.php?post={$child_id}&amp;action=edit&amp;srp_parent={$post->ID}";

				echo "<tr id='{$link_id}'>\n";
				echo "<td>";
				echo "<strong><a href='{$edit_url}' class='row-title' title='{$child->post_title}'>{$child->post_title}</a></strong>\n";
				echo "<div class='row-actions'>\n";
				echo "<span class='edit'><a href='{$edit_url}' title='" . __( 'Edit this item', 'simple-related-posts' ) . "'>";
				_e( 'Edit Post', 'simple-related-posts' );
				echo "</a> | </span>";
				echo "<span class='trash'><a class='submitdelete' title='" . __( 'Delete this item', 'simple-related-posts' ) . "' href='javascript:;'>";
				_e( 'Delete Post', 'simple-related-posts' );
				echo "</a></span>";
				echo "</div>\n";
				echo "</td>\n";
				echo "</tr>\n";
				$i ++;
			}
			echo "</tbody>\n";
			echo "</table>\n";

		} else {

			echo '<br/>';
			_e( 'No related posts found.', 'simple-related-posts' );
		}

		// Reset Post Data
		wp_reset_postdata();

		echo "</div>\n";
	}

}