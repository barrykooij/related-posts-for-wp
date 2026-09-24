<?php
/**
 * The deprecated RP4WP_Constants class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Admin\MetaBox\Ajax;
use LV2\WordPress\RelatedPostsForWP\Admin\Notices\Review;
use LV2\WordPress\RelatedPostsForWP\Admin\Wizard\Page;
use LV2\WordPress\RelatedPostsForWP\Install\Installer;
use LV2\WordPress\RelatedPostsForWP\Links\LinkPostType;

/**
 * The 2.x constants. Each one now lives on the class it belongs to; reading one here gives no notice.
 *
 * @deprecated 3.0.0 Use the class constant each constant points to.
 */
abstract class RP4WP_Constants {

	/**
	 * The link post type. Use LinkPostType::POST_TYPE.
	 */
	public const LINK_PT = LinkPostType::POST_TYPE;

	/**
	 * Link meta: the post type of the parent. Use LinkPostType::META_PARENT_POST_TYPE.
	 */
	public const PM_PT_PARENT = LinkPostType::META_PARENT_POST_TYPE;

	/**
	 * Link meta: the parent. Use LinkPostType::META_PARENT.
	 */
	public const PM_PARENT = LinkPostType::META_PARENT;

	/**
	 * Link meta: the child. Use LinkPostType::META_CHILD.
	 */
	public const PM_CHILD = LinkPostType::META_CHILD;

	/**
	 * Post meta of posts that were linked automatically. Use LinkPostType::META_AUTO_LINKED.
	 */
	public const PM_POST_AUTO_LINKED = LinkPostType::META_AUTO_LINKED;

	/**
	 * The option that starts the installation wizard. Use Installer::OPTION_DO_INSTALL.
	 */
	public const OPTION_DO_INSTALL = Installer::OPTION_DO_INSTALL;

	/**
	 * The option set while the installation wizard runs. Use Wizard\Page::OPTION_IS_INSTALLING.
	 */
	public const OPTION_IS_INSTALLING = Page::OPTION_IS_INSTALLING;

	/**
	 * The option with the installation date. Use Review::OPTION_INSTALL_DATE.
	 */
	public const OPTION_INSTALL_DATE = Review::OPTION_INSTALL_DATE;

	/**
	 * The user meta that hides the review notice. Use Review::DISMISS_KEY.
	 */
	public const OPTION_ADMIN_NOTICE_KEY = Review::DISMISS_KEY;

	/**
	 * The nonce action of the installation wizard. Use Wizard\Page::NONCE.
	 */
	public const NONCE_INSTALL = Page::NONCE;

	/**
	 * The nonce action of the meta box AJAX requests. Use MetaBox\Ajax::NONCE.
	 */
	public const NONCE_AJAX = Ajax::NONCE;
}
