<?php
/**
 * The golden master test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\GoldenMaster;

use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Frontend\Css;
use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Related\Finder;
use LV2\WordPress\RelatedPostsForWP\Related\Linker;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\Contract\LinkManagerApi;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;
use LV2\WordPress\RelatedPostsForWP\Tests\Support\Golden;
use LV2\WordPress\RelatedPostsForWP\Tests\Support\HookRecorder;
use LV2\WordPress\RelatedPostsForWP\Tests\Support\Normalizer;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;

/**
 * Golden master for the free plugin: stored data and rendered output must stay identical to 2.x.
 *
 * The site is built the way a user builds it: content exists first, then the installation wizard caches words and
 * links posts. The tests then snapshot the word cache, the links, the related post scores and the HTML of every
 * front-end output, and run the post lifecycle and manual linking on top.
 *
 * @group golden
 * @coversNothing
 */
final class GoldenMasterTest extends TestCase {

	/**
	 * The golden set these tests compare against.
	 */
	private const SET = 'free-2.x';

	/**
	 * How many related posts the wizard links per post (its default).
	 */
	private const WIZARD_AMOUNT = 3;

	/**
	 * Posts whose output is rendered in the snapshots, one or two per topic.
	 */
	private const RENDERED = [ 'fresh-pasta-dough', 'espresso-at-home', 'hiking-the-dolomites', 'wordpress-hooks-explained', 'growing-tomatoes', 'olive-trees-in-pots' ];

	/**
	 * Post IDs by slug.
	 *
	 * @var array<string, int>
	 */
	private static array $ids = [];

	/**
	 * The author of the corpus.
	 *
	 * @var int
	 */
	private static int $author = 0;

	/**
	 * Build the corpus and run the wizard once for the whole class.
	 *
	 * @param \WP_UnitTest_Factory $factory The factory.
	 *
	 * @return void
	 */
	public static function wpSetUpBeforeClass( \WP_UnitTest_Factory $factory ): void {
		self::$author = $factory->user->create(
			[
				'role'         => 'administrator',
				'user_login'   => 'golden',
				'display_name' => 'Golden Author',
			]
		);

		// Content first, plugin install after: keep the publish hooks away while the corpus is created.
		self::toggle_lifecycle_hooks( false );
		self::$ids = Corpus::create( $factory, self::$author );
		self::toggle_lifecycle_hooks( true );

		self::run_wizard();
	}

	public function set_up(): void {
		parent::set_up();

		$this->set_permalink_structure( '/%postname%/' );
		wp_set_current_user( self::$author );
	}

	public function tear_down(): void {
		$this->remove_added_uploads();

		parent::tear_down();
	}

	public function test_word_cache_matches_golden(): void {
		Golden::assert_json_matches( self::SET, 'word-cache.json', $this->word_cache() );
	}

	public function test_links_match_golden(): void {
		Golden::assert_json_matches( self::SET, 'links.json', $this->links() );
	}

	public function test_related_scores_match_golden(): void {
		$scores = $this->related_scores();

		Golden::assert_json_matches( self::SET, 'related-scores.json', $scores );

		// Ties at the cut-off would make the linked posts depend on database row order, so keep the corpus free of them.
		foreach ( $scores as $slug => $related ) {
			if ( count( $related ) > self::WIZARD_AMOUNT ) {
				$this->assertNotSame(
					$related[ self::WIZARD_AMOUNT - 1 ]['score'],
					$related[ self::WIZARD_AMOUNT ]['score'],
					"The corpus has a tie at the cut-off for {$slug}; adjust its content."
				);
			}
		}
	}

	public function test_default_output_matches_golden(): void {
		Golden::assert_matches( self::SET, 'render-default.html', $this->render_default() );
	}

	public function test_output_with_images_matches_golden(): void {
		Golden::assert_matches( self::SET, 'render-images.html', $this->render_images() );
	}

	public function test_output_with_other_settings_matches_golden(): void {
		Golden::assert_matches( self::SET, 'render-settings.html', $this->render_settings() );
	}

	public function test_output_with_filters_matches_golden(): void {
		Golden::assert_matches( self::SET, 'render-filters.html', $this->render_filters() );
	}

