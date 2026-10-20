<?php
/**
 * The link permissions test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Security;

use LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen\Page;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * Regression tests for the 2.3.1 security fixes in link creation, and for the 3.0.1 fix that only links posts the user
 * can read. The fixes in the 2.x link manager are covered by Deprecated\PostLinkManagerTest.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen\Page
 */
final class LinkPermissionsTest extends TestCase {

	/**
	 * An administrator.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * A post owned by an administrator.
	 *
	 * @var int
	 */
	private int $admin_post;

	/**
	 * Candidate children.
	 *
	 * @var int[]
	 */
	private array $children;

	public function set_up(): void {
		parent::set_up();

		$this->admin      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->admin_post = self::factory()->post->create( [ 'post_author' => $this->admin ] );
		$this->children   = self::factory()->post->create_many( 2 );

		$_GET  = [];
		$_POST = [];
	}

	public function tear_down(): void {
		$_GET  = [];
		$_POST = [];

		parent::tear_down();
	}

	public function test_contributor_cannot_bulk_link_to_someone_elses_post(): void {
		$this->act_as( 'contributor' );
		$this->prepare_bulk_request( $this->admin_post, $this->children );

		try {
			$this->invoke_screen_method( 'handle_bulk_link' );
			$this->fail( 'Expected wp_die() for a post the user cannot edit.' );
		} catch ( \WPDieException $e ) {
			$this->assertStringContainsString( 'necessary permissions', $e->getMessage() );
		}

		$this->assertSame( [], $this->get_link_ids( $this->admin_post ) );
	}

	public function test_contributor_can_bulk_link_to_own_post_and_junk_is_skipped(): void {
		$contributor = $this->act_as( 'contributor' );
		$own_post    = self::factory()->post->create(
			[
				'post_author' => $contributor,
				'post_status' => 'draft',
			]
		);

		$this->prepare_bulk_request( $own_post, [ $this->children[0], 'abc', $this->children[1] . ' OR 1=1' ] );
		$this->stop_at_redirect();

		try {
			$this->invoke_screen_method( 'handle_bulk_link' );
			$this->fail( 'Expected a redirect after bulk linking.' );
		} catch ( RedirectException $e ) {
			$this->assertStringContainsString( "post={$own_post}&action=edit", $e->getMessage() );
		}

		$this->assertSame( $this->children, array_values( ( new LinkRepository() )->child_ids( $own_post ) ) );
	}

	public function test_contributor_cannot_create_a_single_link_on_someone_elses_post(): void {
		$this->act_as( 'contributor' );

		$_GET = [
			'rp4wp_parent'      => $this->admin_post,
			'rp4wp_create_link' => $this->children[0],
			'rp4wp_nonce'       => wp_create_nonce( 'rp4wp_link_nonce' ),
		];

		$this->expectException( \WPDieException::class );
		$this->invoke_screen_method( 'handle_create_link' );
	}

	public function test_admin_can_create_a_single_link(): void {
		$this->act_as( 'administrator' );

		$_GET = [
			'rp4wp_parent'      => $this->admin_post,
			'rp4wp_create_link' => $this->children[0],
			'rp4wp_nonce'       => wp_create_nonce( 'rp4wp_link_nonce' ),
		];
		$this->stop_at_redirect();

		try {
			$this->invoke_screen_method( 'handle_create_link' );
		} catch ( RedirectException $e ) {
			// Expected: the handler redirects back to the post after linking.
			unset( $e );
		}

		$this->assertCount( 1, $this->get_link_ids( $this->admin_post ) );
	}

	/**
	 * The statuses of posts of someone else that a contributor can't read.
	 *
	 * @return array<string, array{string}>
	 */
	public static function unreadable_statuses(): array {
		return [
			'draft'     => [ 'draft' ],
			'pending'   => [ 'pending' ],
			'private'   => [ 'private' ],
			'scheduled' => [ 'future' ],
			'trash'     => [ 'trash' ],
		];
	}

	/**
	 * @dataProvider unreadable_statuses
	 *
	 * @param string $status The status of the post to link.
	 */
	public function test_contributor_cannot_link_a_post_of_someone_else_they_cannot_read( string $status ): void {
		$own_post = $this->own_draft( $this->act_as( 'contributor' ) );

		$_GET = [
			'rp4wp_parent'      => $own_post,
			'rp4wp_create_link' => $this->admin_post_with_status( $status ),
			'rp4wp_nonce'       => wp_create_nonce( 'rp4wp_link_nonce' ),
		];

		try {
			$this->invoke_screen_method( 'handle_create_link' );
			$this->fail( 'Expected wp_die() for a post the user cannot read.' );
		} catch ( \WPDieException $e ) {
			$this->assertStringContainsString( 'necessary permissions', $e->getMessage() );
		}

		$this->assertSame( [], $this->get_link_ids( $own_post ) );
	}

