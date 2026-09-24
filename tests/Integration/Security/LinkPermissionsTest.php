<?php
/**
 * The link permissions test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Security;

use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * Regression tests for the 2.3.1 security fixes in link creation.
 *
 * @covers \RP4WP_Post_Link_Manager::add
 * @covers \RP4WP_Hook_Link_Related_Screen
 */
final class LinkPermissionsTest extends TestCase {

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

		$admin            = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->admin_post = self::factory()->post->create( [ 'post_author' => $admin ] );
		$this->children   = self::factory()->post->create_many( 2 );

		$_GET  = [];
		$_POST = [];
	}

	public function tear_down(): void {
		$_GET  = [];
		$_POST = [];

		parent::tear_down();
	}

	public function test_add_does_not_run_sql_from_the_child_id(): void {
		global $wpdb;

		$payload = $this->children[0] . "'), (1, 'rp4wp_injected', 'pwned";
		$link_id = ( new \RP4WP_Post_Link_Manager() )->add( $this->admin_post, $payload ); // @phpstan-ignore argument.type (The attack passes a string on purpose.)

		$injected = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'rp4wp_injected'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verifying the raw table.

		$this->assertSame( 0, $injected );
		$this->assertSame( (string) $this->children[0], get_post_meta( $link_id, 'rp4wp_child', true ) );
		$this->assertSame( (string) $this->admin_post, get_post_meta( $link_id, 'rp4wp_parent', true ) );
	}

	public function test_add_does_not_run_sql_from_the_parent_id(): void {
		global $wpdb;

		$payload = $this->admin_post . "'), (1, 'rp4wp_injected', 'pwned";
		( new \RP4WP_Post_Link_Manager() )->add( $payload, $this->children[0] ); // @phpstan-ignore argument.type (The attack passes a string on purpose.)

		$injected = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'rp4wp_injected'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verifying the raw table.

		$this->assertSame( 0, $injected );
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

		$children = array_map(
			static function ( $link_id ) {
				return (int) get_post_meta( $link_id, 'rp4wp_child', true );
			},
			$this->get_link_ids( $own_post )
		);

		$this->assertSame( $this->children, $children );
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
	 * Call one of the private request handlers on the link screen hook.
	 *
	 * @param string $method The method name.
	 *
	 * @return void
	 */
	private function invoke_screen_method( string $method ): void {
		$screen  = \RP4WP_Manager_Hook::get_hook_object( 'RP4WP_Hook_Link_Related_Screen' );
		$handler = new \ReflectionMethod( $screen, $method );
		$handler->setAccessible( true );
		$handler->invoke( $screen );
	}
}