	public function test_post_lifecycle_matches_golden(): void {
		Golden::assert_json_matches( self::SET, 'lifecycle.json', $this->post_lifecycle() );
	}

	public function test_manual_linking_matches_golden(): void {
		Golden::assert_json_matches( self::SET, 'manual-links.json', $this->manual_linking() );
	}

	public function test_link_manager_api_matches_golden(): void {
		Golden::assert_json_matches( self::SET, 'link-manager-api.json', $this->link_manager_api() );
	}

	/**
	 * Every scenario again with a recorder attached: the same hooks must fire with the same argument counts.
	 */
	public function test_hooks_fired_match_golden(): void {
		$recorder = new HookRecorder();
		$recorder->start();

		$this->render_default();
		$this->render_images();
		$this->render_settings();
		$this->render_filters();
		$this->post_lifecycle();
		$this->manual_linking();
		$this->link_manager_api();

		$recorder->stop();

		Golden::assert_json_matches( self::SET, 'hooks-fired.json', $recorder->hooks() );
	}

	/**
	 * The word cache with post IDs replaced by slugs.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function word_cache(): array {
		global $wpdb;

		$rows = $wpdb->get_results( 'SELECT post_id, word, weight, post_type FROM ' . Table::name() . ' ORDER BY post_id, word', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Reading our own table.

		$cache = [];
		foreach ( $rows as $row ) {
			$cache[ $this->normalizer()->name( (int) $row['post_id'] ) ][ $row['word'] ] = number_format( (float) $row['weight'], 6, '.', '' ) . ' ' . $row['post_type'];
		}

		return $cache;
	}

	/**
	 * The links of every post: children in order, plus the link post fields that 2.x writes.
	 *
	 * @return array<string, mixed>
	 */
	private function links(): array {
		$links = [];

		foreach ( self::$ids as $slug => $post_id ) {
			$children = [];
			foreach ( $this->get_link_ids( $post_id ) as $link_id ) {
				$link       = get_post( $link_id );
				$children[] = [
					'child'      => $this->normalizer()->name( (int) get_post_meta( $link_id, 'rp4wp_child', true ) ),
					'menu_order' => $link->menu_order,
					'title'      => $link->post_title,
					'status'     => $link->post_status,
					'meta'       => array_keys( get_post_meta( $link_id ) ),
				];
			}

			$links[ $slug ] = [
				'auto_linked' => get_post_meta( $post_id, 'rp4wp_auto_linked', true ),
				'children'    => $children,
			];
		}

		return $links;
	}

	/**
	 * The top related posts per post with their scores.
	 *
	 * @return array<string, array<int, array{post: string, score: string}>>
	 */
	private function related_scores(): array {
		$finder = new Finder();
		$scores = [];

		foreach ( self::$ids as $slug => $post_id ) {
			foreach ( $finder->related_posts( $post_id, self::WIZARD_AMOUNT + 2 ) as $related ) {
				$scores[ $slug ][] = [
					'post'  => $this->normalizer()->name( (int) $related->ID ),
					'score' => number_format( (float) $related->CMS, 8, '.', '' ), // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Column name from the 2.x query.
				];
			}
		}

		return $scores;
	}

	/**
	 * Every front-end output of the rendered posts with the default settings.
	 *
	 * @return string
	 */
	private function render_default(): string {
		$other = self::$ids['growing-tomatoes'];
		$html  = '';

		foreach ( self::RENDERED as $slug ) {
			$html .= $this->render_post(
				$slug,
				[
					'the_content'                      => [ $this, 'appended_content' ],
					'[rp4wp]'                          => $this->shortcode( '[rp4wp]' ),
					'[rp4wp limit=1]'                  => $this->shortcode( '[rp4wp limit=1]' ),
					'[rp4wp limit=2 offset=1]'         => $this->shortcode( '[rp4wp limit=2 offset=1]' ),
					'[rp4wp id=growing-tomatoes]'      => $this->shortcode( "[rp4wp id={$other}]" ),
					'rp4wp_children()'                 => static function () {
						return rp4wp_children( get_the_ID(), false );
					},
					'widget'                           => [ $this, 'widget' ],
					'wp_head css'                      => [ $this, 'frontend_css' ],
				]
			);
		}

		return $html;
	}

