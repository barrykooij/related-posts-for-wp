<?php
/**
 * The update merge test file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Unit\Install;

use LV2\WordPress\RelatedPostsForWP\Install\Update;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * How requests for the update job merge (decision D72): the widest scope wins, the versions add up, a list of posts only
 * counts for a relink, and the notice shows when any request wants it.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Install\Update::merge
 */
final class UpdateMergeTest extends TestCase {

	public function test_a_first_request_starts_the_state(): void {
		$this->assertSame(
			[
				'pending'  => true,
				'scope'    => 'relink',
				'versions' => [ '3.1.0' ],
				'post_ids' => [ 7, 9 ],
				'notice'   => false,
			],
			Update::merge( [], 'relink', '3.1.0', [ 7, 9, 7 ], false )
		);
	}

	public function test_a_state_that_is_not_pending_starts_over(): void {
		$state = Update::merge(
			[
				'pending'  => false,
				'versions' => [ '3.0.0' ],
			],
			'weigh',
			'3.1.0',
			null,
			true
		);

		$this->assertSame( 'weigh', $state['scope'] );
		$this->assertSame( [ '3.1.0' ], $state['versions'] );
	}

	public function test_the_widest_scope_wins(): void {
		$state = Update::merge( [], 'weigh', '3.1.0', null, true );

		$this->assertSame( 'read', Update::merge( $state, 'read', '3.1.0', null, true )['scope'] );
		$this->assertSame( 'weigh', Update::merge( $state, 'relink', '3.1.0', [ 3 ], true )['scope'] );
	}

	public function test_lists_of_posts_join_and_every_post_wins_over_a_list(): void {
		$state = Update::merge( [], 'relink', '3.1.0', [ 1, 2 ], false );

		$this->assertSame( [ 1, 2, 3 ], Update::merge( $state, 'relink', '3.1.0', [ 2, 3 ], false )['post_ids'] );
		$this->assertNull( Update::merge( $state, 'relink', '3.1.0', null, false )['post_ids'] );
		$this->assertNull( Update::merge( $state, 'weigh', '3.1.0', [ 4 ], false )['post_ids'], 'A wider scope covers every post.' );
	}

	public function test_the_versions_add_up_and_a_state_of_3_0_is_the_update_to_3_0(): void {
		$state = Update::merge( [ 'pending' => true ], 'weigh', '3.1.0', null, false );

		$this->assertSame( 'read', $state['scope'], 'The update of 3.0 reads every post again.' );
		$this->assertSame( [ '3.0.0', '3.1.0' ], $state['versions'] );
		$this->assertTrue( $state['notice'], 'The update of 3.0 shows its notice.' );
	}

	public function test_the_notice_shows_when_any_request_wants_it(): void {
		$silent = Update::merge( [], 'relink', '3.1.0', [ 1 ], false );

		$this->assertFalse( Update::merge( $silent, 'relink', '3.1.0', [ 2 ], false )['notice'] );
		$this->assertTrue( Update::merge( $silent, 'relink', '3.1.0', [ 2 ], true )['notice'] );
	}

	public function test_an_unknown_scope_reads_every_post_again(): void {
		$this->assertSame( 'read', Update::merge( [], 'everything', '3.1.0', null, true )['scope'] );
	}
}
