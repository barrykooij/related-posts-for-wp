<?php
/**
 * The algorithm contract file of the quality harness.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Quality;

/**
 * What the quality harness measures: the way a plugin edition finds related posts. Each edition has an adapter; when
 * the code that finds related posts changes, its adapter changes with it, and the corpora and metrics stay the same.
 */
interface Algorithm {

	/**
	 * The name in the results, for example `free` or `premium`.
	 *
	 * @return string
	 */
	public function name(): string;

	/**
	 * Cache the words of the posts, as an installation does once every post is in place: pass one for every post, then
	 * pass two over all of them.
	 *
	 * @param int[] $post_ids The posts.
	 *
	 * @return void
	 */
	public function index( array $post_ids ): void;

	/**
	 * The related posts of a post, most related first, as an installation would link them.
	 *
	 * @param int $post_id The post.
	 * @param int $limit   How many.
	 *
	 * @return int[]
	 */
	public function related( int $post_id, int $limit ): array;

	/**
	 * The tokens stored for a post.
	 *
	 * @param int $post_id The post.
	 *
	 * @return string[]
	 */
	public function tokens( int $post_id ): array;

	/**
	 * Store the words of posts directly, without extracting them, for the scale test.
	 *
	 * @param array<int, array<string, int>> $vectors Post ID => word => how many times it counts.
	 *
	 * @return void
	 */
	public function seed( array $vectors ): void;

	/**
	 * Finish seeding: count the document frequencies and weigh the words of every seeded post.
	 *
	 * @return void
	 */
	public function seeded(): void;
}