	/**
	 * Output with thumbnails enabled.
	 *
	 * @return string
	 */
	private function render_images(): string {
		$this->with_options( [ 'display_image' => 1 ] );

		foreach ( Corpus::WITH_IMAGE as $slug ) {
			$image = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', self::$ids[ $slug ] );
			set_post_thumbnail( self::$ids[ $slug ], $image );
		}

		$html = '';
		foreach ( [ 'tomato-sauce-basics', 'fresh-pasta-dough', 'rome-in-three-days' ] as $slug ) {
			$html .= $this->render_post( $slug, [ 'the_content' => [ $this, 'appended_content' ] ] );
		}

		$this->reset_options();

		return $html;
	}

	/**
	 * Output with the other display settings changed.
	 *
	 * @return string
	 */
	private function render_settings(): string {
		$scenarios = [
			'empty heading, no excerpt' => [
				'heading_text'   => '',
				'excerpt_length' => '0',
			],
			'long excerpt, show love'   => [
				'heading_text'   => 'You might also like',
				'excerpt_length' => '40',
				'show_love'      => '1',
			],
			'custom css'                => [ 'css' => '.rp4wp-related-posts ul { margin: 0; } <script>alert(1)</script>' ],
		];

		$html = '';
		foreach ( $scenarios as $name => $options ) {
			$this->with_options( $options );
			$html .= "<!-- settings: {$name} -->\n";
			$html .= $this->render_post(
				'espresso-at-home',
				[
					'the_content' => [ $this, 'appended_content' ],
					'wp_head css' => [ $this, 'frontend_css' ],
				]
			);
			$this->reset_options();
		}

		return $html;
	}

	/**
	 * Output with the public filters in use.
	 *
	 * @return string
	 */
	private function render_filters(): string {
		$filters = [
			'rp4wp_heading'                => static function () {
				return '<h2>Filtered heading</h2>';
			},
			'rp4wp_post_title'             => static function ( $title ) {
				return strtoupper( $title );
			},
			'rp4wp_post_link'              => static function ( $link, $post_id ) {
				return add_query_arg( 'ref', 'rp4wp-' . get_post_field( 'post_name', $post_id ), $link );
			},
			'rp4wp_post_excerpt'           => static function ( $excerpt ) {
				return '[' . $excerpt . ']';
			},
			'rp4wp_post_title_html'        => static function () {
				return "<a class='filtered' href='%s'>%s</a>";
			},
			'rp4wp_post_title_html_values' => static function ( $html ) {
				return '<span>' . $html . '</span>';
			},
			'rp4wp_poweredby_affiliate_id' => static function () {
				return '42';
			},
		];

		foreach ( $filters as $hook => $callback ) {
			add_filter( $hook, $callback, 10, 2 );
		}

		$this->with_options( [ 'show_love' => '1' ] );
		$html = $this->render_post( 'hiking-the-dolomites', [ 'the_content' => [ $this, 'appended_content' ] ] );

		add_filter( 'rp4wp_append_content', '__return_false' );
		$html .= "<!-- rp4wp_append_content false -->\n";
		$html .= $this->render_post( 'hiking-the-dolomites', [ 'the_content' => [ $this, 'appended_content' ] ] );

		remove_filter( 'rp4wp_append_content', '__return_false' );
		foreach ( $filters as $hook => $callback ) {
			remove_filter( $hook, $callback, 10 );
		}
		$this->reset_options();

		return $html;
	}

