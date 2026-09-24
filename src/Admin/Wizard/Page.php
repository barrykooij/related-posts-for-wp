<?php
/**
 * The installation wizard page class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\Wizard;

use LV2\WordPress\RelatedPostsForWP\Admin\MetaBox\Ajax as MetaBoxAjax;
use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Links\LinkPostType;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Module;
use LV2\WordPress\RelatedPostsForWP\PostTypes;
use LV2\WordPress\RelatedPostsForWP\Related\Finder;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;

/**
 * The installation wizard: 1. cache the words of every post, 2. link posts, 3. done.
 *
 * Steps 1 and 2 run in the browser, calling the AJAX actions in batches until nothing is left (see Ajax).
 */
class Page implements Module {

	/**
	 * The admin page slug.
	 */
	public const SLUG = 'rp4wp_install';

	/**
	 * The nonce action of the wizard links.
	 */
	public const NONCE = 'rp4wp-install-secret';

	/**
	 * Option set while the wizard is not finished, so the admin can resume it.
	 */
	public const OPTION_IS_INSTALLING = 'rp4wp_is_installing';

	/**
	 * Register the hidden admin page.
	 *
	 * @return void
	 */
	public static function setup(): void {
		LegacyHooks::add_action(
			'RP4WP_Hook_Page_Install',
			'admin_menu',
			[ self::class, 'register' ],
			10,
			1,
			[
				'enqueue_install_assets' => [ self::class, 'enqueue_assets' ],
				'content'                => [ self::class, 'render' ],
			]
		);
	}

	/**
	 * The URL of the wizard, with a fresh nonce.
	 *
	 * @param array<string, string|int> $args Extra query arguments.
	 *
	 * @return string
	 */
	public static function url( array $args = [] ): string {
		return admin_url() . '?' . http_build_query( array_merge( [ 'page' => self::SLUG ], $args, [ 'rp4wp_nonce' => wp_create_nonce( self::NONCE ) ] ) );
	}

	/**
	 * Register the page, without a menu entry, for admins.
	 *
	 * @return void
	 */
	public static function register(): void {
		$hook = add_submenu_page( '', 'RP4WPINSTALL', 'RP4WPINSTALL', 'manage_options', self::SLUG, [ self::class, 'render' ] );

		add_action( 'load-' . $hook, [ self::class, 'enqueue_assets' ] );
	}

