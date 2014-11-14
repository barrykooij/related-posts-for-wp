<?php

if ( ! defined( 'ABSPATH' ) ) {
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
				<p><?php _e( 'Thank you for using Related Posts for WordPress!', 'related-posts-for-wp' ); ?></p>
			</div>

			<div class="rp4wp-box rp4wp-box-upgrade">
				<h3 class="rp4wp-title"><?php _e( 'Related Posts for WordPress Premium', 'related-posts-for-wp' ); ?></h3>

				<p><?php _e( "This plugin has an even better premium version, I am sure you will love it.", 'related-posts-for-wp' ); ?></p>
				<p><?php _e( "Premium features include custom post type support, related post themes, custom taxonomy support and priority support.", 'related-posts-for-wp' ); ?></p>
				<p><?php printf( __( "%sMore information about Related Posts for WP Premium »%s", 'related-posts-for-wp' ), '<a href="https://www.relatedpostsforwp.com/upgrade-premium/?utm_source=plugin&utm_medium=link&utm_campaign=upgrade-box" target="_blank">', '</a>' ); ?></p>
			</div>

			<div class="rp4wp-box">
				<h3 class="rp4wp-title"><?php _e( 'Show a token of your appreciation', 'related-posts-for-wp' ); ?></h3>

				<p><?php printf( __( "<a href='%s' target='_blank'>Leave a ★★★★★ plugin review on WordPress.org</a>", 'related-posts-for-wp' ), 'http://wordpress.org/support/view/plugin-reviews/related-posts-for-wp?rate=5#postform' ); ?></p>
				<p><?php printf( __( "<a href='%s' target='_blank'>Tweet about Related Posts for WordPress</a>", 'related-posts-for-wp' ), 'https://twitter.com/intent/tweet?text=Showing%20my%20appreciation%20to%20%40CageNL%20for%20his%20WordPress%20plugin%3A%20Related%20Posts%20for%20WordPress%20-%20check%20it%20out!%20http%3A%2F%2Fwordpress.org%2Fplugins%2Frelated-posts-for-wp%2F' ); ?></p>
				<p><?php printf( __( "Review the plugin on your blog and link to <a href='%s' target='_blank'>the plugin page</a>", 'related-posts-for-wp' ), 'https://www.relatedpostsforwp.com/?utm_source=plugin&utm_medium=link&utm_campaign=show-appreciation' ); ?></p>
				<p><?php printf( __( "<a href='%s' target='_blank'>Vote 'works' on the WordPress.org plugin page</a>", 'related-posts-for-wp' ), 'http://wordpress.org/plugins/related-posts-for-wp/' ); ?></p>

			</div>

			<div class="rp4wp-box">
				<h3 class="rp4wp-title"><?php _e( 'Looking for support?', 'related-posts-for-wp' ); ?></h3>

				<p><?php printf( __( "For support please visit the <a href='%s' target='_blank'>WordPress.org forums</a>.", 'related-posts-for-wp' ), 'http://wordpress.org/support/plugin/related-posts-for-wp' ); ?></p>

				<p style="color: green;font-weight: bold;"><?php printf( __( "Did you know that Related Posts for WordPress Premium clients get priority email support? %sClick here to upgrade.%s", 'related-posts-for-wp' ), '<a href="https://www.relatedpostsforwp.com/upgrade-premium/?utm_source=plugin&utm_medium=link&utm_campaign=support" target="_blank">', '</a>' ); ?></p>
			</div>

			<div class="rp4wp-box">
				<h3 class="rp4wp-title"><?php _e( 'More information', 'related-posts-for-wp' ); ?></h3>

				<p><?php printf( __( "<a href='%s' target='_blank'>FAQ</a>", 'related-posts-for-wp' ), 'https://wordpress.org/plugins/related-posts-for-wp/faq/' ); ?></p>

				<p><?php printf( __( "<a href='%s' target='_blank'>Changelog</a>", 'related-posts-for-wp' ), 'https://wordpress.org/plugins/related-posts-for-wp/changelog/' ); ?></p>

				<p><?php printf( __( "<a href='%s' target='_blank'>Website</a>", 'related-posts-for-wp' ), 'https://www.relatedpostsforwp.com/?utm_source=plugin&utm_medium=link&utm_campaign=more-information' ); ?></p>

			</div>

			<div class="rp4wp-box">
				<h3 class="rp4wp-title"><?php _e( 'About the developer', 'related-posts-for-wp' ); ?></h3>

				<p><?php _e( "Barry is a WordPress developer that works on WooCommerce by WooThemes and is the author of various WordPress plugins that include Related Posts for WordPress, Post Connector and What The File.", 'related-posts-for-wp' ); ?></p>

				<p><?php _e( "Barry likes contributing to opensource projects and visiting WordCamps and WordPress meetups. He’s the organizer of the WordPress meetup in Tilburg.", 'related-posts-for-wp' ); ?></p>

				<p><?php printf( __( "<a href='%s' target='_blank'>Follow Barry on Twitter</a>", 'related-posts-for-wp' ), 'https://twitter.com/cageNL' ); ?></p>
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
					<?php settings_fields( 'rp4wp' );    //pass slug name of page, also referred
					//to in Settings API as option group name
					do_settings_sections( 'rp4wp' );    //pass slug name of page
					submit_button();
					?>
				</form>
			</div>
			<?php $this->sidebar(); ?>
		</div>
	<?php
	}
}