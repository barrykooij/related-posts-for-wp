<?php
/**
 * The link screen seam test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Admin;

use LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen\ListTable;
use LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen\Page;
use LV2\WordPress\RelatedPostsForWP\Links\LinkPostType;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\Security\RedirectException;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * The premium add-on extends the link screen: its screen adds links and builds the table its own way, and its table
 * lists other posts, in other rows.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen\Page
 * @covers \LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen\ListTable
 */
final class LinkScreenSeamTest extends TestCase {

	/**
	 * The post links are added to.
	 *
	 * @var int
	 */
	private int $parent;

	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		$this->act_as( 'administrator' );
		$this->parent        = self::factory()->post->create();
		RecordingPage::$links = [];
		set_current_screen( 'admin_page_' . Page::SLUG );
		add_filter( 'wp_redirect', [ self::class, 'throw_on_redirect' ] );
	}

	public function tear_down(): void {
		$_GET     = [];
		$_REQUEST = [];
		remove_filter( 'wp_redirect', [ self::class, 'throw_on_redirect' ] );
		set_current_screen( 'front' );

		parent::tear_down();
	}

	public function test_a_subclass_of_the_screen_adds_the_links(): void {
		$_GET = [
			'rp4wp_parent'      => (string) $this->parent,
			'rp4wp_create_link' => '7',
			'rp4wp_nonce'       => wp_create_nonce( Page::NONCE_LINK ),
		];

		try {
			RecordingPage::register();
			$this->fail( 'Expected a redirect after linking.' );
		} catch ( RedirectException $e ) {
			$this->assertStringContainsString( "post={$this->parent}&action=edit", $e->getMessage() );
		}

		$this->assertSame( [ [ $this->parent, 7 ] ], RecordingPage::$links );
		$this->assertSame( [], get_posts( [ 'post_type' => LinkPostType::POST_TYPE ] ) );
	}

	public function test_a_subclass_of_the_screen_shows_its_own_table(): void {
		$_GET     = [
			'page'         => Page::SLUG,
			'rp4wp_parent' => (string) $this->parent,
		];
		$_REQUEST = $_GET;

		ob_start();
		RecordingPage::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Suggested by the subclass', $html );
	}

	public function test_a_subclass_of_the_table_lists_its_own_post_types_in_its_own_rows(): void {
		$page = self::factory()->post->create(
			[
				'post_type'  => 'page',
				'post_title' => 'A page',
			]
		);
		$_GET = [
			'page'         => Page::SLUG,
			'rp4wp_parent' => (string) $this->parent,
			'rp4wp_view'   => 'all',
		];

		$this->assertSame(
			[
				[
					'ID'        => $page,
					'title'     => 'A page',
					'post_type' => 'page',
				],
			],
			$this->items_of( new RecordingListTable() )
		);
	}

	public function test_a_subclass_of_the_table_suggests_its_own_posts(): void {
		$_GET  = [
			'page'         => Page::SLUG,
			'rp4wp_parent' => (string) $this->parent,
		];
		$table = new RecordingListTable();

		$this->assertSame( 'Suggested by the subclass', $this->items_of( $table )[0]['title'] );
		$this->assertSame( $this->parent, $table->parent );
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
	 * The rows of a table for the current request.
	 *
	 * @param ListTable $table The table.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function items_of( ListTable $table ): array {
		// Loading the items also prints the views.
		ob_start();
		$table->prepare_items();
		ob_end_clean();

		return $table->items;
	}
}

/**
 * A screen that records the links it adds, and shows a table of its own.
 */
class RecordingPage extends Page {

	/**
	 * The links it added: parent, child.
	 *
	 * @var array<int, array{int, int}>
	 */
	public static array $links = [];

	/**
	 * Record a link.
	 *
	 * @param int $parent The parent.
	 * @param int $child  The child.
	 *
	 * @return void
	 */
	protected static function add_link( int $parent, int $child ): void {
		self::$links[] = [ $parent, $child ];
	}

	/**
	 * A table of its own.
	 *
	 * @param int $parent The parent.
	 *
	 * @return ListTable
	 */
	protected static function list_table( int $parent ): ListTable { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- The signature of the parent.
		return new RecordingListTable();
	}
}

/**
 * A table that lists pages, suggests the parent itself and shows the post type in its rows.
 */
class RecordingListTable extends ListTable {

	/**
	 * The parent it was asked to suggest posts for.
	 *
	 * @var int
	 */
	public int $parent = 0;

	/**
	 * Pages.
	 *
	 * @return string[]
	 */
	protected function linkable_post_types(): array {
		return [ 'page' ];
	}

	/**
	 * A made-up suggestion.
	 *
	 * @param int $parent The parent.
	 *
	 * @return array<int, object>
	 */
	protected function suggested_posts( int $parent ): array {
		$this->parent = $parent;

		return [
			new \WP_Post(
				(object) [
					'ID'         => 1,
					'post_title' => 'Suggested by the subclass',
					'post_type'  => 'post',
				]
			),
		];
	}

	/**
	 * A row with the post type.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return array<string, mixed>
	 */
	protected function row_data( \WP_Post $post ): array {
		return [
			'ID'        => $post->ID,
			'title'     => $post->post_title,
			'post_type' => $post->post_type,
		];
	}
}
