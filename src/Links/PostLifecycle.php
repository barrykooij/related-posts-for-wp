<?php
/**
 * The post lifecycle class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Links;

use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Install\Jobs\Queue;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Module;
use LV2\WordPress\RelatedPostsForWP\PostTypes;
use LV2\WordPress\RelatedPostsForWP\Related\Linker;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;

/**
 * Keeps words and links up to date when posts are published, unpublished and deleted.
 *
 * A post that stops being published loses the automatic links to it, and the posts that showed it get a new related
 * post; links added by hand to it stay, hidden until it is published again (known issue P20). A post that is deleted
 * loses every link to and from it (known issue K2).
 *
 * While the links can't be written (a migration moves them into the links table), a post that is published, or a
 * linked post that is unpublished, waits (see Deferred), and is handled on the first request after the move.
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

		add_action( 'transition_post_status', [ self::class, 'remove_words' ], 10, 3 );
		// After the words are gone (priority 10), so the posts that showed the deleted post can't find it again.
		add_action( 'delete_post', [ self::class, 'delete_links' ], 11, 1 );
		add_action( 'init', [ self::class, 'run_deferred' ], 20 );
	}

	/**
	 * Handle the posts that waited while the links could not be written.
	 *
	 * @return void
	 */
	public static function run_deferred(): void {
		// Not while a job runs: it would link posts before every post has its words.
		if ( ! Deferred::has() || ! ( new LinkRepository() )->can_write() || Queue::running() ) {
			return;
		}

		foreach ( Deferred::take() as $post_id => $what ) {
			if ( Deferred::FORGET === $what ) {
				self::forget_links( (int) $post_id );

				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			if ( Deferred::LINK === $what && 'publish' === $post->post_status ) {
				self::auto_link( 'publish', 'new', $post );
			} elseif ( Deferred::UNLINK === $what && 'publish' !== $post->post_status ) {
				self::update_links( $post->post_status, 'publish', $post );
			}
		}
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
	 * Remove the cached words of a post that stops being published (a draft, private, scheduled or in the trash), so
	 * it is not found as a related post by its words, and no words of it are left behind (known issue K5).
	 *
	 * @param string   $new_status The new status.
	 * @param string   $old_status The old status.
	 * @param \WP_Post $post       The post.
	 *
	 * @return void
	 */
	public static function remove_words( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof \WP_Post || 'publish' !== $old_status || 'publish' === $new_status ) {
			return;
		}

		( new Cache() )->delete_post( (int) $post->ID );
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

		$links = new LinkRepository();
		if ( ! $links->can_write() || Queue::running() ) {
			// While the links move, or while a job (such as an installation or the update) caches the words of every
			// post, the post waits, and is linked when that is done, with the words of every post in place.
			Deferred::add( (int) $post->ID, Deferred::LINK );

			return;
		}

		if ( PostState::is_linked( (int) $post->ID ) ) {
			return;
		}

		// Marks the post as linked.
		( new Linker( null, $links ) )->link_post( (int) $post->ID, (int) Main::get()->settings()->get( 'automatic_linking_post_amount' ) );

		// A post published again shows in the posts that link to it by hand; they drop an automatic link to keep their
		// amount (known issue P20).
		self::relink( $links->manual_parents( (int) $post->ID ), $links );
	}

	/**
	 * When a post stops being published, remove the automatic links to it and give each post that showed it a new
	 * related post instead, by difference. Links added by hand to it stay: they are hidden while it is not published and
	 * show again when it is (known issue P20; 2.x removed them, and only for a post that was linked automatically
	 * itself). Its own links stay; it is linked again, by difference, when it is published again.
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

		$links = new LinkRepository();
		if ( ! $links->can_write() ) {
			Deferred::add( (int) $post->ID, Deferred::UNLINK );

			return;
		}

		// The posts that link to it by hand show one related post fewer now, so they are relinked too.
		$parents = array_values( array_unique( array_merge( $links->automatic_parents( (int) $post->ID ), $links->manual_parents( (int) $post->ID ) ) ) );
		$links->delete_automatic_to( (int) $post->ID );
		self::relink( $parents, $links );

		PostState::unmark_linked( (int) $post->ID );
	}

	/**
	 * When a post is deleted, whoever deletes it, remove every link to and from it and give each post that showed it a
	 * new related post (known issue K2: 2.x kept the links, which then pointed at nothing).
	 *
	 * @param int $post_id The post.
	 *
	 * @return void
	 */
	public static function delete_links( $post_id ): void {
		$post = get_post( (int) $post_id );
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, PostTypes::supported(), true ) ) {
			return;
		}

		if ( ! ( new LinkRepository() )->can_write() ) {
			Deferred::add( (int) $post_id, Deferred::FORGET );

			return;
		}

		self::forget_links( (int) $post_id );
	}

	/**
	 * Remove every link to and from a post, and relink the posts that showed it, by difference.
	 *
	 * @param int $post_id The post.
	 *
	 * @return void
	 */
	private static function forget_links( int $post_id ): void {
		$links   = new LinkRepository();
		$parents = [];
		foreach ( $links->get_parents( $post_id ) as $parent ) {
			if ( null !== $parent && $parent->ID !== $post_id ) {
				$parents[] = (int) $parent->ID;
			}
		}

		$links->delete_links_related_to( $post_id );

		if ( self::is_auto_linking_on() ) {
			self::relink( array_values( array_unique( $parents ) ), $links );
		}
	}

	/**
	 * Relink posts that lost a related post, by difference, when they are published and were linked automatically.
	 *
	 * @param int[]          $post_ids The posts.
	 * @param LinkRepository $links    The links.
	 *
	 * @return void
	 */
	private static function relink( array $post_ids, LinkRepository $links ): void {
		$linker = new Linker( null, $links );
		$amount = (int) Main::get()->settings()->get( 'automatic_linking_post_amount' );

		foreach ( $post_ids as $post_id ) {
			if ( 'publish' === get_post_status( $post_id ) && PostState::is_linked( $post_id ) ) {
				$linker->relink_post( $post_id, $amount );
			}
		}
	}

	/**
	 * Remove the cached words and the marks of a deleted post, whoever deletes it: 2.x only did it for users who can
	 * delete posts, so posts deleted by cron or WP-CLI left their words (known issue K5).
	 *
	 * @param int $post_id The post.
	 *
	 * @return void
	 */
	public static function delete_words( $post_id ): void {
		( new Cache() )->delete_post( (int) $post_id );
		PostState::forget( (int) $post_id );
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
