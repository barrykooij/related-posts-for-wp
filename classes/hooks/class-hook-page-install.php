<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Hook_Page_Install extends RP4WP_Hook {
	protected $tag = 'admin_menu';

	public function run() {

		$menu_hook = add_submenu_page( null, 'SRPInstallation', 'SRPInstallation', 'edit_posts', 'rp4wp_install', array( $this, 'content' ) );

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
		wp_enqueue_script( 'rp4wp-install-js', plugins_url( '/assets/js/install' . ( ( !SCRIPT_DEBUG ) ? '.min' : '' ) . '.js', RP4WP::get_plugin_file() ), array( 'jquery', 'jquery-ui-core', 'jquery-ui-progressbar' ) );
		wp_enqueue_style( 'jquery-ui-smoothness', "http://ajax.googleapis.com/ajax/libs/jqueryui/" . $wp_scripts->query( 'jquery-ui-core' )->ver . "/themes/smoothness/jquery-ui.css", false, null );
	}

	/**
	 * The screen content
	 */
	public function content() {

		$steps = array(
			1 => 'Caching Posts',
			2 => 'Linking Posts',
			3 => 'Finished',
		);

		$cur_step = isset( $_GET['step'] ) ? $_GET['step'] : 1;

		?>
		<div class="wrap">
			<h2><?php _e( 'Related Posts for WordPress Installation', 'related-posts-for-wp' ); ?></h2>

			<ul class="install-steps">
				<?php

				foreach ( $steps as $step => $label ) {
					echo "<li id='step-bar-" . $step . "'" . ( ( $cur_step == $step ) ? " class='step-bar-active'" : "" ) . ">" . $label . "</li>" . PHP_EOL;
				}
				?>
			</ul>
			<br class="clear" />

			<h3><?php echo $steps[$cur_step]; ?></h3>

			<?php
			$cur_step = isset( $_GET['step'] ) ? $_GET['step'] : 1;
			echo "<div class='rp4wp-step rp4wp-step-" . $cur_step . "' rel='" . $cur_step . "'>";

			echo "<input type='hidden' id='rp4wp_total_posts' value='" . wp_count_posts( 'post' )->publish . "' />" . PHP_EOL;
			echo "<input type='hidden' id='rp4wp_admin_url' value='" . admin_url() . "' />" . PHP_EOL;

			if ( 1 == $cur_step ) {
				?>
				<p>Thank you for choosing Related Posts for WordPress!<br /><br />Before you can start using Related Posts for WordPress we need to cache your current posts.<br />This is a one time process which might take some time now, depending on the amount of posts you have, but will ensure your website's performance when using the plugin.
				</p>

				<p style="font-weight: bold;">Do NOT close this window, wait for this process to finish and this wizard to take you to the next step.</p>

				<div id="progressbar"></div>
			<?php
			} elseif ( 2 == $cur_step ) {
				?>
				<p style="font-weight: bold;">Great! All your posts were succesfully cached!</p>
				<p>You can let me link your posts, based on what I think is related, to each other. And don't worry, if I made a mistake at one of your posts you can easily correct this by editing it manually!</p>
				<p>Want me to start linking posts to each other? Fill in the amount of related posts each post should have and click on the "Link now" button. Rather link your posts manually? Click "Skip linking".</p>
				<p style="font-weight: bold;">Do NOT close this window if you click the "Link now" button, wait for this process to finish and this wizard to take you to the next step.</p>
				<br class="clear" />
				<p class="rp4wp-install-link-box">
					<label for="rp4wp_related_posts_amount">Amount of related posts per post:</label><input class="form-input-tip" type="text" id="rp4wp_related_posts_amount" value="3" />
					<a href="javascript:;" class="button button-primary button-large rp4wp-link-now-btn" id="rp4wp-link-now">Link now</a>
					<a href="<?php echo admin_url(); ?>?page=rp4wp_install&step=3" class="button">Skip linking</a>
				</p>
				<br class="clear" />
				<div id="progressbar"></div>
			<?php
			} elseif ( 3 == $cur_step ) {
				?>
				<p>That's it, you're good to go!</p>
				<p>Thanks again for using Related Posts for WordPress and if you have any questions be sure to ask them at the
					<a href="http://wordpress.org/support/plugin/related-posts-for-wp">WordPress.org forums.</a></p>
			<?php
			}
			?>
		</div>

		</div>

	<?php
	}

}