<?php
/**
 * The unreadable post titles test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Security;

use LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen\ListTable;
use LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen\Page;
use LV2\WordPress\RelatedPostsForWP\Admin\MetaBox\ManageLinks;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * Regression tests for the 3.0.1 fix of the titles of posts a user can't read: the meta box leaves out related posts
 * the user can't read, also those linked before the link screen checked this, and the link screen neither lists nor
 * counts them, whatever statuses a site offers and whatever a user searches for.
 * That the link screen doesn't link them is covered by LinkPermissionsTest.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Admin\PostAccess
 * @covers \LV2\WordPress\RelatedPostsForWP\Admin\MetaBox\ManageLinks
 * @covers \LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen\ListTable
 */
final class UnreadablePostTitlesTest extends TestCase {

	/**
	 * An administrator.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * A contributor.
	 *
	 * @var int
	 */
	private int $contributor;

	/**
	 * The statuses the link screen offers, through rp4wp_manual_link_post_statuses.
	 *
	 * @var string[]
	 */
	private array $statuses = [];

	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		$this->admin       = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->contributor = self::factory()->user->create( [ 'role' => 'contributor' ] );
	}

	public function tear_down(): void {
		remove_filter( 'rp4wp_manual_link_post_statuses', [ $this, 'offered_statuses' ] );
		$_GET     = [];
		$_REQUEST = [];
		set_current_screen( 'front' );

		parent::tear_down();
	}

	public function test_the_meta_box_leaves_out_related_posts_the_user_cannot_read(): void {
		$draft = $this->post( $this->contributor, 'draft', 'My draft' );
		$links = new LinkRepository();
		foreach ( [ 'draft', 'pending', 'private', 'future', 'trash' ] as $status ) {
			$links->add( $draft, $this->post( $this->admin, $status, "Secret {$status} post" ) );
		}
		$links->add( $draft, $this->post( $this->admin, 'publish', 'A published post' ) );

		wp_set_current_user( $this->contributor );
		$html = $this->render_meta_box( $draft );

		$this->assertStringNotContainsString( 'Secret', $html );
		$this->assertStringContainsString( 'A published post', $html );
		$this->assertSame( 1, substr_count( $html, '<tr ' ) );

		// An editor can read them all.
		$this->act_as( 'editor' );
		$this->assertSame( 6, substr_count( $this->render_meta_box( $draft ), '<tr ' ) );
	}

	public function test_the_meta_box_shows_the_own_unpublished_posts_of_the_user(): void {
		$draft = $this->post( $this->contributor, 'draft', 'My draft' );
		( new LinkRepository() )->add( $draft, $this->post( $this->contributor, 'pending', 'My pending post' ) );

		wp_set_current_user( $this->contributor );

		$this->assertStringContainsString( 'My pending post', $this->render_meta_box( $draft ) );
	}

	public function test_the_link_screen_lists_and_counts_private_posts_only_for_users_who_can_read_them(): void {
		$draft = $this->post( $this->contributor, 'draft', 'My draft' );
		$this->post( $this->admin, 'private', 'Secret private post' );
		$this->post( $this->admin, 'publish', 'A published post' );

		wp_set_current_user( $this->contributor );
		$this->assertSame( $this->view( [ 'A published post' ] ), $this->all_view( $draft ) );

		$this->act_as( 'editor' );
		$this->assertSame( $this->view( [ 'A published post', 'Secret private post' ] ), $this->all_view( $draft ) );
	}

	/**
	 * The statuses a site can offer with rp4wp_manual_link_post_statuses, beyond the default.
	 *
	 * @return array<string, array{string[]}>
	 */
	public static function extra_statuses(): array {
		return [
			'drafts and pending posts' => [ [ 'publish', 'private', 'draft', 'pending' ] ],
			'any status'               => [ [ 'any' ] ],
		];
	}

	/**
	 * @dataProvider extra_statuses
	 *
	 * @param string[] $statuses The statuses the site offers.
	 */
	public function test_the_link_screen_lists_and_counts_posts_of_extra_statuses_only_for_users_who_can_read_them( array $statuses ): void {
		$draft = $this->post( $this->contributor, 'draft', 'My draft' );
		$this->post( $this->contributor, 'pending', 'My pending post' );
		$this->post( $this->admin, 'draft', 'Secret draft post' );
		$this->post( $this->admin, 'pending', 'Secret pending post' );
		$this->post( $this->admin, 'private', 'Secret private post' );
		$this->post( $this->admin, 'publish', 'A published post' );
		$this->offer( $statuses );

		wp_set_current_user( $this->contributor );
		$this->assertSame( $this->view( [ 'A published post', 'My draft', 'My pending post' ] ), $this->all_view( $draft ) );

		$this->act_as( 'editor' );
		$this->assertSame(
			$this->view( [ 'A published post', 'My draft', 'My pending post', 'Secret draft post', 'Secret pending post', 'Secret private post' ] ),
			$this->all_view( $draft )
		);
	}

	public function test_a_search_on_the_link_screen_neither_finds_nor_counts_posts_the_user_cannot_read(): void {
		$draft = $this->post( $this->contributor, 'draft', 'My draft' );
		$this->post( $this->admin, 'draft', 'Secret draft post' );
		$this->post( $this->admin, 'private', 'Secret private post' );
		$this->offer( [ 'publish', 'private', 'draft' ] );

		wp_set_current_user( $this->contributor );
		$this->assertSame( $this->view( [] ), $this->all_view( $draft, 'Secret' ) );

		$this->act_as( 'editor' );
		$this->assertSame( $this->view( [ 'Secret draft post', 'Secret private post' ] ), $this->all_view( $draft, 'Secret' ) );
	}

	/**
	 * The rp4wp_manual_link_post_statuses callback: the statuses the test offers.
	 *
	 * @return string[]
	 */
	public function offered_statuses(): array {
		return $this->statuses;
	}

	/**
	 * Offer posts of other statuses on the link screen, as a site can with rp4wp_manual_link_post_statuses.
	 *
	 * @param string[] $statuses The statuses.
	 *
	 * @return void
	 */
	private function offer( array $statuses ): void {
		$this->statuses = $statuses;
		add_filter( 'rp4wp_manual_link_post_statuses', [ $this, 'offered_statuses' ] );
	}

	/**
	 * What the "all" view shows: these titles, and as many posts in total.
	 *
	 * @param string[] $titles The titles.
	 *
	 * @return array{titles: string[], total: int}
	 */
	private function view( array $titles ): array {
		return [
			'titles' => $titles,
			'total'  => count( $titles ),
		];
	}

	/**
	 * A post.
	 *
	 * @param int    $author The author.
	 * @param string $status The status.
	 * @param string $title  The title.
	 *
	 * @return int
	 */
	private function post( int $author, string $status, string $title ): int {
		return self::factory()->post->create(
			[
				'post_author' => $author,
				'post_status' => $status,
				'post_title'  => $title,
				'post_date'   => 'future' === $status ? gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) : '',
			]
		);
	}

	/**
	 * The output of the meta box of a post.
	 *
	 * @param int $post_id The post.
	 *
	 * @return string
	 */
	private function render_meta_box( int $post_id ): string {
		ob_start();
		ManageLinks::render( get_post( $post_id ) );

		return (string) ob_get_clean();
	}

	/**
	 * What the "all" view of the link screen of a post shows: the titles of its posts, sorted by title, and how many
	 * posts it says there are in total.
	 *
	 * @param int         $parent The post links are added to.
	 * @param string|null $search The search text, if any.
	 *
	 * @return array{titles: string[], total: int}
	 */
	private function all_view( int $parent, ?string $search = null ): array {
		set_current_screen( 'admin_page_' . Page::SLUG );
		Page::add_screen_options();
		$_GET     = [
			'page'         => Page::SLUG,
			'rp4wp_parent' => (string) $parent,
			'rp4wp_view'   => 'all',
		];
		$_REQUEST = $_GET;

		$table = new ListTable();
		$table->set_search( $search );

		// Loading the items also prints the views.
		ob_start();
		$table->prepare_items();
		ob_end_clean();

		return [
			'titles' => array_column( $table->items, 'title' ),
			'total'  => (int) $table->get_pagination_arg( 'total_items' ),
		];
	}
}
