<?php
/**
 * The settings page class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\Settings;

use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * The Settings > Related Posts screen: a tab per setting section, and a sidebar.
 */
class Page implements Module {

	/**
	 * The admin page slug, which is also the Settings API page and option group.
	 */
	public const SLUG = 'rp4wp';

	/**
	 * Add the page to the Settings menu.
	 *
	 * @return void
	 */
	public static function setup(): void {
		LegacyHooks::add_action(
			'RP4WP_Hook_Settings_Page',
			'admin_menu',
			[ self::class, 'register' ],
			10,
			1,
			[
				'enqueue_assets' => [ self::class, 'enqueue_assets' ],
				'screen'         => [ self::class, 'render' ],
			]
		);
	}

	/**
	 * Register the page, and its assets for when it loads.
	 *
	 * @return void
	 */
	public static function register(): void {
		$hook = add_submenu_page( 'options-general.php', __( 'Related Posts', 'related-posts-for-wp' ), __( 'Related Posts', 'related-posts-for-wp' ), 'manage_options', self::SLUG, [ self::class, 'render' ] );

		if ( false !== $hook ) {
			add_action( 'load-' . $hook, [ self::class, 'enqueue_assets' ] );
		}
	}

	/**
	 * The page styles.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		wp_enqueue_style( 'rp4wp-settings-css', plugins_url( '/assets/css/settings.css', Main::file() ), [], Main::VERSION );
	}

	/**
	 * The page. Each section is a tab; assets/js/settings.js switches between them.
	 *
	 * @return void
	 */
	public static function render(): void {
		global $wp_settings_sections, $wp_settings_fields;
		?>
		<div class="wrap">
			<h2>Related Posts for WordPress</h2>

			<div class="rp4wp-content">
				<form method="post" action="options.php" id="rp4wp-settings-form">
					<?php
					settings_fields( self::SLUG );

					if ( isset( $wp_settings_sections[ self::SLUG ] ) ) {
						echo '<h2 class="nav-tab-wrapper">';
						foreach ( (array) $wp_settings_sections[ self::SLUG ] as $section ) {
							echo '<a href="#rp4wp-settings-' . esc_attr( $section['id'] ) . '" class="nav-tab">' . wp_kses_post( $section['title'] ) . '</a>';
						}
						echo '</h2>' . PHP_EOL;

						foreach ( (array) $wp_settings_sections[ self::SLUG ] as $section ) {
							echo '<div id="rp4wp-settings-' . esc_attr( $section['id'] ) . '" class="rp4wp-settings-section">';

							if ( $section['title'] ) {
								echo '<h3>' . wp_kses_post( $section['title'] ) . "</h3>\n";
							}

							if ( $section['callback'] ) {
								call_user_func( $section['callback'], $section );
							}

							if ( isset( $wp_settings_fields[ self::SLUG ][ $section['id'] ] ) ) {
								echo '<table class="form-table">';
								do_settings_fields( self::SLUG, $section['id'] );
								echo '</table>';
							}

							echo '</div>';
						}
					}

					submit_button();
					?>
				</form>
			</div>
			<?php self::sidebar(); ?>
		</div>
		<?php
	}

