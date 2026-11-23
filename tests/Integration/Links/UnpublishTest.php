<?php
/**
 * The unpublish test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Links;

use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Links\PostState;
use LV2\WordPress\RelatedPostsForWP\Related\Linker;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * A post that stops being published (known issue P20): the automatic links to it go and the posts that showed it get a
 * new related post; links added by hand to it stay, hidden and without taking a place, until it is published again.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Links\PostLifecycle::update_links
 * @covers \LV2\WordPress\RelatedPostsForWP\Links\LinkRepository
 * @covers \LV2\WordPress\RelatedPostsForWP\Related\Linker::relink_post
 */
final class UnpublishTest extends TestCase {

	/**
	 * The links.
	 *
	 * @var LinkRepository
	 */
	private LinkRepository $links;

	public function set_up(): void {
		parent::set_up();

		$this->links = new LinkRepository();
		update_option(
			'rp4wp',
			[
				'automatic_linking'             => 1,
				'automatic_linking_post_amount' => 2,
			]
		);
	}

	public function tear_down(): void {
		delete_option( 'rp4wp' );

		parent::tear_down();
	}

	public function test_the_posts_that_showed_it_get_a_new_related_post(): void {
		$related = $this->post( 'Pruning roses in early spring' );
		$other   = $this->post( 'Pruning roses after the first flowers' );
		$third   = $this->post( 'Pruning climbing roses on a wall' );
		$parent  = $this->post( 'Pruning roses: when and how' );
		( new Linker() )->relink_post( $parent, 2 );
		$before = array_values( $this->links->child_ids( $parent ) );
		$this->assertCount( 2, $before );

		$this->unpublish( $before[0] );

		$after = array_values( $this->links->child_ids( $parent ) );
		$this->assertNotContains( $before[0], $after );
		$this->assertCount( 2, $after, 'The post shows as many related posts as before.' );
		$this->assertContains( $before[1], $after );
		$this->assertContains( current( array_diff( [ $related, $other, $third ], $before ) ), $after );
	}

	public function test_a_link_added_by_hand_stays_hidden_takes_no_place_and_shows_again(): void {
		$parent = $this->post( 'Composting kitchen scraps' );
		$manual = $this->post( 'A compost bin for a small garden' );
		$this->post( 'Composting leaves in autumn' );
		$this->post( 'Composting with worms' );
		$this->links->add( $parent, $manual );
		( new Linker() )->relink_post( $parent, 2 );

		$this->unpublish( $manual );

		$this->assertContains( $manual, $this->links->child_ids( $parent ), 'The link added by hand stays.' );
		$shown = array_keys( $this->links->get_children( $parent, [ 'posts_per_page' => 2 ] ) );
		$this->assertNotContains( $manual, $shown );
		$this->assertCount( 2, $shown, 'The hidden link takes no place.' );

		wp_update_post(
			[
				'ID'          => $manual,
				'post_status' => 'publish',
			]
		);

		$this->assertSame( $manual, array_keys( $this->links->get_children( $parent, [ 'posts_per_page' => 2 ] ) )[0], 'It shows again in its place.' );
		$this->assertCount( 2, $this->links->child_ids( $parent ), 'The post keeps its amount: an automatic link made room.' );
	}

	public function test_the_links_of_the_post_itself_stay(): void {
		$this->post( 'Sourdough starter from scratch' );
		$this->post( 'Feeding a sourdough starter' );
		$post = $this->post( 'Baking with a sourdough starter' );
		$own  = $this->links->child_ids( $post );
		$this->assertNotSame( [], $own );

		$this->unpublish( $post );

		$this->assertSame( $own, $this->links->child_ids( $post ) );
		$this->assertFalse( PostState::is_linked( $post ), 'It is linked again, by difference, when it is published again.' );
	}

	public function test_a_private_related_post_shows_to_a_logged_in_user_only(): void {
		$parent  = $this->post( 'Growing tomatoes on a balcony' );
		$private = self::factory()->post->create(
			[
				'post_title'  => 'Tomato notes',
				'post_status' => 'private',
			]
		);
		$this->links->add( $parent, $private );

		$this->act_as( 'editor' );
		$this->assertArrayHasKey( $private, $this->links->get_children( $parent, [ 'posts_per_page' => 5 ] ) );

		wp_set_current_user( 0 );
		$this->assertArrayNotHasKey( $private, $this->links->get_children( $parent, [ 'posts_per_page' => 5 ] ) );
	}

	/**
	 * A published post with a title and the same text, so posts with similar titles are related.
	 *
	 * @param string $title The title.
	 *
	 * @return int
	 */
	private function post( string $title ): int {
		return self::factory()->post->create(
			[
				'post_title'   => $title,
				'post_content' => str_repeat( $title . '. ', 3 ),
				'post_status'  => 'publish',
			]
		);
	}

	/**
	 * Turn a post into a draft.
	 *
	 * @param int $post_id The post.
	 *
	 * @return void
	 */
	private function unpublish( int $post_id ): void {
		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'draft',
			]
		);
	}
}