	/**
	 * Publish, update, unpublish and delete posts, and record what happens to words and links.
	 *
	 * @return array<string, mixed>
	 */
	private function post_lifecycle(): array {
		$result = [];

		// Publishing a new post caches its words and links it automatically.
		$new_id = self::factory()->post->create(
			[
				'post_name'     => 'lifecycle-new-post',
				'post_title'    => 'Italian espresso and pasta in Rome',
				'post_content'  => 'After a long walk through Rome, an espresso and a plate of fresh pasta taste even better.',
				'post_excerpt'  => '',
				'post_status'   => 'publish',
				'post_author'   => self::$author,
				'post_date'     => '2024-03-01 09:00:00',
				'post_category' => [ (int) get_cat_ID( 'Travel' ) ],
				'tags_input'    => [ 'italy', 'coffee', 'pasta' ],
			]
		);
		$names  = array_merge( self::$ids, [ 'lifecycle-new-post' => $new_id ] );

		$result['publish']['words']    = $this->words_of( $new_id );
		$result['publish']['children'] = $this->children_of( $new_id, $names );

		// Moving an auto-linked post away from publish removes the links to it and relinks the posts that pointed to it.
		$parents_before = $this->parents_of( self::$ids['tomato-sauce-basics'], $names );
		wp_update_post(
			[
				'ID'          => self::$ids['tomato-sauce-basics'],
				'post_status' => 'draft',
			]
		);
		$result['unpublish']['parents_before'] = $parents_before;
		$result['unpublish']['parents_after']  = $this->parents_of( self::$ids['tomato-sauce-basics'], $names );
		foreach ( $parents_before as $parent ) {
			$result['unpublish']['children_of'][ $parent ] = $this->children_of( $names[ $parent ], $names );
		}

		// Deleting a post removes its words.
		wp_delete_post( self::$ids['cold-brew-coffee'], true );
		$result['delete']['words'] = $this->words_of( self::$ids['cold-brew-coffee'] );

		return $result;
	}

