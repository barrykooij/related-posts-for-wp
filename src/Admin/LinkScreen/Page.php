<?php
/**
 * The link screen class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen;

use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * The hidden screen that adds related posts to a post by hand, one at a time or in bulk.
 *
 * The premium add-on extends this class: it adds links and builds the table through add_link() and list_table(), and
 * every callback is registered for the class that set up the module.
 */
class Page implements Module {

	/**
	 * The admin page slug.
	 */
	public const SLUG = 'rp4wp_link_related';

	/**
	 * The nonce action of the "Link Post" links.
	 */
	public const NONCE_LINK = 'rp4wp_link_nonce';

	/**
	 * The screen option with the number of posts per page.
	 */
	public const PER_PAGE_OPTION = 'rp4wp_per_page';

	/**
	 * Register the page and its screen option.
	 *
	 * @return void
	 */
	public static function setup(): void {
		LegacyHooks::add_action(
			'RP4WP_Hook_Link_Related_Screen',
			'admin_menu',
			[ static::class, 'register' ],
			10,
			1,
			[
				'init_screen' => [ static::class, 'add_screen_options' ],
				'content'     => [ static::class, 'render' ],
			]
		);

		LegacyHooks::add_filter( 'RP4WP_Filter_Set_Screen_Option', 'set-screen-option', [ static::class, 'save_screen_option' ], 10, 3 );
	}

	/**
	 * Handle link requests, then register the page without a menu entry.
	 *
	 * @return void
	 */
	public static function register(): void {
		self::handle_create_link();
		self::handle_bulk_link();
		self::handle_search();

		$hook = add_submenu_page( '', 'Link_Related_Screen', 'Link_Related_Screen', 'edit_posts', self::SLUG, [ static::class, 'render' ] );

		add_action( 'load-' . $hook, [ static::class, 'add_screen_options' ] );
	}

	/**
	 * The "posts per page" screen option.
	 *
	 * @return void
	 */
	public static function add_screen_options(): void {
		add_screen_option(
			'per_page',
			[
				'label'   => 'Posts',
				'default' => 20,
				'option'  => self::PER_PAGE_OPTION,
			]
		);
	}

	/**
	 * Save the "posts per page" screen option.
	 *
	 * @param mixed  $status The value to save, false by default.
	 * @param string $option The option.
	 * @param mixed  $value  The submitted value.
	 *
	 * @return mixed
	 */
	public static function save_screen_option( $status, $option, $value ) {
		return self::PER_PAGE_OPTION === $option ? $value : $status;
	}

	/**
	 * The page.
	 *
	 * @return void
	 */
	public static function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Displaying the screen; changes are handled with nonces above.
		if ( ! isset( $_GET['rp4wp_parent'] ) ) {
			wp_die( "Can't load page, no parent set. Please contact support and provide them this message" );
		}

		$parent = absint( $_GET['rp4wp_parent'] );
		self::check_if_allowed( $parent );

		$cancel_url = get_admin_url() . 'post.php?post=' . $parent . '&action=edit';

		$search = null;
		if ( isset( $_GET['s'] ) && '' !== $_GET['s'] ) {
			$search = sanitize_text_field( wp_unslash( $_GET['s'] ) );
		}
		// phpcs:enable
		?>
		<div class="wrap">
			<h2>
				<?php esc_html_e( 'Posts', 'related-posts-for-wp' ); ?>
				<a href="<?php echo esc_attr( $cancel_url ); ?>" class="add-new-h2"><?php esc_html_e( 'Cancel linking', 'related-posts-for-wp' ); ?></a>
			</h2>