	/**
	 * The wizard's scripts and styles.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		global $wp_scripts;

		wp_enqueue_style( 'rp4wp-install-css', plugins_url( '/assets/css/install.css', Main::file() ), [], Main::VERSION );
		wp_enqueue_script( 'rp4wp-install-js', plugins_url( '/assets/js/install' . ( SCRIPT_DEBUG ? '' : '.min' ) . '.js', Main::file() ), [ 'jquery', 'jquery-ui-core', 'jquery-ui-progressbar' ], Main::VERSION, false );
		// The jQuery UI theme still comes from Google's CDN, like 2.x (known issue K13).
		wp_enqueue_style( 'jquery-ui-smoothness', '//ajax.googleapis.com/ajax/libs/jqueryui/' . $wp_scripts->query( 'jquery-ui-core' )->ver . '/themes/smoothness/jquery-ui.css', [], null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The version is in the URL.
	}

	/**
	 * The page.
	 *
	 * @return void
	 */
	public static function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The nonce is verified right below.
		$nonce = isset( $_GET['rp4wp_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['rp4wp_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_die( 'Woah! It looks like something else tried to run the Related Posts for WordPress installation wizard! We were able to stop them, nothing was lost. Please report this incident at <a href="http://wordpress.org/support/plugin/related-posts-for-wp" target="_blank">our forums.</a>' );
		}

		if ( isset( $_GET['reinstall'] ) ) {
			self::remove_links_and_words();
		}

		$steps = [
			1 => __( 'Caching Posts', 'related-posts-for-wp' ),
			2 => __( 'Linking Posts', 'related-posts-for-wp' ),
			3 => __( 'Finished', 'related-posts-for-wp' ),
		];

		$step = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;
		// phpcs:enable
		if ( ! isset( $steps[ $step ] ) ) {
			$step = 1;
		}

		// Remember that the wizard is running, so it can be resumed.
		if ( 1 === $step ) {
			add_option( self::OPTION_IS_INSTALLING, true );
		} elseif ( 3 === $step ) {
			delete_option( self::OPTION_IS_INSTALLING );
		}

		$total_posts = 0;
		foreach ( PostTypes::supported() as $post_type ) {
			$total_posts += intval( wp_count_posts( $post_type )->publish );
		}
		?>
		<div class="wrap">
			<h2>Related Posts for WordPress <?php esc_html_e( 'Installation', 'related-posts-for-wp' ); ?></h2>

			<ul class="install-steps">
				<?php
				foreach ( $steps as $number => $label ) {
					echo "<li id='step-bar-" . (int) $number . "'" . ( ( $step === $number ) ? " class='step-bar-active'" : '' ) . '><span>' . (int) $number . '. ' . esc_html( $label ) . '</span></li>' . PHP_EOL;
				}
				?>
			</ul>
			<br class="clear"/>

			<h3><?php echo esc_html( $steps[ $step ] ); ?></h3>

			<div class='rp4wp-step rp4wp-step-<?php echo (int) $step; ?>' rel='<?php echo (int) $step; ?>'>
				<?php
				echo "<input type='hidden' id='rp4wp_total_posts' value='" . (int) $total_posts . "' />" . PHP_EOL;
				echo "<input type='hidden' id='rp4wp_admin_url' value='" . esc_attr( admin_url() ) . "' />" . PHP_EOL;

				if ( '' !== $nonce ) {
					echo "<input type='hidden' id='rp4wp_nonce' value='" . esc_attr( $nonce ) . "' />" . PHP_EOL;
				}

				echo '<input type="hidden" name="rp4wp-ajax-nonce" id="rp4wp-ajax-nonce" value="' . esc_attr( wp_create_nonce( MetaBoxAjax::NONCE ) ) . '" />';

				if ( 1 === $step ) {
					self::render_caching_step();
				} elseif ( 2 === $step ) {
					self::render_linking_step();
				} else {
					self::render_finished_step();
				}
				?>

				<div class="rp4wp-box rp4wp-box-upgrade-black">
					<h3 class="rp4wp-title"><?php esc_html_e( 'Related Posts for WordPress Premium', 'related-posts-for-wp' ); ?></h3>

					<p><?php esc_html_e( 'This plugin has an even better premium version, I am sure you will love it.', 'related-posts-for-wp' ); ?></p>

					<p><?php esc_html_e( 'Premium features include custom post type support, related post themes, custom taxonomy support and priority support.', 'related-posts-for-wp' ); ?></p>

					<p>
					<?php
					/* translators: 1: link opening tag, 2: link closing tag */
					printf( esc_html__( '%sMore information about Related Posts for WP Premium (opens in new window) »%s', 'related-posts-for-wp' ), '<a href="https://www.relatedpostsforwp.com/upgrade-premium/?utm_source=plugin&utm_medium=link&utm_campaign=install" target="_blank">', '</a>' ); // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText -- The 2.x string; translations exist for it.
					?>
					</p>
				</div>

			</div>

		</div>
		<?php
	}

	/**
	 * Step 1: caching the words of every post.
	 *
	 * @return void
	 */
	private static function render_caching_step(): void {
		$todo = ( new Cache() )->uncached_post_count();

		echo "<input type='hidden' id='rp4wp_posts_todo' value='" . (int) $todo . "' />" . PHP_EOL;
		?>
		<p><?php esc_html_e( 'Thank you for choosing Related Posts for WordPress!', 'related-posts-for-wp' ); ?></p>
		<p><?php esc_html_e( 'Before you can start using Related Posts for WordPress we need to cache your current posts.', 'related-posts-for-wp' ); ?></p>
		<p><?php esc_html_e( "This is a one time process which might take some time now, depending on the amount of posts you have, but will ensure your website's performance when using the plugin.", 'related-posts-for-wp' ); ?></p>

		<p style="font-weight: bold;"><?php esc_html_e( 'Do NOT close this window, wait for this process to finish and this wizard to take you to the next step.', 'related-posts-for-wp' ); ?></p>

		<div id="progress-container">
			<div id="progressbar"></div>
			<p>Todo: <span id="progress-todo"><?php echo (int) $todo; ?></span></p>
			<p>Done: <span id="progress-done">0</span></p>
		</div>
		<?php
	}

