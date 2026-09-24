<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * 2.x link screen table. The table now lives in LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen\ListTable; the link
 * screen still creates this class, so code that extends it keeps working.
 */
class RP4WP_Link_Related_Table extends \LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen\ListTable {
}
