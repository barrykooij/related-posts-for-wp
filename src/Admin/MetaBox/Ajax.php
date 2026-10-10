<?php
/**
 * The meta box AJAX class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\MetaBox;

use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
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

		$links = new LinkRepository();
		if ( ! $links->can_write() ) {
			self::moving();
		}

		$link = $links->find( $link_id );
		if ( null === $link || ! current_user_can( 'edit_post', $link['parent'] ) ) {
			return;
		}

		$links->delete( $link_id );

		wp_send_json( [ 'success' => true ] );
	}

	/**
	 * Save the order of the links after dragging, for links the user may edit.
	 *
	 * @return void
	 */
	public static function sort(): void {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		if ( ! isset( $_POST['rp4wp_items'] ) || ! is_string( $_POST['rp4wp_items'] ) ) {
			return;
		}

		$items = explode( ',', sanitize_text_field( wp_unslash( $_POST['rp4wp_items'] ) ) );

		$links = new LinkRepository();
		if ( ! $links->can_write() ) {
			self::moving();
		}

		$positions = [];
		foreach ( $items as $order => $item_id ) {
			$link = $links->find( absint( $item_id ) );

			if ( null !== $link && current_user_can( 'edit_post', $link['parent'] ) ) {
				$positions[ absint( $item_id ) ] = (int) $order;
			}
		}

		$links->set_positions( $positions );

		wp_send_json( [ 'success' => true ] );
	}

	/**
	 * Answer that links can't be changed now, because a migration moves them into the links table.
	 *
	 * @return void
	 */
	private static function moving(): void {
		wp_send_json(
			[
				'success' => false,
				'message' => __( 'Related Posts for WordPress is moving its links to a new database table. Please try again in a moment.', 'related-posts-for-wp' ),
			]
		);
	}
}
