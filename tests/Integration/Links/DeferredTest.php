<?php
/**
 * The deferred links test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Links;

use LV2\WordPress\RelatedPostsForWP\Database\Schema;
use LV2\WordPress\RelatedPostsForWP\Links\Deferred;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Links\PostLifecycle;
use LV2\WordPress\RelatedPostsForWP\Links\PostState;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * While a migration moves the links into the links table, nothing writes links: a post that is published then, or a
 * linked post that is unpublished then, waits and is handled once the move is done.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Links\Deferred
 * @covers \LV2\WordPress\RelatedPostsForWP\Links\PostLifecycle
 */
final class DeferredTest extends TestCase {

	public function test_a_post_published_while_the_links_move_is_linked_afterwards(): void {
		$older = $this->post( 'Climbing roses on a garden wall' );

		Schema::set_storage( Schema::STORAGE_POST_STATE );
		$newer = $this->post( 'Climbing roses for a sunny garden wall' );

		$this->assertTrue( Deferred::has() );
		$this->assertFalse( PostState::is_linked( $newer ) );

		Schema::set_storage( Schema::STORAGE_LINKS );
		PostLifecycle::run_deferred();

		$this->assertTrue( PostState::is_linked( $newer ) );
		$this->assertSame( [ $older ], array_values( ( new LinkRepository() )->child_ids( $newer ) ) );
		$this->assertFalse( Deferred::has() );
	}

	public function test_a_related_post_unpublished_while_the_links_move_is_unlinked_afterwards(): void {
		$older = $this->post( 'Pruning roses in early spring' );
		$newer = $this->post( 'Pruning roses before new growth in spring' );
		$links = new LinkRepository();

		$this->assertSame( [ $older ], array_values( $links->child_ids( $newer ) ) );

		Schema::set_storage( Schema::STORAGE_POST_STATE );
		wp_update_post(
			[
				'ID'          => $older,
				'post_status' => 'draft',
			]
		);

		Schema::set_storage( Schema::STORAGE_LINKS );
		$this->assertSame( [ $older ], array_values( $links->child_ids( $newer ) ), 'Nothing changed while the links moved.' );

		PostLifecycle::run_deferred();

		$this->assertSame( [], $links->child_ids( $newer ) );
		$this->assertFalse( PostState::is_linked( $older ) );
	}

	public function test_links_can_not_be_changed_while_they_move(): void {
		$parent  = $this->post( 'Composting kitchen scraps' );
		$links   = new LinkRepository();
		$link_id = $links->add( $parent, self::factory()->post->create() );

		Schema::set_storage( Schema::STORAGE_POST_STATE );

		$this->assertFalse( $links->can_write() );
		$this->assertSame( 0, $links->add( $parent, self::factory()->post->create() ) );

		$links->delete( $link_id );
		Schema::set_storage( Schema::STORAGE_LINKS );

		$this->assertNotNull( $links->find( $link_id ) );
	}

	public function test_the_storage_level_follows_from_the_migrations_when_its_option_is_gone(): void {
		delete_option( Schema::OPTION_STORAGE );

		$this->assertSame( Schema::STORAGE_LINKS, Schema::storage() );
		$this->assertSame( Schema::STORAGE_LINKS, (int) get_option( Schema::OPTION_STORAGE ) );
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
				'post_content' => $title . '.',
				'post_status'  => 'publish',
			]
		);
	}
}
