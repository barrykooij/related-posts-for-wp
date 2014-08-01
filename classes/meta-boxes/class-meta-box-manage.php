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
		echo "<div class='sp_mb_manage'>\n";

		// Add nonce
		echo "<input type='hidden' name='sp-ajax-nonce' id='sp-ajax-nonce' value='" . wp_create_nonce( 'post-connector-ajax-nonce-omgrandomword' ) . "' />\n";

		// Output plugin URL in hidden val
		echo "<input type='hidden' name='sp-dir-img' id='sp-dir-img' value='" . plugins_url( '/assets/images/', Simple_Related_Posts::get_plugin_file() ) . "' />\n";

		// Setup vars
		$sp_parent  = ( ( isset( $_GET['sp_parent'] ) ) ? $_GET['sp_parent'] : '' );
		$sp_pt_link = ( ( isset( $_GET['sp_pt_link'] ) ) ? $_GET['sp_pt_link'] : '' );

		// Create a Post Link Manager object
		$post_link_manager = new SRP_Post_Link_Manager();

		// Get the children
		$children = $post_link_manager->get_children( $post->ID );

		var_dump( $children );

		echo "<div class='pt_button_holder'>\n";


		// Build the Post Connector link existing post URL
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

			$table_classes = 'wp-list-table widefat fixed pages pt_table_manage';

			/**
			 * Action: 'pc_meta_box_manage_table_classes' - Allow adjusting meta box manage table classes
			 *
			 * @api string $table_classes The table classes
			 *
			 * @param SP_Connection $connection The connection
			 */
			$table_classes = apply_filters( 'pc_meta_box_manage_table_classes', $table_classes, $this->connection );

			// Managet table
			echo "<table class='" . $table_classes . "'>\n";

			echo "<tbody>\n";
			$i = 0;
			foreach ( $children as $link_id => $child ) {
				$child_id = $child->ID;

				$edit_url = get_admin_url() . "post.php?post={$child_id}&amp;action=edit&amp;sp_parent=" . SP_Parent_Param::generate_sp_parent_param( $post->ID, $sp_pt_link, $sp_parent, 0 ) . "&sp_pt_link=" . $this->connection->get_id();

				echo "<tr id='{$link_id}'>\n";
				echo "<td>";
				echo "<strong><a href='{$edit_url}' class='row-title' title='{$child->post_title}'>{$child->post_title}</a></strong>\n";
				echo "<div class='row-actions'>\n";
				echo "<span class='edit'><a href='{$edit_url}' title='" . __( 'Edit this item', 'post-connector' ) . "'>";
				_e( 'Edit', 'post-connector' );
				echo "</a> | </span>";
				echo "<span class='trash'><a class='submitdelete' title='" . __( 'Delete this item', 'post-connector' ) . "' href='javascript:;'>";
				_e( 'Delete', 'post-connector' );
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