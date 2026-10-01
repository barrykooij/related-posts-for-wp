<?php
/**
 * The settings page test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Admin;

use LV2\WordPress\RelatedPostsForWP\Admin\Settings\Fields;
use LV2\WordPress\RelatedPostsForWP\Admin\Settings\Page;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Settings\Settings;
use LV2\WordPress\RelatedPostsForWP\Admin\App\Assets;
use LV2\WordPress\RelatedPostsForWP\Admin\Wizard\Page as WizardPage;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;
use LV2\WordPress\RelatedPostsForWP\Tests\Support\AdminAppBuild;

/**
 * The Settings > Related Posts screen, which holds the admin app, and the settings it registers with the Settings API
 * for code that still uses it.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Admin\Settings\Page
 * @covers \LV2\WordPress\RelatedPostsForWP\Admin\Settings\Fields
 * @covers \LV2\WordPress\RelatedPostsForWP\Admin\Wizard\Page
 * @covers \LV2\WordPress\RelatedPostsForWP\Admin\Wizard\Redirect
 */
final class SettingsPageTest extends TestCase {

	use AdminAppBuild;

	/**
	 * Globals the admin menu and the Settings API write to; restored after every test.
	 */
	private const GLOBALS = [
		'submenu',
		'_wp_submenu_nopriv',
		'_registered_pages',
		'_parent_pages',
		'wp_settings_sections',
		'wp_settings_fields',
		'wp_registered_settings',
		'new_allowed_options',
	];

	/**
	 * The globals before the test.
	 *
	 * @var array<string, mixed>
	 */
	private array $backup = [];

