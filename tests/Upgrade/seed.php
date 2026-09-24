<?php
/**
 * Uses the site with 2.x, the way a site owner does: activation, the installation wizard, settings, new posts that are
 * linked automatically, and a link added and moved by hand. Run with `wp eval-file` while 2.x is active.
 *
 * @package RelatedPostsForWP
 */

// Activation only runs in the admin in 2.x (known issue K3), so run it the way the admin would.
require_once WP_PLUGIN_DIR . '/related-posts-for-wp/includes/installer-functions.php';
rp4wp_activate_plugin();

// The installation wizard: cache the words of every post, link 3 related posts to each, and finish.
( new RP4WP_Related_Word_Manager() )->save_all_words();
( new RP4WP_Related_Post_Manager() )->link_related_posts( 3 );
delete_option( 'rp4wp_do_install' );

update_option(
	'rp4wp',
	array_merge(
		RP4WP()->settings->get_options(),
		[
			'heading_text'   => 'You might also like',
			'excerpt_length' => 8,
		]
	)
);

// New posts are cached and linked when they are published.
$rp4wp_latte = wp_insert_post(
	[
		'post_title'   => 'Latte art',
		'post_content' => 'Latte art starts with a good espresso shot and silky steamed milk poured into the coffee cup.',
		'post_status'  => 'publish',
	]
);
wp_insert_post(
	[
		'post_title'   => 'Mountain huts',
		'post_content' => 'Mountain huts give hikers a bed and a warm meal on hiking trips through the mountains.',
		'post_status'  => 'publish',
	]
);

// A link added by hand, moved to the top like the meta box does.
$rp4wp_trail = get_page_by_path( 'trail-running', OBJECT, 'post' );
$rp4wp_link  = ( new RP4WP_Post_Link_Manager() )->add( $rp4wp_latte, $rp4wp_trail->ID );
wp_update_post(
	[
		'ID'         => $rp4wp_link,
		'menu_order' => -1,
	]
);

echo "2.x site built.\n";
