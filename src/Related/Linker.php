<?php
/**
 * The related posts linker class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Related;

use LV2\WordPress\RelatedPostsForWP\Database\Transaction;
use LV2\WordPress\RelatedPostsForWP\Links\Deferred;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Links\PostState;

/**
 * Links posts to their most related posts automatically, and links them again.
 *
 * Links added by hand keep their place: new automatic links take the free positions, in order of relevance. The
 * premium add-on extends this class with its own finder and force fill.
 */
class Linker {

	/**
	 * The related posts finder. The premium add-on brings its own.
	 *
	 * @var Finder
	 */
	private Finder $finder;

	/**
	 * The link repository.
	 *
	 * @var LinkRepository
	 */
	protected LinkRepository $links;

	/**
	 * Constructor.
	 *
	 * @param Finder|null         $finder The finder; a new one when null.
	 * @param LinkRepository|null $links  The link repository; a new one when null.
	 */
	public function __construct( ?Finder $finder = null, ?LinkRepository $links = null ) {
		$this->finder = $finder ?? new Finder();
		$this->links  = $links ?? new LinkRepository();
	}

	/**
	 * Link a post to its most related posts, and mark it as linked. Related posts it links to already are skipped.
	 * The new links come after the links it has, in order of relevance, as in 2.x; or, with `$fill`, they take the
	 * free places from the top, so the links it has keep their places.
	 *
	 * While the links can't be written (see LinkRepository::can_write()), the post waits until they can.
	 *
	 * @param int  $post_id The post.
	 * @param int  $amount  How many related posts to add.
	 * @param bool $fill    Whether the new links take the free places instead of coming last.
	 *
	 * @return void
	 */
	public function link_post( int $post_id, int $amount, bool $fill = false ): void {
		if ( ! $this->links->can_write() ) {
			Deferred::add( $post_id, Deferred::LINK );

			return;
		}

		if ( $amount > 0 ) {
			$rows      = $this->links->link_rows( $post_id );
			$children  = array_values( array_diff( $this->related( $post_id, $amount ), array_column( $rows, 'child' ) ) );
			$positions = array_column( $rows, 'position' );

			if ( ! $fill && count( $positions ) > 0 ) {
				$positions = range( 0, max( $positions ) );
			}

			$this->links->insert( $post_id, self::places( $children, $positions ), false );
		}

		PostState::mark_linked( $post_id );
	}

	/**
	 * Link every post that was not linked yet.
	 *
	 * @param int $amount      How many related posts to link per post.
	 * @param int $post_amount The maximum number of posts to link; -1 for all.
	 *
	 * @return void
	 */
	public function link_all( int $amount, int $post_amount = -1 ): void {
		foreach ( $this->finder->not_auto_linked_post_ids( $post_amount ) as $post_id ) {
			$this->link_post( (int) $post_id, $amount );
		}
	}

