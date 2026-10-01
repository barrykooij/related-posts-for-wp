<?php
/**
 * The settings REST test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Rest;

/**
 * Reading and saving the settings through the REST API.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Rest\SettingsController
 * @covers \LV2\WordPress\RelatedPostsForWP\Settings\Schema\Pages
 * @covers \LV2\WordPress\RelatedPostsForWP\Settings\Schema\Field
 * @covers \LV2\WordPress\RelatedPostsForWP\Settings\Controller
 */
final class SettingsRestTest extends RestTestCase {

	public function set_up(): void {
		parent::set_up();

		$this->act_as( 'administrator' );
		delete_option( 'rp4wp' );
	}

	public function test_every_section_is_a_page_with_its_fields_and_values(): void {
		$pages = $this->request( 'GET', '/settings' )->get_data()['pages'];

		$this->assertSame( [ 'general', 'styling', 'misc' ], array_column( $pages, 'id' ) );
		$this->assertSame( 'rp4wp', $pages[0]['option'] );

		$fields = array_column( $pages[0]['sections'][0]['fields'], null, 'id' );
		$this->assertSame( 'toggle', $fields['automatic_linking']['type'] );
		$this->assertSame( 'number', $fields['automatic_linking_post_amount']['type'] );
		$this->assertSame( 1, $fields['automatic_linking_post_amount']['min'] );
		$this->assertSame( 50, $fields['automatic_linking_post_amount']['max'] );
		$this->assertSame( 'text', $fields['heading_text']['type'] );
		$this->assertFalse( $fields['heading_text']['filtered'] );

		$this->assertSame( 'code', array_column( $pages[1]['sections'][0]['fields'], 'type', 'id' )['css'] );

		$this->assertSame( [ 'clean_on_uninstall', 'show_love' ], array_column( $pages[2]['sections'][0]['fields'], 'id' ), 'The rebuild link moved to the installer.' );

		// The values as the app edits them: booleans and numbers, with the defaults for what was never saved.
		$values = (array) $pages[0]['values'];
		$this->assertTrue( $values['automatic_linking'] );
		$this->assertSame( 3, $values['automatic_linking_post_amount'] );
		$this->assertSame( 'Related Posts', $values['heading_text'] );
	}

