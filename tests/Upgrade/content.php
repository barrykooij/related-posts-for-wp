<?php
/**
 * Creates the posts of the upgrade test, before the plugin is active. Run with `wp eval-file`.
 *
 * @package RelatedPostsForWP
 */

$rp4wp_posts = [
	'Espresso at home'     => 'Pull a rich espresso shot at home with fresh coffee beans, a fine grind and a good espresso machine.',
	'Cold brew coffee'     => 'Cold brew coffee steeps coarse coffee grounds in cold water for many hours for a smooth coffee.',
	'Pour over coffee'     => 'Pour over coffee needs a gooseneck kettle, fresh coffee beans, a medium grind and patience with the water.',
	'Growing tomatoes'     => 'Tomatoes need sun, rich soil and regular water; stake the tomato plants and pinch the side shoots.',
	'Pruning roses'        => 'Prune roses in early spring, cut above an outward bud and remove dead wood to keep the roses healthy in the garden.',
	'Compost basics'       => 'Compost turns kitchen scraps and garden waste into rich soil; keep the compost moist and turn it often.',
	'Hiking the Dolomites' => 'The Dolomites offer hiking trails, mountain huts and via ferrata routes with views of jagged mountain peaks.',
	'Packing for a hike'   => 'Pack water, snacks, a rain jacket, a map and good hiking boots before you start a mountain hike.',
	'Trail running'        => 'Trail running on mountain trails builds strength; wear shoes with grip and carry water on long trail runs.',
];

foreach ( $rp4wp_posts as $rp4wp_title => $rp4wp_content ) {
	wp_insert_post(
		[
			'post_title'   => $rp4wp_title,
			'post_content' => $rp4wp_content,
			'post_status'  => 'publish',
		]
	);
}

echo count( $rp4wp_posts ) . " posts created.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