	/**
	 * The sidebar: premium, support and the people behind the plugin.
	 *
	 * The strings are the 2.x strings, because translations exist for them.
	 *
	 * @return void
	 */
	private static function sidebar(): void {
		?>
		<div class="rp4wp-sidebar">

			<div class="rp4wp-box rp4wp-box-upgrade-black">
				<h3><?php esc_html_e( 'Related Posts for WordPress Premium', 'related-posts-for-wp' ); ?></h3>

				<p><?php esc_html_e( 'This plugin has an even better premium version, I am sure you will love it.', 'related-posts-for-wp' ); ?></p>

				<p><?php esc_html_e( 'Premium features include:', 'related-posts-for-wp' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Full control over your post display with our configurator', 'related-posts-for-wp' ); ?></li>
					<li><?php esc_html_e( 'Related Custom Post Types & Taxonomies to each other', 'related-posts-for-wp' ); ?></li>
					<li><?php esc_html_e( 'Ability to Exclude posts from being related', 'related-posts-for-wp' ); ?></li>
					<li><?php esc_html_e( 'Keep Manually created links', 'related-posts-for-wp' ); ?></li>
					<li><?php esc_html_e( 'Define What you find related by setting weights', 'related-posts-for-wp' ); ?></li>
					<li><?php esc_html_e( 'Top notch priority Email support', 'related-posts-for-wp' ); ?></li>
				</ul>

				<p><?php esc_html_e( 'And more features, click the button below to get a full overview including a demo video!', 'related-posts-for-wp' ); ?></p>

				<p>
				<?php
				/* translators: 1: link opening tag, 2: link closing tag */
				printf( esc_html__( '%sView All Premium Features%s', 'related-posts-for-wp' ), '<a class="button button-primary button-large" href="https://www.relatedpostsforwp.com/upgrade-premium/?utm_source=plugin&utm_medium=link&utm_campaign=upgrade-box" target="_blank">', '</a>' ); // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText -- The 2.x string; translations exist for it.
				?>
				</p>
			</div>

			<div class="rp4wp-box">
				<h3 class="rp4wp-title"><?php esc_html_e( 'Can we help you?', 'related-posts-for-wp' ); ?></h3>

				<p>
				<?php
				/* translators: 1: documentation link opening tag, 2: link closing tag, 3: forum link opening tag, 4: link closing tag */
				printf( esc_html__( "We've covered a lot of general questions in our %sdocumentation%s, is your question not covered there? Feel free to open a thread at our %sWordPress.org forum%s.", 'related-posts-for-wp' ), '<a href="https://www.relatedpostsforwp.com/documentation/" target="_blank">', '</a>', '<a href="http://wordpress.org/support/plugin/related-posts-for-wp" target="_blank">', '</a>' ); // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText -- The 2.x string; translations exist for it.
				?>
				</p>

				<p>
				<?php
				/* translators: 1: link opening tag, 2: link closing tag */
				printf( esc_html__( 'Did you know our %sPremium customers%s get priority email support?', 'related-posts-for-wp' ), '<a href="https://www.relatedpostsforwp.com/upgrade-premium/?utm_source=plugin&utm_medium=link&utm_campaign=support" target="_blank">', '</a>' ); // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText -- The 2.x string; translations exist for it.
				?>
				</p>

			</div>

			<div class="rp4wp-box">
				<h3 class="rp4wp-title"><?php esc_html_e( 'Want to help us?', 'related-posts-for-wp' ); ?></h3>

				<p>
				<?php
				/* translators: 1: link opening tag, 2: link closing tag */
				printf( esc_html__( '%sUpgrade to Related Posts for WordPress Premium%s', 'related-posts-for-wp' ), '<a href="https://www.relatedpostsforwp.com/upgrade-premium/?utm_source=plugin&utm_medium=link&utm_campaign=help-us" target="_blank">', '</a>' ); // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText -- The 2.x string; translations exist for it.
				?>
				</p>

				<p>
				<?php
				/* translators: 1: link opening tag, 2: link closing tag */
				printf( esc_html__( '%sLeave a ★★★★★ plugin review on WordPress.org%s', 'related-posts-for-wp' ), '<a href="http://wordpress.org/support/view/plugin-reviews/related-posts-for-wp?rate=5#postform" target="_blank">', '</a>' ); // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText -- The 2.x string; translations exist for it.
				?>
				</p>

				<p>
				<?php
				/* translators: 1: link opening tag, 2: link closing tag */
				printf( esc_html__( '%sTweet about Related Posts for WordPress%s', 'related-posts-for-wp' ), '<a href="https://twitter.com/intent/tweet?text=Showing%20my%20appreciation%20to%20%40CageNL%20for%20his%20WordPress%20plugin%3A%20Related%20Posts%20for%20WordPress%20-%20check%20it%20out!%20http%3A%2F%2Fwordpress.org%2Fplugins%2Frelated-posts-for-wp%2F" target="_blank">', '</a>' ); // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText -- The 2.x string; translations exist for it.
				?>
				</p>

				<p>
				<?php
				/* translators: 1: link opening tag, 2: link closing tag */
				printf( esc_html__( "%sVote 'works' on the WordPress.org plugin page%s", 'related-posts-for-wp' ), '<a href="http://wordpress.org/plugins/related-posts-for-wp/" target="_blank">', '</a>' ); // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText -- The 2.x string; translations exist for it.
				?>
				</p>

				<p><a href="http://www.never5.com/" target="_blank"><?php esc_html_e( 'Check out our other plugins at Never5.com', 'related-posts-for-wp' ); ?></a></p>

			</div>

			<div class="rp4wp-box">
				<h3 class="rp4wp-title"><?php esc_html_e( 'About Never5', 'related-posts-for-wp' ); ?></h3>

				<a href="http://www.never5.com" target="_blank"><img src="<?php echo esc_url( plugins_url( '/assets/images/never5-logo.png', Main::file() ) ); ?>" alt="Never5" style="float:left;padding:0 10px 10px 0;" /></a>

				<p>
				<?php
				/* translators: 1: link opening tag, 2: link closing tag */
				printf( esc_html__( 'At %sNever5%s we create high quality premium WordPress plugins, with extensive support. We offer solutions in related posts, advanced download management, vehicle management and connecting post types.', 'related-posts-for-wp' ), '<a href="http://www.never5.com" target="_blank">', '</a>' ); // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText -- The 2.x string; translations exist for it.
				?>
				</p>

				<p>
				<?php
				/* translators: 1: link opening tag, 2: link closing tag */
				printf( esc_html__( '%sFollow Never5 on Twitter%s', 'related-posts-for-wp' ), '<a href="https://twitter.com/Never5Plugins" target="_blank">', '</a>' ); // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText -- The 2.x string; translations exist for it.
				?>
				</p>
			</div>

		</div>
		<?php
	}
}
