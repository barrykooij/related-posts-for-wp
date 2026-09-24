<?php
/**
 * The Yoast Duplicate Post integration class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Integrations;

use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Links\LinkPostType;
use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * A copy made with Yoast Duplicate Post is a new post, so it must not inherit the "linked automatically" flag.
 */
class YoastDuplicatePost implements Module {

	/**
	 * Hook into Yoast Duplicate Post.
	 *
	 * @return void
	 */
	public static function setup(): void {
		LegacyHooks::add_filter( 'RP4WP_Filter_Yoast_Duplicate_Post', 'duplicate_post_excludelist_filter', [ self::class, 'exclude_meta' ] );
	}

	/**
	 * Leave the plugin's meta out of copies.
	 *
	 * @param mixed $meta_excludelist The meta keys Yoast Duplicate Post leaves out.
	 *
	 * @return array<int, string>
	 */
	public static function exclude_meta( $meta_excludelist ): array {
		return array_merge( (array) $meta_excludelist, [ LinkPostType::META_AUTO_LINKED ] );
	}
}