	public function test_linking_a_post_that_does_not_exist_is_refused(): void {
		$this->act_as( 'administrator' );

		$_GET = [
			'rp4wp_parent'      => $this->admin_post,
			'rp4wp_create_link' => PHP_INT_MAX,
			'rp4wp_nonce'       => wp_create_nonce( 'rp4wp_link_nonce' ),
		];

		$this->expectException( \WPDieException::class );
		$this->invoke_screen_method( 'handle_create_link' );
	}

	public function test_contributor_bulk_linking_skips_posts_they_cannot_read(): void {
		$own_post   = $this->own_draft( $this->act_as( 'contributor' ) );
		$unreadable = array_map( [ $this, 'admin_post_with_status' ], array_column( self::unreadable_statuses(), 0 ) );

		$this->prepare_bulk_request( $own_post, array_merge( $unreadable, [ $this->children[0] ] ) );
		$this->stop_at_redirect();

		try {
			$this->invoke_screen_method( 'handle_bulk_link' );
			$this->fail( 'Expected a redirect after bulk linking.' );
		} catch ( RedirectException $e ) {
			unset( $e );
		}

		$this->assertSame( [ $this->children[0] ], array_values( ( new LinkRepository() )->child_ids( $own_post ) ) );
	}

	public function test_editor_can_link_a_private_post_of_someone_else(): void {
		$this->act_as( 'editor' );
		$private = $this->admin_post_with_status( 'private' );

		$_GET = [
			'rp4wp_parent'      => $this->admin_post,
			'rp4wp_create_link' => $private,
			'rp4wp_nonce'       => wp_create_nonce( 'rp4wp_link_nonce' ),
		];
		$this->stop_at_redirect();

		try {
			$this->invoke_screen_method( 'handle_create_link' );
		} catch ( RedirectException $e ) {
			unset( $e );
		}

		$this->assertSame( [ $private ], array_values( ( new LinkRepository() )->child_ids( $this->admin_post ) ) );
	}

	/**
	 * A draft of a user.
	 *
	 * @param int $author The user.
	 *
	 * @return int
	 */
	private function own_draft( int $author ): int {
		return self::factory()->post->create(
			[
				'post_author' => $author,
				'post_status' => 'draft',
			]
		);
	}

	/**
	 * A post of the administrator, with a status.
	 *
	 * @param string $status The status.
	 *
	 * @return int
	 */
	private function admin_post_with_status( string $status ): int {
		return self::factory()->post->create(
			[
				'post_author' => $this->admin,
				'post_status' => $status,
				'post_title'  => "Secret {$status} post",
				'post_date'   => 'future' === $status ? gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) : '',
			]
		);
	}

	/**
	 * Fill the request the way the bulk link form posts it.
	 *
	 * @param int   $parent_id The parent post ID.
	 * @param array $children  The posted child values.
	 *
	 * @return void
	 */
	private function prepare_bulk_request( int $parent_id, array $children ): void {
		$_GET  = [ 'rp4wp_parent' => $parent_id ];
		$_POST = [
			'rp4wp_bulk' => $children,
			'_wpnonce'   => wp_create_nonce( 'bulk-admin_page_rp4wp_link_related' ),
		];
	}

	/**
	 * The handlers call exit() right after wp_redirect(), so turn the redirect into an exception.
	 *
	 * @return void
	 */
	private function stop_at_redirect(): void {
		add_filter( 'wp_redirect', [ self::class, 'throw_on_redirect' ] );
	}

	// phpcs:disable Squiz.Commenting.FunctionComment.InvalidNoReturn -- A filter callback must declare a return type, but this one always throws.
	/**
	 * The wp_redirect filter callback that stops the handler before its exit().
	 *
	 * @param string $location The redirect target.
	 *
	 * @return string Never returns: it always throws.
	 *
	 * @throws RedirectException Always.
	 */
	public static function throw_on_redirect( $location ): string {
		throw new RedirectException( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test-only exception.
	}
	// phpcs:enable

	/**
	 * Call one of the private request handlers of the link screen.
	 *
	 * @param string $method The method name.
	 *
	 * @return void
	 */
	private function invoke_screen_method( string $method ): void {
		$handler = new \ReflectionMethod( Page::class, $method );
		$handler->setAccessible( true );
		$handler->invoke( null );
	}
}
