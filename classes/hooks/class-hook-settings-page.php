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
		wp_enqueue_style( 'rp4wp-settings-css', plugins_url( '/assets/css/settings.css', RP4WP::get_plugin_file() ), array(), RP4WP::VERSION );
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

			<div class="rp4wp-box rp4wp-box-upgrade-black">
				<h3><?php _e( 'Related Posts for WordPress Premium', 'related-posts-for-wp' ); ?></h3>

				<p><?php _e( 'This plugin has an even better premium version, I am sure you will love it.', 'related-posts-for-wp' ); ?></p>

				<p><?php _e( 'Premium features include:', 'related-posts-for-wp' ); ?></p>
				<ul>
					<li><?php _e( 'Full control over your post display with our configurator', 'related-posts-for-wp' ); ?></li>
					<li><?php _e( 'Related Custom Post Types & Taxonomies to each other', 'related-posts-for-wp' ); ?></li>
					<li><?php _e( 'Ability to Exclude posts from being related', 'related-posts-for-wp' ); ?></li>
					<li><?php _e( 'Keep Manually created links', 'related-posts-for-wp' ); ?></li>
					<li><?php _e( 'Define What you find related by setting weights', 'related-posts-for-wp' ); ?></li>
					<li><?php _e( 'Top notch priority Email support', 'related-posts-for-wp' ); ?></li>
				</ul>

				<p><?php _e( 'And more features, click the button below to get a full overview including a demo video!', 'related-posts-for-wp' ); ?></p>

				<p><?php printf( __( '%sView All Premium Features%s', 'related-posts-for-wp' ), '<a class="button button-primary button-large" href="https://www.relatedpostsforwp.com/upgrade-premium/?utm_source=plugin&utm_medium=link&utm_campaign=upgrade-box" target="_blank">', '</a>' ); ?></p>
			</div>

			<div class="rp4wp-box">
				<h3 class="rp4wp-title"><?php _e( 'Can we help you?', 'related-posts-for-wp' ); ?></h3>

				<p><?php printf( __( "We've covered a lot of general questions in our %sdocumentation%s, is your question not covered there? Feel free to open a thread at our %sWordPress.org forum%s.", 'related-posts-for-wp' ), '<a href="https://www.relatedpostsforwp.com/documentation/" target="_blank">', '</a>', '<a href="http://wordpress.org/support/plugin/related-posts-for-wp" target="_blank">', '</a>' ); ?></p>

				<p><?php printf( __( "Did you know our %sPremium customers%s get priority email support?", 'related-posts-for-wp' ), '<a href="https://www.relatedpostsforwp.com/upgrade-premium/?utm_source=plugin&utm_medium=link&utm_campaign=support" target="_blank">', '</a>' ); ?></p>

			</div>

			<div class="rp4wp-box">
				<h3 class="rp4wp-title"><?php _e( 'Want to help us?', 'related-posts-for-wp' ); ?></h3>

				<p><?php printf( __( "%sUpgrade to Related Posts for WordPress Premium%s", 'related-posts-for-wp' ), '<a href="https://www.relatedpostsforwp.com/upgrade-premium/?utm_source=plugin&utm_medium=link&utm_campaign=help-us" target="_blank">', '</a>' ); ?></p>

				<p><?php printf( __( "%sLeave a ★★★★★ plugin review on WordPress.org%s", 'related-posts-for-wp' ), '<a href="http://wordpress.org/support/view/plugin-reviews/related-posts-for-wp?rate=5#postform" target="_blank">', '</a>' ); ?></p>

				<p><?php printf( __( "%sTweet about Related Posts for WordPress%s", 'related-posts-for-wp' ), '<a href="https://twitter.com/intent/tweet?text=Showing%20my%20appreciation%20to%20%40CageNL%20for%20his%20WordPress%20plugin%3A%20Related%20Posts%20for%20WordPress%20-%20check%20it%20out!%20http%3A%2F%2Fwordpress.org%2Fplugins%2Frelated-posts-for-wp%2F" target="_blank">', '</a>' ); ?></p>

				<p><?php printf( __( "%sVote 'works' on the WordPress.org plugin page%s", 'related-posts-for-wp' ), '<a href="http://wordpress.org/plugins/related-posts-for-wp/" target="_blank">', '</a>' ); ?></p>

				<p><a href="http://www.never5.com/" target="_blank"><?php _e( "Check out our other plugins at Never5.com", 'related-posts-for-wp' ); ?></a></p>

			</div>

			<div class="rp4wp-box">
				<h3 class="rp4wp-title"><?php _e( 'About Never5', 'related-posts-for-wp' ); ?></h3>

				<a href="http://www.never5.com" target="_blank"><img src="<?php echo plugins_url( '/assets/images/never5-logo.png', RP4WP::get_plugin_file() ); ?>" alt="Never5" style="float:left;padding:0 10px 10px 0;" /></a>

				<p><?php printf( __( 'At %sNever5%s we create high quality premium WordPress plugins, with extensive support. We offer solutions in related posts, advanced download management, vehicle management and connecting post types.', 'related-posts-for-wp'), '<a href="http://www.never5.com" target="_blank">', '</a>' ); ?></p>

				<p><?php printf( __( "%sFollow Never5 on Twitter%s", 'related-posts-for-wp' ), '<a href="https://twitter.com/Never5Plugins" target="_blank">', '</a>' ); ?></p>
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
		global $wp_settings_sections, $wp_settings_fields;
		?>
		<div class="wrap">
			<h2>Related Posts for WordPress</h2>

			<div class="rp4wp-content">
				<form method="post" action="options.php" id="rp4wp-settings-form">
					<?php
					//pass slug name of page, also referred  to in Settings API as option group name
					settings_fields( 'rp4wp' );

					//do_settings_sections( 'rp4wp' );    //pass slug name of page

					if ( isset( $wp_settings_sections['rp4wp'] ) ) {

						echo '<h2 class="nav-tab-wrapper">';
						foreach ( (array) $wp_settings_sections['rp4wp'] as $section ) {
							//nav-tab-active
							echo '<a href="#rp4wp-settings-' . $section['id'] . '" class="nav-tab">' . $section['title'] . '</a>';
						}
						echo '</h2>' . PHP_EOL;
						?>
						<?php


						foreach ( (array) $wp_settings_sections['rp4wp'] as $section ) {

							echo '<div id="rp4wp-settings-' . $section['id'] . '" class="rp4wp-settings-section">';

							if ( $section['title'] ) {
								echo "<h3>{$section['title']}</h3>\n";
							}

							if ( $section['callback'] ) {
								call_user_func( $section['callback'], $section );
							}

							if ( isset( $wp_settings_fields ) && isset( $wp_settings_fields['rp4wp'] ) && isset( $wp_settings_fields['rp4wp'][ $section['id'] ] ) ) {
								echo '<table class="form-table">';
								do_settings_fields( 'rp4wp', $section['id'] );
								echo '</table>';

							}

							echo '</div>';
						}


					}

					// submit button
					submit_button();
					?>
				</form>
			</div>
			<?php $this->sidebar(); ?>
		</div>
		<?php
	}
}