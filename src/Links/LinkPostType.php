<?php
/**
 * The link post type class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Links;

/**
 * The hidden post type that stores one "post A shows post B as related" link per post.
 */
class LinkPostType {

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
}