			<form id="sp-list-table-form" method="post">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>"/>
				<?php
				$list_table = static::list_table( $parent );
				$list_table->set_search( $search );
				$list_table->prepare_items();
				$list_table->search_box( __( 'Search', 'related-posts-for-wp' ), 'sp-search' );
				$list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Link one post, from the "Link Post" row action, then go back to the editor.
	 *
	 * @return void
	 */
	private static function handle_create_link(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The nonce is checked below.
		if ( ! isset( $_GET['rp4wp_create_link'], $_GET['rp4wp_parent'] ) ) {
			return;
		}

		$parent = absint( $_GET['rp4wp_parent'] );
		self::check_if_allowed( $parent );

		if ( ! isset( $_GET['rp4wp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['rp4wp_nonce'] ) ), self::NONCE_LINK ) ) {
			wp_die( 'There was a problem creating the links, please try again. (nonce failed)' );
		}

		static::add_link( $parent, absint( $_GET['rp4wp_create_link'] ) );
		// phpcs:enable

		wp_safe_redirect( self::edit_url( $parent ) );
		exit;
	}

	/**
	 * Link the checked posts, from the bulk action, then go back to the editor.
	 *
	 * @return void
	 */
	private static function handle_bulk_link(): void {
		// phpcs:disable WordPress.Security.NonceVerification -- The nonce is checked below.
		if ( ! isset( $_POST['rp4wp_bulk'], $_GET['rp4wp_parent'] ) ) {
			return;
		}

		$parent = absint( $_GET['rp4wp_parent'] );
		self::check_if_allowed( $parent );

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bulk-admin_page_' . self::SLUG ) ) {
			wp_die( 'There was a problem creating the link, please try again. (nonce failed)' );
		}

		if ( is_array( $_POST['rp4wp_bulk'] ) ) {
			foreach ( array_map( 'absint', wp_unslash( $_POST['rp4wp_bulk'] ) ) as $child_id ) {
				// Only accept post IDs.
				if ( $child_id > 0 ) {
					static::add_link( $parent, $child_id );
				}
			}
		}
		// phpcs:enable

		wp_safe_redirect( self::edit_url( $parent ) );
		exit;
	}

	/**
	 * Turn a search form post into a GET request, so the search ends up in the URL.
	 *
	 * @return void
	 */
	private static function handle_search(): void {
		// phpcs:disable WordPress.Security.NonceVerification -- Only turns the search form post into a redirect.
		if ( ! isset( $_GET['page'], $_POST['s'] ) || self::SLUG !== $_GET['page'] ) {
			return;
		}

		$parent = isset( $_GET['rp4wp_parent'] ) ? absint( $_GET['rp4wp_parent'] ) : 0;
		$view   = isset( $_GET['rp4wp_view'] ) ? sanitize_key( $_GET['rp4wp_view'] ) : '';
		$base   = admin_url( sprintf( 'admin.php?page=%s&rp4wp_parent=%d&rp4wp_view=%s', self::SLUG, $parent, $view ) );
		$search = sanitize_text_field( wp_unslash( $_POST['s'] ) );
		// phpcs:enable

		wp_safe_redirect( '' !== $search ? add_query_arg( 's', rawurlencode( $search ), $base ) : remove_query_arg( 's', $base ), 302 );
		exit;
	}

	/**
	 * Link a post to a parent, by hand.
	 *
	 * @param int $parent The post links are added to.
	 * @param int $child  The post to link.
	 *
	 * @return void
	 */
	protected static function add_link( int $parent, int $child ): void {
		( new LinkRepository() )->add( $parent, $child );
	}

	/**
	 * The table of posts to link to a parent.
	 *
	 * @param int $parent The post links are added to.
	 *
	 * @return ListTable
	 */
	protected static function list_table( int $parent ): ListTable { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Subclasses use it.
		return new ListTable();
	}

	/**
	 * The editor URL of a post, keeping the WPML language.
	 *
	 * @param int $parent The post.
	 *
	 * @return string
	 */
	private static function edit_url( int $parent ): string {
		$url = get_admin_url() . "post.php?post={$parent}&action=edit";

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only passed along.
		if ( isset( $_GET['lang'] ) ) {
			$url .= '&amp;lang=' . esc_attr( sanitize_text_field( wp_unslash( $_GET['lang'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only passed along.
		}

		return $url;
	}

	/**
	 * Stop users who may not edit posts, or the given post.
	 *
	 * @param int $parent_id The post links are added to; 0 to only check editing posts.
	 *
	 * @return void
	 */
	private static function check_if_allowed( int $parent_id = 0 ): void {
		if ( ! current_user_can( 'edit_posts' ) || ( $parent_id > 0 && ! current_user_can( 'edit_post', $parent_id ) ) ) {
			wp_die( 'There was a problem loading this page, you may not have the necessary permissions.' );
		}
	}
}