	/**
	 * Link a post to its most related posts again, without a moment in which it shows fewer of them.
	 *
	 * Links added by hand stay, with their place. The automatic links are compared with the related posts found now:
	 * links to posts that are still related stay, links to the others are replaced. The new links are added before the
	 * old ones go, all in one transaction, and the automatic links take the free places in order of relevance. When
	 * nothing is found at all, for example because the words of the post are gone, the post keeps its links.
	 *
	 * @param int $post_id The post.
	 * @param int $amount  How many related posts the post has, links added by hand included.
	 *
	 * @return void
	 *
	 * @throws \Throwable When a write fails; nothing of the relink stays then.
	 */
	public function relink_post( int $post_id, int $amount ): void {
		if ( ! $this->links->can_write() ) {
			return;
		}

		$manual    = [];
		$automatic = [];
		foreach ( $this->links->link_rows( $post_id ) as $link_id => $row ) {
			if ( $row['manual'] ) {
				$manual[ $link_id ] = $row;
			} else {
				$automatic[ $link_id ] = $row;
			}
		}

		$wanted   = max( 0, $amount - count( $manual ) );
		$children = $wanted > 0 ? $this->wanted_children( $post_id, $wanted, array_column( $manual, 'child' ), array_column( $automatic, 'child' ) ) : [];

		if ( $wanted > 0 && count( $children ) < 1 && count( $automatic ) > 0 ) {
			PostState::mark_linked( $post_id );

			return;
		}

		$kept  = [];
		$stale = [];
		foreach ( $automatic as $link_id => $row ) {
			if ( in_array( $row['child'], $children, true ) && ! isset( $kept[ $row['child'] ] ) ) {
				$kept[ $row['child'] ] = (int) $link_id;
			} else {
				$stale[] = (int) $link_id;
			}
		}

		$places  = self::places( $children, array_column( $manual, 'position' ) );
		$missing = array_diff_key( $places, $kept );
		$moves   = [];
		foreach ( $kept as $child_id => $link_id ) {
			if ( $automatic[ $link_id ]['position'] !== $places[ $child_id ] ) {
				$moves[ $link_id ] = $places[ $child_id ];
			}
		}

		$added = Transaction::run(
			function () use ( $post_id, $missing, $moves, $stale ): array {
				$added = $this->links->insert( $post_id, $missing, false );
				$this->links->set_positions( $moves );
				$this->links->delete_many( $stale );

				return $added;
			}
		);

		PostState::mark_linked( $post_id );

		/**
		 * Fires after a post was linked to its related posts again.
		 *
		 * @since 3.0.0
		 *
		 * @param int   $post_id     The post.
		 * @param int[] $added_ids   The links that were added.
		 * @param int[] $removed_ids The links that were removed.
		 */
		do_action( 'rp4wp_post_relinked', $post_id, array_values( $added ), $stale );
	}

	/**
	 * The time to mark a link with, in milliseconds since the Unix epoch.
	 *
	 * @return int
	 */
	public static function now(): int {
		return PostState::now();
	}

	/**
	 * The posts to link a post to, most related first.
	 *
	 * @param int $post_id The post.
	 * @param int $amount  How many.
	 *
	 * @return int[]
	 */
	protected function related( int $post_id, int $amount ): array {
		return self::ids( $this->finder->related_posts( $post_id, $amount ) );
	}

	/**
	 * The posts a post should be linked to automatically now, most related first: the most related posts, leaving out
	 * the posts it links to by hand.
	 *
	 * @param int   $post_id The post.
	 * @param int   $wanted  How many.
	 * @param int[] $manual  The posts it links to by hand.
	 * @param int[] $current The posts it links to automatically now.
	 *
	 * @return int[]
	 */
	protected function wanted_children( int $post_id, int $wanted, array $manual, array $current ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Premium's force fill uses $current.
		return array_slice( self::ids( $this->finder->related_posts( $post_id, $wanted, $manual ) ), 0, $wanted );
	}

	/**
	 * The places of new links: the positions that are free, from the top, in the order of the posts.
	 *
	 * @param int[] $children The posts, in order.
	 * @param int[] $taken    The positions that are taken.
	 *
	 * @return array<int, int> Post => position.
	 */
	protected static function places( array $children, array $taken ): array {
		$taken  = array_flip( array_map( 'intval', $taken ) );
		$place  = 0;
		$places = [];

		foreach ( $children as $child_id ) {
			while ( isset( $taken[ $place ] ) ) {
				++$place;
			}

			$places[ (int) $child_id ] = $place;
			++$place;
		}

		return $places;
	}

	/**
	 * The post IDs of finder results, without duplicates.
	 *
	 * @param array<int, object> $results The results.
	 *
	 * @return int[]
	 */
	protected static function ids( array $results ): array {
		return array_values(
			array_unique(
				array_map(
					static function ( $result ): int {
						return (int) $result->ID;
					},
					$results
				)
			)
		);
	}
}
