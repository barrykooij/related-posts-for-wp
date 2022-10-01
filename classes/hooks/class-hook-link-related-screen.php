<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Hook_Link_Related_Screen extends RP4WP_Hook {
	protected $tag = 'admin_menu';

	public function run() {

		// handle singular link request
		$this->handle_create_link();

		// handle bulk link request
		$this->handle_bulk_link();

		// catch search requests
		$this->catch_search();

		// Add Page
		$screen_hook = add_submenu_page( null, 'Link_Related_Screen', 'Link_Related_Screen', 'edit_posts', 'rp4wp_link_related', array( $this, 'content' ) );

		// add screen options
		add_action( 'load-' . $screen_hook, array( $this, 'init_screen' ) );
	}

	/**
	 * Add screen options
	 */
	public function init_screen() {
		add_screen_option( 'per_page', array(
			'label' => 'Posts',
			'default' => 20,
			'option' => 'rp4wp_per_page'
		) );
	}

	/**
	 * Catch post search requests on our manually linking page and do a post to get
	 */
	private function catch_search() {
		if ( isset( $_GET['page'] ) && 'rp4wp_link_related' == $_GET['page'] && isset ( $_POST['s'] ) ) {
			$base_url = admin_url( sprintf( 'admin.php?page=rp4wp_link_related&rp4wp_parent=%d&rp4wp_view=%s', absint( $_GET['rp4wp_parent'] ), esc_attr( $_GET['rp4wp_view'] ) ) );
			if ( ! empty( $_POST['s'] ) ) {
				$s = urlencode( $_POST['s'] );
				// post to get solution
				$url = add_query_arg( 's', $s, $base_url );
			} else {
				$url = remove_query_arg( 's', $base_url );
			}
			// post to get solution
			wp_redirect( $url, 302 );
			exit;
		}
	}

	/**
	 * Check if the current user is allowed to create related posts
	 */
	private function check_if_allowed() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'There was a problem loading this page, you may not have the necessary permissions.' );
		}
	}

	/**
	 * Handle the create link action
	 */
	private function handle_create_link() {

		// Check if link is chosen
		if ( isset( $_GET['rp4wp_create_link'] ) && isset( $_GET['rp4wp_parent'] ) ) {

			// Check if user is allowed to do this
			$this->check_if_allowed();

			// Get parent
			$parent = absint( $_GET['rp4wp_parent'] );

			// Create link
			$post_link_manager = new RP4WP_Post_Link_Manager();

			// Create link
			$post_link_manager->add( $parent, absint( $_GET['rp4wp_create_link'] ) );

			// Send back
			$redirect_url = get_admin_url() . "post.php?post={$parent}&action=edit";

			// WPML check
			if ( isset( $_GET['lang'] ) ) {
				$redirect_url .= "&amp;lang=" . esc_attr( $_GET['lang'] );
			}

			wp_redirect( $redirect_url );
			exit;
		}

	}

	/**
	 * Handle the bulk creation of links
	 */
	private function handle_bulk_link() {

		if ( isset( $_POST['rp4wp_bulk'] ) && isset( $_GET['rp4wp_parent'] ) ) {

			// Get parent
			$parent = absint( $_GET['rp4wp_parent'] );

			// Check if user is allowed to do this
			$this->check_if_allowed();

			// Post Link Manager
			$post_link_manager = new RP4WP_Post_Link_Manager();

			if ( count( $_POST['rp4wp_bulk'] ) > 0 ) {
				foreach ( $_POST['rp4wp_bulk'] as $bulk_post ) {

					// Create link
					$post_link_manager->add( $parent, $bulk_post );

				}
			}

			// Send back
			$redirect_url = get_admin_url() . "post.php?post={$parent}&action=edit";

			// WPML check
			if ( isset( $_GET['lang'] ) ) {
				$redirect_url .= "&amp;lang=" . esc_attr( $_GET['lang'] );
			}

			wp_redirect( $redirect_url );
			exit;

		}

	}

	/**
	 * The screen content
	 */
	public function content() {

		// Check if user is allowed to do this
		$this->check_if_allowed();

		if ( ! isset( $_GET['rp4wp_parent'] ) ) {
			wp_die( "Can't load page, no parent set. Please contact support and provide them this message" );
		}

		// Parent
		$parent = absint( $_GET['rp4wp_parent'] );

		// Setup cancel URL
		$cancel_url = get_admin_url() . "post.php?post={$parent}&action=edit";

		// Catch search string
		$search = null;
		if ( isset( $_GET['s'] ) && $_GET['s'] != '' ) {
			$search = $_GET['s'];
		}

		?>
		<div class="wrap">
			<h2>
				<?php _e( 'Posts', 'related-posts-for-wp' ); ?>
				<a href="<?php echo esc_attr( $cancel_url ); ?>" class="add-new-h2"><?php _e( 'Cancel linking', 'related-posts-for-wp' ); ?></a>
			</h2>

			<form id="sp-list-table-form" method="post">
				<input type="hidden" name="page" value="<?php esc_attr_e( $_REQUEST['page'] ); ?>" />
				<?php
				// Create the link table
				$list_table = new RP4WP_Link_Related_Table();

				// Set the search
				$list_table->set_search( $search );

				// Load the items
				$list_table->prepare_items();

				// Add the search box
				$list_table->search_box( __( 'Search', 'related-posts-for-wp' ), 'sp-search' );

				// Display the table
				$list_table->display();
				?>
			</form>
		</div>

	<?php
	}
}
