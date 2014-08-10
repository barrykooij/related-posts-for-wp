<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Hook_Settings_Page extends RP4WP_Hook {
	protected $tag = 'admin_menu';

	/**
	 * Hook callback, add the sub menu page
	 *
	 * @since  1.1.0
	 * @access public
	 */
	public function run() {
		$menu_hook = add_submenu_page( 'options-general.php', __( 'Related Posts', 'related-posts-for-wp' ), __( 'Related Posts', 'related-posts-for-wp' ), 'manage_options', 'rp4wp', array(
			$this,
			'screen'
		) );

		add_action( 'load-' . $menu_hook, array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue settings page assets
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'rp4wp-settings-css', plugins_url( '/assets/css/settings.css', RP4WP::get_plugin_file() ) );
	}

	/**
	 * The sidebar
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function sidebar() {
		?>
		<div class="rp4wp-sidebar">

			<div class="rp4wp-box">
				<div class="rp4wp-sidebar-header">
					<h3>Related Posts for WordPress</h3>
				</div>

				<p><?php _e( 'Plugin version', 'related-posts-for-wp' ); ?>: <?php echo RP4WP::VERSION; ?></p>
			</div>

			<div class="rp4wp-box">
				<h3 class="rp4wp-title"><?php _e( 'More information', 'related-posts-for-wp' ); ?></h3>

				<p><?php printf( __( "<a href='%s'>FAQ</a>", 'related-posts-for-wp' ), 'http://wordpress.org/plugins/related-posts-for-wp/faq/' ); ?></p>

				<p><?php printf( __( "<a href='%s'>Change log</a>", 'related-posts-for-wp' ), 'http://wordpress.org/support/plugin/related-posts-for-wp' ); ?></p>

				<p><?php printf( __( "<a href='%s'>Give us a review</a>", 'related-posts-for-wp' ), 'http://wordpress.org/support/view/plugin-reviews/related-posts-for-wp' ); ?></p>

				<p><?php printf( __( "<a href='%s'>Release blog post</a>", 'related-posts-for-wp' ), 'http://www.barrykooij.com/related-posts-wordpress/?utm_source=plugin&utm_medium=link&utm_campaign=sidebar' ); ?></p>
			</div>

			<div class="rp4wp-box">
				<h3 class="rp4wp-title"><?php _e( 'About the developer', 'related-posts-for-wp' ); ?></h3>
				
				<p><?php _e( "Barry is a WordPress developer that works on WooCommerce by WooThemes and is the author of various WordPress plugins that include Post Connector, Related Posts for WordPress and What The File.", 'related-posts-for-wp' ); ?></p>

				<p><?php _e( "Barry likes contributing to opensource projects and visiting WordCamps and WordPress meetups. He’s the organizer of the WordPress meetup in Tilburg.", 'related-posts-for-wp' ); ?></p>

				<p><?php printf( __( "You can follow Barry on Twitter <a href='%s'>here</a>.", 'related-posts-for-wp' ), 'https://twitter.com/cageNL' ); ?></p>
			</div>

		</div>
	<?php
	}

	/**
	 * Settings screen output
	 *
	 * @since  1.1.0
	 * @access public
	 */
	public function screen() {
		?>
		<div class="wrap">
			<h2>Related Posts for WordPress</h2>

				<div class="rp4wp-content">
					<form method="post" action="options.php">
						<?php settings_fields( 'rp4wp' );	//pass slug name of page, also referred
						//to in Settings API as option group name
						do_settings_sections( 'rp4wp' ); 	//pass slug name of page
						submit_button();
						?>
					</form>
			</div>
			<?php $this->sidebar(); ?>
		</div>
	<?php
	}
}