<?php
/**
 * The deprecated RP4WP_Hook_Ajax_Install_Link_Posts class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\Install\Jobs\Queue;

/**
 * The 2.x AJAX hook that links a batch of posts in the installation wizard.
 *
 * The installer runs in the background since 3.0, so the AJAX action rp4wp_install_link_posts is gone. The class stays,
 * empty, so code that creates or unhooks it keeps working.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Install\Jobs\Queue, or the REST route rp4wp/v1/install.
 */
class RP4WP_Hook_Ajax_Install_Link_Posts extends RP4WP_Hook {

	/**
	 * The 2.x action, which nothing answers any more.
	 *
	 * @var string
	 */
	protected $tag = 'wp_ajax_rp4wp_install_link_posts';

	/**
	 * Constructor. Unlike 2.x, it adds nothing to the action.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, Queue::class );
	}

	/**
	 * Did: Link a batch of posts. Does nothing now.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function run() {
		Deprecation::method( __METHOD__, Queue::class . '::start()' );
	}
}
