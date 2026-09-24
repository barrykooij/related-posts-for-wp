<?php
/**
 * The golden master corpus class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\GoldenMaster;

/**
 * A fixed set of posts for the golden master tests.
 *
 * The content is deliberately clustered in four topics with a few overlaps (Italy links cooking and travel, coffee
 * links cooking and technology, and so on), so the related posts per post are clear and stable. Change it only
 * together with re-recording the golden files against the 2.x code.
 */
final class Corpus {

	/**
	 * Posts as [ slug, title, content, category, tags ].
	 *
	 * @var array<int, array{string, string, string, string, string[]}>
	 */
	private const POSTS = [
		[ 'sourdough-bread-at-home', 'Baking sourdough bread at home', 'A sourdough starter needs flour, water and patience. Feed the starter daily and bake the bread when it doubles. A hot oven and steam give the loaf a crisp crust.', 'Cooking', [ 'bread', 'baking' ] ],
		[ 'fresh-pasta-dough', 'Fresh pasta dough from scratch', 'Fresh pasta needs only flour and eggs. Knead the dough until smooth, rest it, then roll thin sheets for tagliatelle or ravioli. Italian cooks swear by semolina flour.', 'Cooking', [ 'pasta', 'italian' ] ],
		[ 'tomato-sauce-basics', 'Tomato sauce basics for pasta', 'A simple tomato sauce starts with olive oil, garlic and ripe tomatoes. Simmer slowly and season with basil. Serve the sauce with fresh pasta.', 'Cooking', [ 'pasta', 'tomatoes' ] ],
		[ 'espresso-at-home', 'Brewing espresso at home', 'Good espresso needs freshly ground coffee beans, the right grind and a hot machine. Pull the shot for about thirty seconds and taste the crema.', 'Cooking', [ 'coffee' ] ],
		[ 'pour-over-coffee', 'Pour over coffee for beginners', 'Pour over coffee is slow and calm. Rinse the paper filter, bloom the coffee grounds, then pour hot water in circles. Light roast beans shine here.', 'Cooking', [ 'coffee' ] ],
		[ 'focaccia-with-rosemary', 'Focaccia bread with rosemary', 'Focaccia is an Italian flat bread with plenty of olive oil. Dimple the dough with your fingers, add rosemary and sea salt, and bake until golden.', 'Cooking', [ 'bread', 'italian' ] ],
		[ 'risotto-technique', 'Risotto technique explained', 'Risotto rice releases starch when you stir it. Add warm stock slowly, keep stirring, and finish with butter and parmesan. A classic Italian comfort dish.', 'Cooking', [ 'italian', 'rice' ] ],
		[ 'banana-bread-recipe', 'Moist banana bread recipe', 'Overripe bananas make the best banana bread. Mash them with butter and sugar, fold in flour, and bake the loaf low and slow for a moist crumb.', 'Cooking', [ 'bread', 'baking' ] ],
		[ 'cold-brew-coffee', 'Cold brew coffee in the fridge', 'Cold brew coffee steeps coarse grounds in cold water overnight. Strain it in the morning for a smooth, low acid coffee concentrate.', 'Cooking', [ 'coffee' ] ],
		[ 'homemade-pizza-dough', 'Homemade pizza dough and oven tips', 'Pizza dough improves with a long cold rise. Stretch the dough by hand, top it lightly with tomato sauce and mozzarella, and bake on a very hot stone.', 'Cooking', [ 'italian', 'baking' ] ],
		[ 'hiking-the-dolomites', 'Hiking the Dolomites in summer', 'The Dolomites in northern Italy offer dramatic hiking trails. Stay in mountain huts, start early, and pack layers for sudden weather changes.', 'Travel', [ 'hiking', 'italy' ] ],
		[ 'rome-in-three-days', 'Rome in three days', 'Three days in Rome covers the Colosseum, the Vatican and endless pasta. Walk the old streets early and book museum tickets ahead.', 'Travel', [ 'italy', 'city' ] ],
		[ 'tokyo-coffee-shops', 'Tokyo coffee shops worth the trip', 'Tokyo has tiny coffee shops with careful pour over brewing. Many serve light roast beans and quiet corners to read.', 'Travel', [ 'japan', 'coffee' ] ],
		[ 'kyoto-temples', 'Kyoto temples and gardens', 'Kyoto is full of temples and quiet gardens. Visit early to avoid crowds and walk between the temples along the canals.', 'Travel', [ 'japan', 'gardens' ] ],
		[ 'packing-light', 'Packing light for long trips', 'Packing light means fewer clothes and more freedom. Choose layers, one pair of hiking shoes and a small backpack for any long trip.', 'Travel', [ 'packing' ] ],
		[ 'train-travel-europe', 'Train travel across Europe', 'Trains connect most of Europe. A rail pass makes it easy to hop from Amsterdam to Rome, and night trains save a hotel night.', 'Travel', [ 'trains', 'europe' ] ],
		[ 'amsterdam-by-bike', 'Amsterdam by bike', 'Amsterdam is best seen by bike. Rent a bike for the day, follow the canals, and stop for coffee along the way.', 'Travel', [ 'europe', 'city' ] ],
		[ 'hiking-gear-checklist', 'Hiking gear checklist', 'A good hiking checklist covers boots, rain jacket, water, snacks and a map. Test your hiking gear on short trails first.', 'Travel', [ 'hiking', 'packing' ] ],
		[ 'florence-food-tour', 'Florence food tour', 'Florence rewards hungry travellers. Try fresh pasta, focaccia and gelato on a food tour through the Italian markets.', 'Travel', [ 'italy', 'food' ] ],
		[ 'osaka-street-food', 'Osaka street food guide', 'Osaka is Japan\'s street food capital. Try takoyaki and okonomiyaki at the night markets and follow the locals.', 'Travel', [ 'japan', 'food' ] ],
		[ 'wordpress-plugin-basics', 'WordPress plugin development basics', 'A WordPress plugin starts with one PHP file and a header. Hooks let the plugin change WordPress without touching core files.', 'Technology', [ 'wordpress', 'php' ] ],
		[ 'php-8-features', 'New features in PHP 8', 'PHP 8 brought named arguments, union types and the match expression. Upgrading old PHP code is easier with static analysis.', 'Technology', [ 'php' ] ],
		[ 'mysql-index-tips', 'MySQL index tips', 'A MySQL index speeds up queries on large tables. Index the columns you filter on and check the query plan with explain.', 'Technology', [ 'mysql', 'databases' ] ],
		[ 'wordpress-hooks-explained', 'WordPress hooks explained', 'WordPress hooks come in two kinds: actions and filters. Plugins add callbacks to hooks to change output or run code.', 'Technology', [ 'wordpress', 'php' ] ],
		[ 'static-analysis-php', 'Static analysis for PHP projects', 'Static analysis tools read PHP code without running it. They catch type errors early and make upgrading PHP versions safer.', 'Technology', [ 'php', 'testing' ] ],
		[ 'database-backups', 'Database backups that work', 'A database backup is only useful if you can restore it. Automate MySQL dumps and test a restore every month.', 'Technology', [ 'mysql', 'databases' ] ],
		[ 'unit-testing-wordpress', 'Unit testing WordPress plugins', 'Unit testing a WordPress plugin gives confidence to refactor. Mock WordPress functions and test plugin logic in isolation.', 'Technology', [ 'wordpress', 'testing' ] ],
		[ 'coffee-and-code', 'Coffee and code: a developer morning', 'Many developers start with coffee before writing code. A calm morning routine with pour over coffee helps focus on hard PHP bugs.', 'Technology', [ 'coffee', 'php' ] ],
		[ 'git-branching', 'Git branching for small teams', 'Git branches keep work separate. Small teams do well with short lived branches and pull requests for every change.', 'Technology', [ 'git' ] ],
		[ 'caching-wordpress', 'Caching WordPress for speed', 'Caching makes WordPress fast. Page caching, object caching and a CDN reduce database load and speed up every request.', 'Technology', [ 'wordpress', 'performance' ] ],
		[ 'growing-tomatoes', 'Growing tomatoes in pots', 'Tomatoes grow well in large pots with full sun. Water deeply, feed weekly and pinch side shoots for bigger tomatoes.', 'Gardening', [ 'tomatoes', 'vegetables' ] ],
		[ 'pruning-roses', 'Pruning roses in early spring', 'Prune roses in early spring before new growth. Cut dead wood first, then shape the rose bush with clean angled cuts.', 'Gardening', [ 'roses', 'pruning' ] ],
		[ 'herb-garden-basil', 'A kitchen herb garden with basil', 'Basil, rosemary and thyme grow happily on a sunny windowsill. A small herb garden gives fresh basil for tomato sauce all summer.', 'Gardening', [ 'herbs', 'vegetables' ] ],
		[ 'composting-for-beginners', 'Composting for beginners', 'Compost turns kitchen scraps and garden waste into rich soil. Mix green and brown material, turn the compost pile often and keep the heap as damp as a wrung out sponge.', 'Gardening', [ 'soil', 'compost' ] ],
		[ 'japanese-garden-design', 'Japanese garden design ideas', 'Japanese garden design uses stone, water and moss. Borrow ideas from Kyoto gardens: simple paths, raked gravel and quiet corners.', 'Gardening', [ 'gardens', 'japan' ] ],
		[ 'raised-vegetable-beds', 'Building raised vegetable beds', 'Raised beds warm up early and drain well. Fill the vegetable beds with compost rich soil and plant tomatoes, lettuce and beans.', 'Gardening', [ 'vegetables', 'soil' ] ],
		[ 'climbing-roses', 'Training climbing roses', 'Climbing roses need a sturdy support. Tie the rose canes horizontally for more flowers and prune climbing roses after flowering.', 'Gardening', [ 'roses', 'pruning' ] ],
		[ 'olive-trees-in-pots', 'Olive trees in pots', 'An olive tree brings an Italian feel to a terrace. Grow olive trees in pots with gritty soil and bring them inside in hard frost.', 'Gardening', [ 'trees', 'italy' ] ],
		[ 'saving-tomato-seeds', 'Saving tomato seeds', 'Save seeds from your best tomatoes. Ferment the tomato seeds for a few days, rinse and dry them for next year.', 'Gardening', [ 'tomatoes', 'seeds' ] ],
		[ 'lawn-care-autumn', 'Lawn care in autumn', 'Autumn is the time to feed and aerate the lawn. Rake leaves, overseed thin patches and add compost to tired soil.', 'Gardening', [ 'lawn', 'soil' ] ],
	];

