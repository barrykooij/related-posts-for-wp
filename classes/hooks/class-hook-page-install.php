<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Hook_Page_Install extends RP4WP_Hook {
	protected $tag = 'admin_menu';

	public function run() {

		$menu_hook = add_submenu_page( null, 'RP4WPINSTALL', 'RP4WPINSTALL', 'edit_posts', 'rp4wp_install', array(
				$this,
				'content'
			) );

		add_action( 'load-' . $menu_hook, array( $this, 'enqueue_install_assets' ) );
	}

	/**
	 * Enqueue install assets
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function enqueue_install_assets() {
		global $wp_scripts;
		wp_enqueue_style( 'rp4wp-install-css', plugins_url( '/assets/css/install.css', RP4WP::get_plugin_file() ) );
		wp_enqueue_script( 'rp4wp-install-js', plugins_url( '/assets/js/install' . ( ( ! SCRIPT_DEBUG ) ? '.min' : '' ) . '.js', RP4WP::get_plugin_file() ), array(
				'jquery',
				'jquery-ui-core',
				'jquery-ui-progressbar'
			) );
		wp_enqueue_style( 'jquery-ui-smoothness', "http://ajax.googleapis.com/ajax/libs/jqueryui/" . $wp_scripts->query( 'jquery-ui-core' )->ver . "/themes/smoothness/jquery-ui.css", false, null );
	}

	/**
	 * The screen content
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function content() {

		// Do we have a reinstall?
		if ( isset( $_GET['reinstall'] ) ) {

			// Check nonce
			if ( ! wp_verify_nonce( ( isset( $_GET['rp4wp_nonce'] ) ? $_GET['rp4wp_nonce'] : '' ), RP4WP_Constants::NONCE_INSTALL ) ) {
				wp_die( 'Woah! It looks like something else tried to run the Related Posts for WordPress installation wizard! We were able to stop them, nothing was lost. Please report this incident at <a href="http://wordpress.org/support/plugin/related-posts-for-wp" target="_blank">our forums.</a>' );
			}

			global $wpdb;

			// Get ID's of related post link posts
			$link_ids = get_posts(
				array(
					'post_type' => RP4WP_Constants::LINK_PT,
					'fields' => 'ids',
					'posts_per_page' => - 1
				)
			);

			// Only run queries if we have ID's
			if ( count( $link_ids ) > 0 ) {
				// Delete all link posts
				$wpdb->query( "DELETE FROM $wpdb->posts WHERE `ID` IN (" . implode( ",", $link_ids ) . ");" );

				// Delete all link post meta
				$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE `post_id` IN (" . implode( ",", $link_ids ) . ");" );
			}

			// Remove the post meta we attached to posts
			$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE `meta_key` = 'rp4wp_auto_linked' OR `meta_key` = 'rp4wp_cached' " );

			// Empty word cache
			$wpdb->query( "DELETE FROM " . RP4WP_Related_Word_Manager::get_database_table() . " WHERE 1=1" );
		}

		// The steps
		$steps = array(
			1 => __( 'Caching Posts', 'related-posts-for-wp' ),
			2 => __( 'Linking Posts', 'related-posts-for-wp' ),
			3 => __( 'Finished', 'related-posts-for-wp' ),
		);

		// What's the current step?
		$cur_step = isset( $_GET['step'] ) ? $_GET['step'] : 1;

		// Check installer resume options
		if ( 1 == $cur_step ) {
			// Add is installing site option
			add_option( RP4WP_Constants::OPTION_IS_INSTALLING, true );
		} elseif ( 3 == $cur_step ) {
			// Installer is done, remove the option
			delete_option( RP4WP_Constants::OPTION_IS_INSTALLING );
		}

		?>
		<div class="wrap">
			<h2>Related Posts for WordPress <?php _e( 'Installation', 'related-posts-for-wp' ); ?></h2>

			<ul class="install-steps">
				<?php

				foreach ( $steps as $step => $label ) {
					echo "<li id='step-bar-" . $step . "'" . ( ( $cur_step == $step ) ? " class='step-bar-active'" : "" ) . "><span>" . $step . '. ' . $label . "</span></li>" . PHP_EOL;
				}
				?>
			</ul>
			<br class="clear"/>

			<h3><?php echo $steps[ $cur_step ]; ?></h3>

			<?php
			$cur_step = isset( $_GET['step'] ) ? $_GET['step'] : 1;
			?>
			<div class='rp4wp-step rp4wp-step-<?php echo $cur_step; ?>' rel='<?php echo $cur_step; ?>'>
				<?php

				// Hidden fields
				echo "<input type='hidden' id='rp4wp_total_posts' value='" . wp_count_posts( 'post' )->publish . "' />" . PHP_EOL;
				echo "<input type='hidden' id='rp4wp_admin_url' value='" . admin_url() . "' />" . PHP_EOL;

				if ( 1 == $cur_step ) {

					// Echo current uncached posts
					$related_word_manager = new RP4WP_Related_Word_Manager();
					echo "<input type='hidden' id='rp4wp_uncached_posts' value='" . $related_word_manager->get_uncached_post_count() . "' />" . PHP_EOL;

					?>
					<p><?php _e( 'Thank you for choosing Related Posts for WordPress!', 'related-posts-for-wp' ); ?></p>
					<p><?php _e( 'Before you can start using Related Posts for WordPress we need to cache your current posts.', 'related-posts-for-wp' ); ?></p>
					<p><?php _e( "This is a one time process which might take some time now, depending on the amount of posts you have, but will ensure your website's performance when using the plugin.", 'related-posts-for-wp' ); ?></p>

					<p style="font-weight: bold;"><?php _e( 'Do NOT close this window, wait for this process to finish and this wizard to take you to the next step.', 'related-posts-for-wp' ); ?></p>

					<div id="progressbar"></div>
				<?php
				} elseif ( 2 == $cur_step ) {

					// Echo current uncached posts
					$related_post_manager = new RP4WP_Related_Post_Manager();
					echo "<input type='hidden' id='rp4wp_uncached_posts' value='" . $related_post_manager->get_uncached_post_count() . "' />" . PHP_EOL;

					?>
					<p style="font-weight: bold;"><?php _e( 'Great! All your posts were successfully cached!', 'related-posts-for-wp' ); ?></p>
					<p><?php _e( "You can let me link your posts, based on what I think is related, to each other. And don't worry, if I made a mistake at one of your posts you can easily correct this by editing it manually!", 'related-posts-for-wp' ); ?></p>
					<p><?php _e( 'Want me to start linking posts to each other? Fill in the amount of related posts each post should have and click on the "Link now" button. Rather link your posts manually? Click "Skip linking".', 'related-posts-for-wp' ); ?></p>
					<p style="font-weight: bold;"><?php _e( 'Do NOT close this window if you click the "Link now" button, wait for this process to finish and this wizard to take you to the next step.', 'related-posts-for-wp' ); ?></p>
					<br class="clear"/>
					<p class="rp4wp-install-link-box">
						<label
							for="rp4wp_related_posts_amount"><?php _e( 'Amount of related posts per post:', 'related-posts-for-wp' ); ?></label><input
							class="form-input-tip" type="text" id="rp4wp_related_posts_amount"
							value="<?php echo RP4WP()->settings->get_option( 'automatic_linking_post_amount' ); ?>"/>
						<a href="javascript:;" class="button button-primary button-large rp4wp-link-now-btn"
						   id="rp4wp-link-now"><?php _e( 'Link now', 'related-posts-for-wp' ); ?></a>
						<a href="<?php echo admin_url(); ?>?page=rp4wp_install&step=3"
						   class="button"><?php _e( 'Skip linking', 'related-posts-for-wp' ); ?></a>
					</p>
					<br class="clear"/>
					<div id="progressbar"></div>
				<?php
				} elseif ( 3 == $cur_step ) {
					?>
					<p><?php _e( "That's it, you're good to go!", 'related-posts-for-wp' ); ?></p>
					<p><?php printf( __( 'Thanks again for using Related Posts for WordPress and if you have any questions be sure to ask them at the %sWordPress.org forums.%s', 'related-posts-for-wp' ), '<a href="http://wordpress.org/support/plugin/related-posts-for-wp" target="_blank">', '</a>' ); ?></p>
				<?php
				}
				?>

				<div class="rp4wp-box rp4wp-box-upgrade">
					<h3 class="rp4wp-title"><?php _e( 'Related Posts for WordPress Premium', 'related-posts-for-wp' ); ?></h3>

					<p><?php _e( "This plugin has an even better premium version, I am sure you will love it.", 'related-posts-for-wp' ); ?></p>

					<p><?php _e( "Premium features include custom post type support, related post themes, custom taxonomy support and priority support.", 'related-posts-for-wp' ); ?></p>

					<p><?php printf( __( "%sMore information about Related Posts for WP Premium (opens in new window) »%s", 'related-posts-for-wp' ), '<a href="https://www.relatedpostsforwp.com/upgrade-premium/?utm_source=plugin&utm_medium=link&utm_campaign=install" target="_blank">', '</a>' ); ?></p>
				</div>

			</div>

		</div>

	<?php
	}

}