	/**
	 * Step 2: linking posts.
	 *
	 * @return void
	 */
	private static function render_linking_step(): void {
		$todo = ( new Finder() )->unlinked_post_count();

		echo "<input type='hidden' id='rp4wp_posts_todo' value='" . (int) $todo . "' />" . PHP_EOL;
		?>
		<p style="font-weight: bold;"><?php esc_html_e( 'Great! All your posts were successfully cached!', 'related-posts-for-wp' ); ?></p>
		<p><?php esc_html_e( "You can let me link your posts, based on what I think is related, to each other. And don't worry, if I made a mistake at one of your posts you can easily correct this by editing it manually!", 'related-posts-for-wp' ); ?></p>
		<p><?php esc_html_e( 'Want me to start linking posts to each other? Fill in the amount of related posts each post should have and click on the "Link now" button. Rather link your posts manually? Click "Skip linking".', 'related-posts-for-wp' ); ?></p>
		<p style="font-weight: bold;"><?php esc_html_e( 'Do NOT close this window if you click the "Link now" button, wait for this process to finish and this wizard to take you to the next step.', 'related-posts-for-wp' ); ?></p>
		<br class="clear"/>
		<p class="rp4wp-install-link-box">
			<label for="rp4wp_related_posts_amount"><?php esc_html_e( 'Amount of related posts per post:', 'related-posts-for-wp' ); ?></label><input class="form-input-tip" type="text" id="rp4wp_related_posts_amount" value="<?php echo esc_attr( (string) Main::get()->settings()->get( 'automatic_linking_post_amount' ) ); ?>"/>
			<a href="javascript:;" class="button button-primary button-large rp4wp-link-now-btn" id="rp4wp-link-now"><?php esc_html_e( 'Link now', 'related-posts-for-wp' ); ?></a>
			<a href="<?php echo esc_url( admin_url( sprintf( '?page=rp4wp_install&step=3&rp4wp_nonce=%s', wp_create_nonce( self::NONCE ) ) ) ); ?>" class="button"><?php esc_html_e( 'Skip linking', 'related-posts-for-wp' ); ?></a>
		</p>
		<br class="clear"/>

		<div id="progress-container">
			<div id="progressbar"></div>
			<p>Todo: <span id="progress-todo"><?php echo (int) $todo; ?></span></p>
			<p>Done: <span id="progress-done">0</span></p>
		</div>
		<?php
	}

	/**
	 * Step 3: done.
	 *
	 * @return void
	 */
	private static function render_finished_step(): void {
		?>
		<p><?php esc_html_e( "That's it, you're good to go!", 'related-posts-for-wp' ); ?></p>
		<p>
		<?php
		/* translators: 1: link opening tag, 2: link closing tag */
		printf( esc_html__( 'Thanks again for using Related Posts for WordPress and if you have any questions be sure to ask them at the %sWordPress.org forums.%s', 'related-posts-for-wp' ), '<a href="http://wordpress.org/support/plugin/related-posts-for-wp" target="_blank">', '</a>' ); // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText -- The 2.x string; translations exist for it.
		?>
		</p>
		<?php
	}

	/**
	 * Remove every link, the "linked automatically" flags and the word cache, to start the wizard over.
	 *
	 * @return void
	 */
	private static function remove_links_and_words(): void {
		global $wpdb;

		$link_ids = get_posts(
			[
				'post_type'      => LinkPostType::POST_TYPE,
				'fields'         => 'ids',
				'posts_per_page' => -1,
			]
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Bulk removal, like 2.x; the placeholders are built from the ID count.
		if ( count( $link_ids ) > 0 ) {
			$placeholders = implode( ',', array_fill( 0, count( $link_ids ), '%d' ) );

			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->posts} WHERE `ID` IN ({$placeholders})", $link_ids ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE `post_id` IN ({$placeholders})", $link_ids ) );
		}

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE `meta_key` IN ( 'rp4wp_auto_linked', 'rp4wp_cached', %s )", Cache::META_NO_WORDS ) );
		$wpdb->query( 'DELETE FROM ' . Table::name() . ' WHERE 1=1' );
		// phpcs:enable
	}
}
