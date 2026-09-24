<?php
/**
 * Plugin Name: RP4WP upgrade test: link order
 * Description: Test-only. 2.x leaves the order of links with the same menu_order to the database; order them by ID, as
 * 3.x does (known issue K1), so the upgrade comparison only shows real changes.
 *
 * @package RelatedPostsForWP
 */

add_filter(
	'rp4wp_get_children_link_args',
	static function ( $args ) {
		$args['orderby'] = [
			'menu_order' => 'ASC',
			'ID'         => 'ASC',
		];

		return $args;
	}
);
