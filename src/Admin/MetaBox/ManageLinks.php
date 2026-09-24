<?php
/**
 * The related posts meta box class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\MetaBox;

use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Module;
use LV2\WordPress\RelatedPostsForWP\PostTypes;

/**
 * The "Related Posts" meta box in the post editor: lists the related posts, which can be reordered and unlinked,
 * and links to the screen that adds more.
 */
class ManageLinks implements Module {

	/**
	 * The meta box ID.
	 */
	public const ID = 'rp4wp_metabox_related_posts';

	/**
	 * Set up on admin_init.
	 *
	 * @return void
	 */
	public static function setup(): void {
		LegacyHooks::add_action( 'RP4WP_Hook_Meta_Box', 'admin_init', [ self::class, 'init' ] );
	}

	/**
	 * Add the meta box when WordPress adds meta boxes.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( is_admin() ) {
			add_action( 'add_meta_boxes', [ self::class, 'add' ] );
		}
	}

	/**
	 * Add the meta box to the supported post types.
	 *
	 * @return void
	 */
	public static function add(): void {
		add_meta_box( self::ID, __( 'Related Posts', 'related-posts-for-wp' ), [ self::class, 'render' ], PostTypes::supported(), 'normal', 'core' );
	}

	/**
	 * The meta box content.
	 *
	 * @param \WP_Post $post The post being edited.
	 *
	 * @return void
	 */
	public static function render( $post ): void {
		echo "<div class='rp4wp_mb_manage'>\n";
		echo "<input type='hidden' name='rp4wp-ajax-nonce' id='rp4wp-ajax-nonce' value='" . esc_attr( wp_create_nonce( Ajax::NONCE ) ) . "' />\n";
		echo "<input type='hidden' name='rp4wp-dir-img' id='rp4wp-dir-img' value='" . esc_attr( plugins_url( '/assets/images/', Main::file() ) ) . "' />\n";

		$children = ( new LinkRepository() )->get_children( (int) $post->ID );

		echo "<div class='rp4wp_button_holder'>\n";

		$url = get_admin_url() . 'admin.php?page=rp4wp_link_related&amp;rp4wp_parent=' . (int) $post->ID;

		// Keep the WPML language when going to the link screen.
		if ( isset( $_GET['lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only passed along.
			$url .= '&amp;lang=' . esc_attr( sanitize_text_field( wp_unslash( $_GET['lang'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only passed along.
		}

		echo "<span id='view-post-btn'>";
		echo "<a href='" . $url . "' class='button button-primary'>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from integers, a constant and an escaped value.
		esc_html_e( 'Add Related Posts', 'related-posts-for-wp' );
		echo '</a>';
		echo "</span>\n";
		echo "</div>\n";

		// Links to deleted posts have no post (known issue K2).
		$children = array_filter( $children );

		if ( count( $children ) > 0 ) {
			echo "<table class='wp-list-table widefat fixed pages rp4wp_table_manage sortable'>\n";
			echo "<tbody>\n";

			foreach ( $children as $link_id => $child ) {
				$edit_url = get_admin_url() . 'post.php?post=' . (int) $child->ID . '&amp;action=edit&amp;rp4wp_parent=' . (int) $post->ID;

				echo "<tr id='" . (int) $link_id . "'>\n";
				echo '<td>';
				echo "<strong><a href='" . $edit_url . "' class='row-title' title='" . esc_attr( $child->post_title ) . "'>" . esc_html( $child->post_title ) . "</a></strong>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $edit_url is built from integers.
				echo "<div class='row-actions'>\n";
				echo "<span class='edit'><a href='" . $edit_url . "' title='" . esc_attr__( 'Edit Post', 'related-posts-for-wp' ) . "'>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $edit_url is built from integers.
				esc_html_e( 'Edit Post', 'related-posts-for-wp' );
				echo '</a> | </span>';
				echo "<span class='trash'><a class='submitdelete' title='" . esc_attr__( 'Unlink Related Post', 'related-posts-for-wp' ) . "' href='javascript:;'>";
				esc_html_e( 'Unlink Related Post', 'related-posts-for-wp' );
				echo '</a></span>';
				echo "</div>\n";
				echo "</td>\n";
				echo "</tr>\n";
			}

			echo "</tbody>\n";
			echo "</table>\n";
		} else {
			echo '<br/>';
			esc_html_e( 'No related posts found.', 'related-posts-for-wp' );
		}

		wp_reset_postdata();

		echo "</div>\n";
	}
}
