<?php
/**
 * The meta box AJAX class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\MetaBox;

use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Links\LinkPostType;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * The AJAX actions behind the meta box: unlink a related post, and save the order after dragging.
 */
class Ajax implements Module {

	/**
	 * The nonce action of the admin AJAX requests.
	 */
	public const NONCE = 'rp4wp-ajax-nonce-omgrandomword';

	/**
	 * Register the AJAX actions.
	 *
	 * @return void
	 */
	public static function setup(): void {
		LegacyHooks::add_action( 'RP4WP_Hook_Ajax_Delete_Link', 'wp_ajax_rp4wp_delete_link', [ self::class, 'delete_link' ] );
		LegacyHooks::add_action( 'RP4WP_Hook_Meta_Box_Ajax_Sort', 'wp_ajax_rp4wp_related_sort', [ self::class, 'sort' ] );
	}

	/**
	 * Remove a link. Only for users who can edit the post the link belongs to.
	 *
	 * @return void
	 */
	public static function delete_link(): void {
		if ( ! isset( $_POST['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked below, like 2.x.
			wp_die();
		}

		$link_id = absint( $_POST['id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked on the next line.

		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$link = get_post( $link_id );
		if ( null === $link || LinkPostType::POST_TYPE !== $link->post_type || ! self::can_edit_parent( $link->ID ) ) {
			return;
		}

		( new LinkRepository() )->delete( $link->ID );

		wp_send_json( [ 'success' => true ] );
	}

	/**
	 * Save the order of the links after dragging, for links the user may edit.
	 *
	 * @return void
	 */
	public static function sort(): void {
		global $wpdb;

		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		if ( ! isset( $_POST['rp4wp_items'] ) || ! is_string( $_POST['rp4wp_items'] ) ) {
			return;
		}

		$items = explode( ',', sanitize_text_field( wp_unslash( $_POST['rp4wp_items'] ) ) );

		$order = 0;
		foreach ( $items as $item_id ) {
			$link = get_post( absint( $item_id ) );

			if ( null !== $link && LinkPostType::POST_TYPE === $link->post_type && self::can_edit_parent( $link->ID ) ) {
				$wpdb->update( $wpdb->posts, [ 'menu_order' => $order ], [ 'ID' => $link->ID ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One column, like 2.x.
			}

			++$order;
		}

		wp_send_json( [ 'success' => true ] );
	}

	/**
	 * Whether the current user can edit the post a link belongs to.
	 *
	 * @param int $link_id The link.
	 *
	 * @return bool
	 */
	private static function can_edit_parent( int $link_id ): bool {
		return current_user_can( 'edit_post', absint( get_post_meta( $link_id, LinkPostType::META_PARENT, true ) ) );
	}
}