	/**
	 * Slugs of posts that get a featured image in the display_image scenario.
	 */
	public const WITH_IMAGE = [ 'fresh-pasta-dough', 'tomato-sauce-basics', 'florence-food-tour' ];

	/**
	 * Create the corpus.
	 *
	 * @param \WP_UnitTest_Factory $factory The factory from the test case.
	 * @param int                  $author  The author of every post.
	 *
	 * @return array<string, int> Post IDs by slug, in creation order.
	 */
	public static function create( \WP_UnitTest_Factory $factory, int $author ): array {
		$ids = [];

		foreach ( self::POSTS as $index => [ $slug, $title, $content, $category, $tags ] ) {
			$ids[ $slug ] = $factory->post->create(
				[
					'post_name'     => $slug,
					'post_title'    => $title,
					'post_content'  => $content,
					'post_excerpt'  => '', // The factory fills in a numbered excerpt otherwise, and the plugin prefers it.
					'post_status'   => 'publish',
					'post_author'   => $author,
					'post_date'     => gmdate( 'Y-m-d H:i:s', gmmktime( 9, 0, 0, 1, 1 + $index, 2024 ) ),
					'post_category' => [ self::category( $category ) ],
					'tags_input'    => $tags,
				]
			);
		}

		return $ids;
	}

	/**
	 * Get or create a category by name.
	 *
	 * @param string $name The category name.
	 *
	 * @return int The term ID.
	 */
	private static function category( string $name ): int {
		$term = term_exists( $name, 'category' );

		if ( ! $term ) {
			$term = wp_insert_term( $name, 'category' );
		}

		return (int) $term['term_id'];
	}
}