	/**
	 * Add, reorder and delete links by hand, the way the meta box and link screen do.
	 *
	 * @return array<string, mixed>
	 */
	private function manual_linking(): array {
		$links  = new LinkRepository();
		$parent = self::$ids['pruning-roses'];
		$result = [ 'before' => $this->children_of( $parent, self::$ids ) ];

		$link = $links->add( $parent, self::$ids['kyoto-temples'] );
		$result['after_add'] = $this->children_of( $parent, self::$ids );

		// Reverse the order, like the sort handler does.
		global $wpdb;
		foreach ( array_reverse( $this->get_link_ids( $parent ) ) as $order => $link_id ) {
			$wpdb->update( $wpdb->posts, [ 'menu_order' => $order ], [ 'ID' => $link_id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Same write as the sort handler.
			clean_post_cache( $link_id );
		}
		$result['after_reverse'] = $this->children_of( $parent, self::$ids );

		$links->delete( $link );
		$result['after_delete'] = $this->children_of( $parent, self::$ids );

		$result['parents_of_kyoto_temples'] = $this->parents_of( self::$ids['kyoto-temples'], self::$ids );

		return $result;
	}

	/**
	 * Call the link manager directly, the way themes and other plugins use it.
	 *
	 * @return array<string, mixed>
	 */
	private function link_manager_api(): array {
		return LinkManagerApi::snapshot( self::$ids['espresso-at-home'], $this->normalizer() );
	}

	/**
	 * Render a set of outputs in the context of a single post page.
	 *
	 * @param string                  $slug    The post slug.
	 * @param array<string, callable> $outputs Output name => callable returning HTML.
	 *
	 * @return string
	 */
	private function render_post( string $slug, array $outputs ): string {
		$this->go_to( get_permalink( self::$ids[ $slug ] ) );

		$html = '';
		while ( have_posts() ) {
			the_post();

			foreach ( $outputs as $name => $callback ) {
				$html .= "<!-- post: {$slug} / {$name} -->\n" . $this->normalizer()->html( (string) call_user_func( $callback ) ) . "\n";
			}
		}

		wp_reset_postdata();

		return $html;
	}

	/**
	 * The part the plugin appends to the post content. Core content filters differ between WordPress versions.
	 *
	 * @return string
	 */
	public function appended_content(): string {
		$content = (string) apply_filters( 'the_content', get_the_content() );
		$start   = strpos( $content, "<div class='rp4wp-related-posts'>" );

		return false === $start ? '' : substr( $content, $start );
	}

	/**
	 * The related posts widget.
	 *
	 * @return string
	 */
	public function widget(): string {
		ob_start();
		the_widget(
			'RP4WP_Related_Posts_Widget',
			[],
			[
				'before_widget' => '<section class="widget">',
				'after_widget'  => '</section>',
			]
		);

		return (string) ob_get_clean();
	}

	/**
	 * The CSS the plugin prints in wp_head.
	 *
	 * @return string
	 */
	public function frontend_css(): string {
		ob_start();
		Css::print_css();

		return (string) ob_get_clean();
	}

	/**
	 * A shortcode renderer for render_post().
	 *
	 * @param string $shortcode The shortcode.
	 *
	 * @return callable
	 */
	private function shortcode( string $shortcode ): callable {
		return static function () use ( $shortcode ) {
			return do_shortcode( $shortcode );
		};
	}

	/**
	 * The cached words of a post.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array<string, string>
	 */
	private function words_of( int $post_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT word, weight FROM ' . Table::name() . ' WHERE post_id = %d ORDER BY word', $post_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Reading our own table; the table name cannot be a placeholder.

		return array_column(
			array_map(
				static function ( $row ) {
					return [ $row['word'], number_format( (float) $row['weight'], 6, '.', '' ) ];
				},
				$rows
			),
			1,
			0
		);
	}

	/**
	 * The children of a post as names, in order.
	 *
	 * @param int                $post_id The post ID.
	 * @param array<string, int> $names   Post IDs by name.
	 *
	 * @return string[]
	 */
	private function children_of( int $post_id, array $names ): array {
		$normalizer = ( new Normalizer() )->with_ids( $names );

		return array_map(
			static function ( $link_id ) use ( $normalizer ) {
				return $normalizer->name( (int) get_post_meta( $link_id, 'rp4wp_child', true ) );
			},
			$this->get_link_ids( $post_id )
		);
	}

	/**
	 * The posts that link to a post, as names.
	 *
	 * @param int                $post_id The post ID.
	 * @param array<string, int> $names   Post IDs by name.
	 *
	 * @return string[]
	 */
	private function parents_of( int $post_id, array $names ): array {
		$normalizer = ( new Normalizer() )->with_ids( $names );
		$parents    = array_map(
			static function ( $parent ) use ( $normalizer ) {
				return $normalizer->name( (int) $parent->ID );
			},
			array_values( ( new LinkRepository() )->get_parents( $post_id ) )
		);

		sort( $parents );

		return $parents;
	}

	/**
	 * Change plugin options for one scenario.
	 *
	 * @param array<string, mixed> $options Option values.
	 *
	 * @return void
	 */
	private function with_options( array $options ): void {
		update_option( 'rp4wp', array_merge( Main::get()->settings()->get_options(), $options ) );
	}

	/**
	 * Remove option changes made by a scenario.
	 *
	 * @return void
	 */
	private function reset_options(): void {
		delete_option( 'rp4wp' );
	}

	/**
	 * The normalizer for the corpus.
	 *
	 * @return Normalizer
	 */
	private function normalizer(): Normalizer {
		return ( new Normalizer() )->with_ids( self::$ids );
	}

	/**
	 * Detach or re-attach the hooks that cache words and link posts when a post is published.
	 *
	 * @param bool $attach Whether to attach.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException When one of the hooks is not registered.
	 */
	private static function toggle_lifecycle_hooks( bool $attach ): void {
		foreach ( [ 'RP4WP_Hook_Related_Save_Words', 'RP4WP_Hook_Related_Auto_Link', 'RP4WP_Hook_Related_Update_Link' ] as $class ) {
			$hook = LegacyHooks::get( $class );
			if ( null === $hook ) {
				throw new \RuntimeException( "The {$class} hook is not registered." );
			}

			if ( $attach ) {
				add_action( $hook->get_tag(), [ $hook, 'run' ], $hook->get_priority(), $hook->get_args() );
			} else {
				remove_action( $hook->get_tag(), [ $hook, 'run' ], $hook->get_priority() );
			}
		}
	}

	/**
	 * Do what the installation wizard does: cache the words of every post, then link every post.
	 *
	 * @return void
	 */
	private static function run_wizard(): void {
		( new Cache() )->save_all();
		( new Linker() )->link_all( self::WIZARD_AMOUNT );
	}
}