	public function test_saving_a_page_stores_its_fields_the_way_the_settings_screen_did(): void {
		update_option( 'rp4wp', [ 'display_image' => 1 ] );

		$response = $this->request(
			'PUT',
			'/settings/general',
			[
				'values' => [
					'automatic_linking' => false,
					'heading_text'      => 'You might also like',
					'excerpt_length'    => 0,
				],
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['values']->automatic_linking );

		$stored = get_option( 'rp4wp' );
		$this->assertSame( 0, $stored['automatic_linking'] );
		$this->assertSame( 'You might also like', $stored['heading_text'] );
		$this->assertSame( 0, $stored['excerpt_length'] );
		$this->assertSame( 3, $stored['automatic_linking_post_amount'], 'A field that was not sent keeps its value.' );
		$this->assertSame( 1, $stored['display_image'], 'Fields of other pages keep their value.' );
	}

	public function test_saving_runs_the_sanitize_callback_of_the_option(): void {
		$sanitized = 0;
		add_filter(
			'sanitize_option_rp4wp',
			static function ( $value ) use ( &$sanitized ) {
				++$sanitized;

				return $value;
			}
		);

		$this->request( 'PUT', '/settings/styling', [ 'values' => [ 'display_image' => true ] ] );

		$this->assertGreaterThan( 0, $sanitized );
		$this->assertSame( 1, get_option( 'rp4wp' )['display_image'] );
		$this->assertSame( 1, get_option( 'rp4wp' )['automatic_linking'], 'The callback turns a missing checkbox off; none is missing.' );
	}

	/**
	 * Values that are not valid.
	 *
	 * @return array<string, array{array<string, mixed>}>
	 */
	public function data_invalid_values(): array {
		return [
			'an unknown field'           => [ [ 'not_a_field' => 'x' ] ],
			'a field of another page'    => [ [ 'css' => 'x' ] ],
			'text for a number'          => [ [ 'excerpt_length' => 'ten' ] ],
			'too few related posts'      => [ [ 'automatic_linking_post_amount' => 0 ] ],
			'too many related posts'     => [ [ 'automatic_linking_post_amount' => 51 ] ],
			'a number for a checkbox'    => [ [ 'automatic_linking' => 'yes please' ] ],
		];
	}

	/**
	 * Invalid values are rejected with 400, and nothing is saved.
	 *
	 * @dataProvider data_invalid_values
	 *
	 * @param array<string, mixed> $values The values.
	 */
	public function test_invalid_values_are_rejected( array $values ): void {
		$response = $this->request( 'PUT', '/settings/general', [ 'values' => $values ] );

		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( get_option( 'rp4wp' ) );
	}

	public function test_a_page_that_does_not_exist_is_not_found(): void {
		$this->assertSame( 404, $this->request( 'GET', '/settings/nope' )->get_status() );
		$this->assertSame( 404, $this->request( 'PUT', '/settings/nope', [ 'values' => [] ] )->get_status() );
	}

	public function test_a_field_that_a_filter_decides_is_marked_and_not_saved(): void {
		add_filter(
			'rp4wp_heading_text',
			static function () {
				return 'From a filter';
			}
		);
		$this->fresh_settings();

		$fields = array_column( $this->request( 'GET', '/settings' )->get_data()['pages'][0]['sections'][0]['fields'], null, 'id' );
		$this->assertTrue( $fields['heading_text']['filtered'] );

		$response = $this->request( 'PUT', '/settings/general', [ 'values' => [ 'heading_text' => 'Mine' ] ] );

		$this->assertSame( 'From a filter', $response->get_data()['values']->heading_text );
		$this->assertSame( 'Related Posts', get_option( 'rp4wp' )['heading_text'] );
	}

	public function test_a_link_added_by_a_filter_is_described_and_never_saved(): void {
		add_filter(
			'rp4wp_settings_sections',
			static function ( array $sections ) {
				$sections['misc']['fields']['docs'] = [
					'id'      => 'docs',
					'label'   => 'Documentation',
					'type'    => 'button_link',
					'href'    => 'https://example.com/docs',
					'default' => 'Read the docs',
				];

				return $sections;
			}
		);
		$this->fresh_settings();

		$page   = $this->request( 'GET', '/settings' )->get_data()['pages'][2];
		$fields = array_column( $page['sections'][0]['fields'], null, 'id' );
		$this->assertSame( 'link', $fields['docs']['type'] );
		$this->assertSame( 'https://example.com/docs', $fields['docs']['href'] );
		$this->assertArrayNotHasKey( 'docs', (array) $page['values'] );

		$this->assertSame( 400, $this->request( 'PUT', '/settings/misc', [ 'values' => [ 'docs' => 'x' ] ] )->get_status() );
	}

	public function test_fields_added_by_a_filter_are_described_and_saved(): void {
		add_filter(
			'rp4wp_settings_sections',
			static function ( array $sections ) {
				$sections['styling']['fields']['accent'] = [
					'id'          => 'accent',
					'label'       => 'Accent',
					'description' => 'The colour. <script>alert(1)</script><strong>Bold</strong>',
					'type'        => 'colorpicker',
					'default'     => '#000000',
				];

				return $sections;
			}
		);
		$this->fresh_settings();

		$fields = array_column( $this->request( 'GET', '/settings' )->get_data()['pages'][1]['sections'][0]['fields'], null, 'id' );
		$this->assertSame( 'text', $fields['accent']['type'], 'A type the app does not know is a text field.' );
		$this->assertSame( 'The colour. alert(1)<strong>Bold</strong>', $fields['accent']['description'] );

		$this->request( 'PUT', '/settings/styling', [ 'values' => [ 'accent' => '#ff0000' ] ] );
		$this->assertSame( '#ff0000', get_option( 'rp4wp' )['accent'] );
	}
}
