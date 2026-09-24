<?php
/**
 * Prints what the plugin stores and shows on the upgrade test site, to compare before and after the upgrade. Runs with
 * `wp eval-file` against 2.x and 3.x, so it only uses what both have: WordPress, the options, the table, the template
 * tag, the shortcode and the widget.
 *
 * @package RelatedPostsForWP
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output for a diff.
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Each post is rendered as if it were the page shown.
global $wpdb, $post, $wp_query, $wp_the_query;

$rp4wp_posts = get_posts(
	[
		'post_type'      => 'post',
		'posts_per_page' => -1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	]
);

echo "== settings\n" . wp_json_encode( get_option( 'rp4wp' ), JSON_PRETTY_PRINT ) . "\n";

echo "== state\n";
foreach ( [ 'rp4wp_do_install', 'rp4wp_is_installing' ] as $rp4wp_option ) {
	echo $rp4wp_option . ': ' . wp_json_encode( get_option( $rp4wp_option ) ) . "\n";
}
echo 'cached words: ' . $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}rp4wp_cache" ) . "\n"; // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test snapshot.
$rp4wp_links = get_posts(
	[
		'post_type'      => 'rp4wp_link',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	]
);
echo 'links: ' . count( $rp4wp_links ) . "\n";

foreach ( $rp4wp_posts as $rp4wp_post ) {
	// The single post page of this post, which the widget needs.
	$wp_query     = new WP_Query( [ 'p' => $rp4wp_post->ID ] );
	$wp_the_query = $wp_query;
	$post         = $rp4wp_post;
	setup_postdata( $post );

	echo "== {$post->post_name}\n";
	echo "-- rp4wp_children()\n" . rp4wp_children( $post->ID, false ) . "\n";
	// No offset: 2.2 ignores it (offset arrived in 2.3.0), and the golden master covers it.
	echo "-- [rp4wp limit=2]\n" . do_shortcode( "[rp4wp id={$post->ID} limit=2]" ) . "\n";

	echo "-- widget\n";
	the_widget( 'RP4WP_Related_Posts_Widget' );
	echo "\n";
}

wp_reset_postdata();
