<?php
/**
 * The translations test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration;

use LV2\WordPress\RelatedPostsForWP\TextDomain;

/**
 * The translations in the plugin are complete for its version, so they come first; a language pack from
 * translate.wordpress.org, which WordPress would load instead of them, only adds what they lack.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\TextDomain
 */
final class TranslationsTest extends TestCase {

	/**
	 * The language pack this test writes, if any.
	 *
	 * @var string
	 */
	private string $pack = '';

	public function set_up(): void {
		parent::set_up();

		add_filter( 'determine_locale', [ self::class, 'dutch' ] );
		unload_textdomain( TextDomain::DOMAIN );
	}

	public function tear_down(): void {
		if ( '' !== $this->pack && file_exists( $this->pack ) ) {
			unlink( $this->pack ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test cleanup.
		}

		remove_filter( 'determine_locale', [ self::class, 'dutch' ] );
		unload_textdomain( TextDomain::DOMAIN );

		parent::tear_down();
	}

	/**
	 * The Dutch locale, for the determine_locale filter.
	 *
	 * @return string
	 */
	public static function dutch(): string {
		return 'nl_NL';
	}

	public function test_the_translations_in_the_plugin_are_loaded(): void {
		TextDomain::load();

		// A string that is new in 3.0.
		$this->assertSame( 'Opnieuw proberen', __( 'Try again', 'related-posts-for-wp' ) ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Checking the translation itself.
	}

	public function test_a_language_pack_only_adds_what_the_translations_in_the_plugin_lack(): void {
		$this->pack = $this->write_pack(
			[
				'Try again'                 => 'Probeer het nog eens',
				'A string only in the pack' => 'Alleen in het pakket',
			]
		);

		TextDomain::load();

		$this->assertSame( 'Opnieuw proberen', __( 'Try again', 'related-posts-for-wp' ), 'The plugin has it: its translation wins.' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Checking the translation itself.
		$this->assertSame( 'Alleen in het pakket', __( 'A string only in the pack', 'related-posts-for-wp' ), 'The plugin lacks it: the pack adds it.' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Checking the translation itself.
	}

	public function test_after_a_switch_to_another_locale_the_plugin_comes_first_again(): void {
		$this->pack = $this->write_pack( [ 'Try again' => 'Probeer het nog eens' ] );

		TextDomain::reload( 'nl_NL' );

		$this->assertSame( 'Opnieuw proberen', __( 'Try again', 'related-posts-for-wp' ) ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Checking the translation itself.
		$this->assertSame( 10, has_action( 'change_locale', [ TextDomain::class, 'reload' ] ) );
	}

	/**
	 * Write a Dutch language pack where WordPress looks for one.
	 *
	 * @param array<string, string> $translations The translations, by original.
	 *
	 * @return string The file.
	 */
	private function write_pack( array $translations ): string {
		$directory = WP_LANG_DIR . '/plugins';
		wp_mkdir_p( $directory );

		$mo = new \MO();
		foreach ( $translations as $original => $translation ) {
			$mo->add_entry(
				new \Translation_Entry(
					[
						'singular'     => $original,
						'translations' => [ $translation ],
					]
				)
			);
		}

		$file = $directory . '/' . TextDomain::DOMAIN . '-nl_NL.mo';
		$this->assertTrue( $mo->export_to_file( $file ) );

		return $file;
	}
}
