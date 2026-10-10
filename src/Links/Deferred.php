<?php
/**
 * The deferred links class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Links;

/**
 * Posts whose links must change while the links can't be written: while a migration moves them into the links table.
 * A post that is published then is linked once the move is done; a related post that is unpublished then is replaced
 * then.
 */
final class Deferred {

	/**
	 * The option with the posts, by ID: what to do with each.
	 */
	public const OPTION = 'rp4wp_deferred_links';

	/**
	 * Link the post to its related posts.
	 */
	public const LINK = 'link';

	/**
	 * Remove the links from and to the post, which is no longer published.
	 */
	public const UNLINK = 'unlink';

	/**
	 * Remember what to do with a post; the latest wish counts.
	 *
	 * @param int    $post_id The post.
	 * @param string $what    LINK or UNLINK.
	 *
	 * @return void
	 */
	public static function add( int $post_id, string $what ): void {
		$deferred             = self::all();
		$deferred[ $post_id ] = $what;

		update_option( self::OPTION, $deferred, false );
	}

	/**
	 * Whether posts wait.
	 *
	 * @return bool
	 */
	public static function has(): bool {
		return count( self::all() ) > 0;
	}

	/**
	 * Take the posts that wait, so each is handled once.
	 *
	 * @return array<int, string> Post ID => LINK or UNLINK.
	 */
	public static function take(): array {
		$deferred = self::all();
		delete_option( self::OPTION );

		return $deferred;
	}

	/**
	 * The posts that wait.
	 *
	 * @return array<int, string>
	 */
	private static function all(): array {
		$deferred = get_option( self::OPTION, [] );

		return is_array( $deferred ) ? array_map( 'strval', $deferred ) : [];
	}
}
