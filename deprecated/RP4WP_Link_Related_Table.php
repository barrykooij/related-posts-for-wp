<?php
/**
 * The deprecated RP4WP_Link_Related_Table class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen\ListTable;
use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * The 2.x list table of the link screen.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen\ListTable.
 */
class RP4WP_Link_Related_Table extends ListTable {

	/**
	 * Constructor.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, ListTable::class );

		parent::__construct();
	}
}
