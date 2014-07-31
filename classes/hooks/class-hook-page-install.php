<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class SRP_Hook_Page_Install extends SRP_Hook {
	protected $tag = 'admin_menu';

	public function run() {

		$menu_hook = add_submenu_page( null, 'SRPInstallation', 'SRPInstallation', 'edit_posts', 'srp_install', array( $this, 'content' ) );

		add_action( 'load-' . $menu_hook, array( $this, 'enqueue_install_assets' ) );
	}

	public function enqueue_install_assets() {
		global $wp_scripts;
		wp_enqueue_style( 'srp-install-css', plugins_url( '/assets/css/install.css', Simple_Related_Posts::get_plugin_file() ) );
		wp_enqueue_script( 'srp-install-js', plugins_url( '/assets/js/install.js', Simple_Related_Posts::get_plugin_file() ), array( 'jquery', 'jquery-ui-core', 'jquery-ui-progressbar' ) );
		wp_enqueue_style( 'jquery-ui-smoothness', "http://ajax.googleapis.com/ajax/libs/jqueryui/" . $wp_scripts->query( 'jquery-ui-core' )->ver . "/themes/smoothness/jquery-ui.css", false, null );
	}

	/**
	 * The screen content
	 */
	public function content() {

		?>
		<div class="wrap">
			<h2><?php _e( 'Simple Related Posts Installation', 'simple-related-posts' ); ?></h2>

			<ul class="install_steps">
				<li>Caching posts</li>
				<li>Linking posts</li>
				<li>Done</li>
			</ul>
			<br class="clear" />

			<?php
			$step = isset( $_GET['step'] ) ? $_GET['step'] : 1;
			echo "<div class='step' rel='" . $step . "'>";
			if ( 1 == $step ) {
				echo "<input type='hidden' id='sre_total_posts' value='" . wp_count_posts( 'post' )->publish . "' />" . PHP_EOL;
				?>
				<p>Thank you for choosing Simple Related Posts!<br /><br />Before you can start using Simple Related Posts we need to cache your current posts.<br />This is a one time process which might take some time now, depending on the amount of posts you have, but will ensure your website's performance when using the plugin.
				</p>

				<p style="font-weight: bold;">Do NOT close this window, wait for this process to finish and this wizard to take you to the next step.</p>

				<div id="progressbar"></div>
			<?php
			} elseif ( 2 == $step ) {

			} elseif ( 3 == $step ) {

			}
			?>
		</div>

		</div>

	<?php
	}

}