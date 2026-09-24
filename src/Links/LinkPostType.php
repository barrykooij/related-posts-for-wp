<?php
/**
 * The link post type class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Links;

use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * The hidden post type that stores one "post A shows post B as related" link per post.
 */
class LinkPostType implements Module {

	/**
	 * The post type.
	 */
	public const POST_TYPE = 'rp4wp_link';

	/**
	 * Meta key on a link: the post that shows the related post.
	 */
	public const META_PARENT = 'rp4wp_parent';

	/**
	 * Meta key on a link: the related post.
	 */
	public const META_CHILD = 'rp4wp_child';

	/**
	 * Meta key on a link: the post type of the parent (written by premium).
	 */
	public const META_PARENT_POST_TYPE = 'rp4wp_pt_parent';

	/**
	 * Meta key on a content post: its related posts were linked automatically.
	 */
	public const META_AUTO_LINKED = 'rp4wp_auto_linked';

	/**
	 * The title of every link post.
	 */
	public const TITLE = 'Related Posts for WordPress Link';

	/**
	 * Register the post type on init.
	 *
	 * @return void
	 */
	public static function setup(): void {
		LegacyHooks::add_action( 'RP4WP_Hook_Post_Type', 'init', [ self::class, 'register' ] );
	}

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'public' => false,
				'label'  => self::TITLE,
			]
		);
	}
}
