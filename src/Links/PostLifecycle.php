<?php
/**
 * The post lifecycle class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Links;

use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Module;
use LV2\WordPress\RelatedPostsForWP\PostTypes;
use LV2\WordPress\RelatedPostsForWP\Related\Linker;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;

/**
 * Keeps words and links up to date when posts are published, unpublished and deleted.
 */
class PostLifecycle implements Module {

	/**
	 * Hook into the post lifecycle, in the order and with the priorities of 2.x.
	 *
	 * @return void
	 */
	public static function setup(): void {
		LegacyHooks::add_action( 'RP4WP_Hook_Delete_Words', 'delete_post', [ self::class, 'delete_words' ], 10, 1 );
		LegacyHooks::add_action( 'RP4WP_Hook_Related_Auto_Link', 'transition_post_status', [ self::class, 'auto_link' ], 11, 3 );
		LegacyHooks::add_action( 'RP4WP_Hook_Related_Update_Link', 'transition_post_status', [ self::class, 'update_links' ], 11, 3 );
		LegacyHooks::add_action( 'RP4WP_Hook_Related_Save_Words', 'transition_post_status', [ self::class, 'save_words' ], 10, 3 );
	}

	/**
	 * Cache the words of a post when it is published.
	 *
	 * @param string   $new_status The new status.
	 * @param string   $old_status The old status.
	 * @param \WP_Post $post       The post.
	 *
	 * @return void
	 */
	public static function save_words( $new_status, $old_status, $post ): void {
		if ( ! self::is_relevant( $post ) || 'publish' !== $new_status ) {
			return;
		}

		( new Cache() )->save_post( (int) $post->ID );
	}

	/**
	 * Link a post to its related posts when it is published, if automatic linking is on and it was not linked yet.
	 *
	 * @param string   $new_status The new status.
	 * @param string   $old_status The old status.
	 * @param \WP_Post $post       The post.
	 *
	 * @return void
	 */
	public static function auto_link( $new_status, $old_status, $post ): void {
		if ( ! self::is_relevant( $post ) || 'publish' !== $new_status || ! self::is_auto_linking_on() ) {
			return;
		}

		if ( 1 == get_post_meta( $post->ID, LinkPostType::META_AUTO_LINKED, true ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Meta is stored as "1".
			return;
		}

		( new Linker() )->link_post( (int) $post->ID, (int) Main::get()->settings()->get( 'automatic_linking_post_amount' ) );

		update_post_meta( $post->ID, LinkPostType::META_AUTO_LINKED, 1 );
	}

	/**
	 * When an automatically linked post stops being published, remove its links and give each post that showed it a
	 * new related post instead.
	 *
	 * @param string   $new_status The new status.
	 * @param string   $old_status The old status.
	 * @param \WP_Post $post       The post.
	 *
	 * @return void
	 */
	public static function update_links( $new_status, $old_status, $post ): void {
		if ( ! self::is_relevant( $post ) || 'publish' !== $old_status || 'publish' === $new_status || ! self::is_auto_linking_on() ) {
			return;
		}

		if ( 1 != get_post_meta( $post->ID, LinkPostType::META_AUTO_LINKED, true ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Meta is stored as "1".
			return;
		}

		$links   = new LinkRepository();
		$parents = $links->get_parents( (int) $post->ID );

		$links->delete_links_related_to( (int) $post->ID );

		$linker = new Linker();
		foreach ( $parents as $parent ) {
			// 2.x keeps links to deleted posts (known issue K2); there is nothing to relink for those.
			if ( null !== $parent ) {
				$linker->link_post( (int) $parent->ID, 1 );
			}
		}

		delete_post_meta( $post->ID, LinkPostType::META_AUTO_LINKED );
	}

	/**
	 * Remove the cached words of a deleted post, for users who can delete posts (known issue K5).
	 *
	 * @param int $post_id The post.
	 *
	 * @return void
	 */
	public static function delete_words( $post_id ): void {
		if ( ! current_user_can( 'delete_posts' ) ) {
			return;
		}

		( new Cache() )->delete_post( (int) $post_id );
	}

	/**
	 * Whether a status change of this post matters: not an autosave, and a supported post type.
	 *
	 * @param mixed $post The post.
	 *
	 * @return bool
	 */
	private static function is_relevant( $post ): bool {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		return $post instanceof \WP_Post && in_array( $post->post_type, PostTypes::supported(), true );
	}

	/**
	 * Whether automatic linking is on.
	 *
	 * @return bool
	 */
	private static function is_auto_linking_on(): bool {
		return 1 == Main::get()->settings()->get( 'automatic_linking' ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Stored as 1 or "1".
	}
}