	/**
	 * The settings service before the test.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		foreach ( self::GLOBALS as $name ) {
			$this->backup[ $name ] = $GLOBALS[ $name ] ?? null;
		}

		$this->act_as( 'administrator' );

		// The sections are built once, on init. Build them again, so tests can filter them.
		$this->settings = Main::get()->settings();
		Main::get()->set_settings( new Settings() );
	}

	public function tear_down(): void {
		foreach ( $this->backup as $name => $value ) {
			$GLOBALS[ $name ] = $value; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores what the test changed.
		}

		Main::get()->set_settings( $this->settings );

		wp_deregister_script( Assets::SCRIPT );
		wp_deregister_style( Assets::STYLE );
		$this->remove_admin_app_build_stand_in();

		parent::tear_down();
	}

	public function test_administrators_get_the_page_in_the_settings_menu(): void {
		global $submenu;

		Page::register();

		$pages = array_values(
			array_filter(
				(array) ( $submenu['options-general.php'] ?? [] ),
				static function ( $item ) {
					return Page::SLUG === $item[2];
				}
			)
		);

		$this->assertCount( 1, $pages );
		$this->assertSame( [ 'Related Posts', 'manage_options', 'rp4wp', 'Related Posts' ], array_slice( $pages[0], 0, 4 ) );

		$hook = get_plugin_page_hookname( Page::SLUG, 'options-general.php' );
		$this->assertSame( 10, has_action( $hook, [ Page::class, 'render' ] ) );
		$this->assertSame( 10, has_action( 'load-' . $hook, [ Page::class, 'enqueue_assets' ] ) );
	}

	public function test_other_users_do_not_get_the_page(): void {
		global $submenu;

		$this->act_as( 'editor' );
		Page::register();

		$this->assertNotContains( Page::SLUG, wp_list_pluck( (array) ( $submenu['options-general.php'] ?? [] ), 2 ) );
		$this->assertFalse( has_action( 'load-' . get_plugin_page_hookname( Page::SLUG, 'options-general.php' ) ) );
	}

	public function test_the_page_loads_the_admin_app(): void {
		$this->stand_in_for_admin_app_build();

		Page::enqueue_assets();

		$this->assertTrue( wp_script_is( Assets::SCRIPT ) );
		$this->assertTrue( wp_style_is( Assets::STYLE ) );
	}

	public function test_the_page_only_holds_the_element_the_app_mounts_on(): void {
		ob_start();
		Page::render();
		$html = (string) ob_get_clean();

		$this->assertSame( '<div class="wrap"><div id="rp4wp-admin"></div><noscript><p>The settings of Related Posts for WordPress need JavaScript.</p></noscript></div>', $html );
	}

	public function test_the_page_links_to_a_screen_of_the_app(): void {
		$this->assertSame( admin_url( 'options-general.php?page=rp4wp' ), Page::url() );
		$this->assertSame( admin_url( 'options-general.php?page=rp4wp#/setup' ), Page::url( 'setup' ) );
	}

	public function test_the_address_of_the_2x_wizard_sends_admins_to_the_installer(): void {
		$this->assertSame( Page::url( 'setup' ), WizardPage::target() );

		// The 2.x "Rebuild" link removed everything right away; now it only opens the installer, which asks first.
		$_GET['reinstall'] = '1';
		$this->assertSame( Page::url( 'installer' ), WizardPage::target() );

		// Premium links the posts on its Post types tab.
		$tab = static function () {
			return 'post-types';
		};
		add_filter( 'rp4wp_linking_route', $tab );
		$this->assertSame( Page::url( 'post-types' ), WizardPage::target() );
		remove_filter( 'rp4wp_linking_route', $tab );
		unset( $_GET['reinstall'] );
	}

	public function test_every_section_and_field_is_registered(): void {
		global $wp_settings_sections, $wp_settings_fields, $new_allowed_options;

		Fields::register();

		$sections = Main::get()->settings()->sections();
		$this->assertSame( [ 'general', 'styling', 'misc' ], array_keys( $wp_settings_sections[ Page::SLUG ] ) );

		foreach ( $sections as $id => $section ) {
			$this->assertSame( $section['label'], $wp_settings_sections[ Page::SLUG ][ $id ]['title'] );
			$this->assertSame( array_keys( $section['fields'] ), array_keys( $wp_settings_fields[ Page::SLUG ][ $id ] ) );
		}

		// options.php only saves options that are allowed for the posted option group.
		$this->assertArrayHasKey( Settings::OPTION, get_registered_settings() );
		$this->assertSame( [ Settings::OPTION ], $new_allowed_options[ Page::SLUG ] );
	}

	public function test_saving_the_page_sanitizes_the_settings(): void {
		Fields::register();

		update_option(
			Settings::OPTION,
			[
				'automatic_linking_post_amount' => '5 posts',
				'excerpt_length'                => '20',
				'heading_text'                  => 'Read more',
			]
		);

		$this->assertSame(
			[
				'automatic_linking_post_amount' => 5,
				'excerpt_length'                => 20,
				'heading_text'                  => 'Read more',
				// Unchecked checkboxes are not posted; they are saved as off.
				'automatic_linking'             => 0,
				'display_image'                 => 0,
			],
			get_option( Settings::OPTION )
		);
	}

	public function test_the_2x_fields_still_render_for_code_that_uses_the_settings_api(): void {
		$html = $this->render();

		$this->assertStringContainsString( '<input type="checkbox" name="rp4wp[automatic_linking]" id="automatic_linking" value="1"  checked=\'checked\' />', $html );
		$this->assertStringContainsString( '<input type="checkbox" name="rp4wp[display_image]" id="display_image" value="1"  />', $html );
		$this->assertStringContainsString( '<input type="text" name="rp4wp[heading_text]" id="heading_text" value="Related Posts" class="rp4wp-input-text" />', $html );
		$this->assertStringContainsString( '<textarea name="rp4wp[css]" id="css">.rp4wp-related-posts ul{', $html );
		$this->assertStringContainsString( '<label class="rp4wp-description" for="excerpt_length">The amount of words to be displayed below the title on website. To disable, set value to 0.</label>', $html );
		$this->assertStringNotContainsString( 'This option is overwritten by a filter.', $html );
		$this->assertStringNotContainsString( 'restart_wizard_button', $html, 'The rebuild link moved to the installer of the app.' );
	}

	public function test_an_option_set_by_a_filter_is_marked(): void {
		add_filter(
			'rp4wp_heading_text',
			static function () {
				return 'Set by a filter';
			}
		);

		$html = $this->render();

		$this->assertStringContainsString( 'id="heading_text" value="Set by a filter"', $html );
		$this->assertSame( 1, substr_count( $html, 'This option is overwritten by a filter.' ) );
	}

	public function test_descriptions_keep_their_markup_but_not_scripts(): void {
		add_filter(
			'rp4wp_settings_sections',
			static function ( $sections ) {
				$sections['general']['description']                           = 'Section <em>intro</em><script>alert(1)</script>';
				$sections['general']['fields']['heading_text']['description'] = 'Field <strong>help</strong><script>alert(2)</script>';

				return $sections;
			}
		);

		$html = $this->render();

		$this->assertStringContainsString( '<p>Section <em>intro</em>alert(1)</p>', $html );
		$this->assertStringContainsString( '<label class="rp4wp-description" for="heading_text">Field <strong>help</strong>alert(2)</label>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * Register the settings and render their sections the way the 2.x screen did, with the Settings API.
	 *
	 * @return string
	 */
	private function render(): string {
		Fields::register();

		ob_start();
		do_settings_sections( Page::SLUG );

		return (string) ob_get_clean();
	}
}
