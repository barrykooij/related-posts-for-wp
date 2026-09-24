<?php
/**
 * The settings class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Settings;

/**
 * The plugin settings: their definitions, defaults and values.
 *
 * Values live in the `rp4wp` option. `rp4wp_options` filters all values, and `rp4wp_{option}` filters one.
 */
class Settings {

	/**
	 * The option that holds the settings.
	 */
	public const OPTION = 'rp4wp';

	/**
	 * Nonce action of the installation wizard.
	 */
	private const NONCE_INSTALL = 'rp4wp-install-secret';

	/**
	 * The setting sections with their fields, once built.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private ?array $sections = null;

	/**
	 * The default value of every field.
	 *
	 * @var array<string, mixed>
	 */
	private array $defaults = [];

	/**
	 * The setting sections with their fields, filterable through `rp4wp_settings_sections`.
	 *
	 * Built on first use. The labels are translated, so do not call this before init.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function sections(): array {
		if ( null === $this->sections ) {
			/**
			 * Filters the settings sections and their fields.
			 *
			 * @since 1.1.0
			 *
			 * @param array $sections The sections.
			 */
			$this->sections = (array) apply_filters( 'rp4wp_settings_sections', $this->default_sections() );

			$this->defaults = [];
			foreach ( $this->sections as $section ) {
				foreach ( $section['fields'] as $field ) {
					$this->defaults[ $field['id'] ] = $field['default'];
				}
			}
		}

		return $this->sections;
	}

	/**
	 * The default value of every field.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		$this->sections();

		return $this->defaults;
	}

	/**
	 * All settings: the stored values over the defaults, filterable through `rp4wp_options`.
	 *
	 * @return mixed Normally an array; a filter may return something else.
	 */
	public function get_options() {
		/**
		 * Filters all settings.
		 *
		 * @since 1.1.0
		 *
		 * @param array $options The settings.
		 */
		return apply_filters( 'rp4wp_options', wp_parse_args( get_option( self::OPTION, [] ), $this->defaults() ) );
	}

	/**
	 * One setting, filterable through `rp4wp_{$option}`.
	 *
	 * @param string $option The setting.
	 *
	 * @return mixed The value; false when it does not exist.
	 */
	public function get( string $option ) {
		$options = $this->get_options();

		/**
		 * Filters one setting.
		 *
		 * @since 1.0.0
		 *
		 * @param mixed $value The value.
		 */
		return apply_filters( 'rp4wp_' . $option, isset( $options[ $option ] ) ? $options[ $option ] : false );
	}

	/**
	 * Sanitize the settings posted from the settings page.
	 *
	 * @param mixed $post_data The posted settings.
	 *
	 * @return mixed
	 */
	public function sanitize( $post_data ) {
		// Unchecked checkboxes are not posted.
		if ( ! isset( $post_data['automatic_linking'] ) ) {
			$post_data['automatic_linking'] = 0;
		}

		if ( ! isset( $post_data['display_image'] ) ) {
			$post_data['display_image'] = 0;
		}

		$post_data['automatic_linking']             = intval( $post_data['automatic_linking'] );
		$post_data['automatic_linking_post_amount'] = intval( $post_data['automatic_linking_post_amount'] );
		$post_data['excerpt_length']                = intval( $post_data['excerpt_length'] );

		return $post_data;
	}

	/**
	 * The sections and fields of 2.x.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function default_sections(): array {
		return [
			'general' => [
				'id'          => 'general',
				'label'       => __( 'General', 'related-posts-for-wp' ),
				'description' => __( 'The following options affect the general behaviour of the plugin.', 'related-posts-for-wp' ),
				'fields'      => [
					'automatic_linking'             => [
						'id'          => 'automatic_linking',
						'label'       => __( 'Enable', 'related-posts-for-wp' ),
						'description' => __( 'Checking this will enable automatically linking posts to new posts', 'related-posts-for-wp' ),
						'type'        => 'checkbox',
						'default'     => 1,
					],
					'automatic_linking_post_amount' => [
						'id'          => 'automatic_linking_post_amount',
						'label'       => __( 'Amount of Posts', 'related-posts-for-wp' ),
						'description' => __( 'The amount of automatically linked post', 'related-posts-for-wp' ),
						'type'        => 'text',
						'default'     => '3',
					],
					'heading_text'                  => [
						'id'          => 'heading_text',
						'label'       => __( 'Heading text', 'related-posts-for-wp' ),
						'description' => __( 'The text that is displayed above the related posts. To disable, leave field empty.', 'related-posts-for-wp' ),
						'type'        => 'text',
						'default'     => __( 'Related Posts', 'related-posts-for-wp' ),
					],
					'excerpt_length'                => [
						'id'          => 'excerpt_length',
						'label'       => __( 'Excerpt length', 'related-posts-for-wp' ),
						'description' => __( 'The amount of words to be displayed below the title on website. To disable, set value to 0.', 'related-posts-for-wp' ),
						'type'        => 'text',
						'default'     => '15',
					],
				],
			],
			'styling' => [
				'id'          => 'styling',
				'label'       => __( 'Styling', 'related-posts-for-wp' ),
				'description' => __( 'The following options affect how related posts are displayed on the frontend.', 'related-posts-for-wp' ),
				'fields'      => [
					'display_image' => [
						'id'          => 'display_image',
						'label'       => __( 'Display Image', 'related-posts-for-wp' ),
						'description' => __( 'Checking this will enable displaying featured images of related posts.', 'related-posts-for-wp' ),
						'type'        => 'checkbox',
						'default'     => 0,
					],
					'css'           => [
						'id'          => 'css',
						'label'       => __( 'CSS', 'related-posts-for-wp' ),
						'description' => __( 'Warning! This is an advanced feature! An error here will break frontend display. To disable, leave field empty.', 'related-posts-for-wp' ),
						'type'        => 'textarea',
						'default'     => $this->default_css(),
					],
				],
			],
			'misc'    => [
				'id'          => 'misc',
				'label'       => __( 'Misc', 'related-posts-for-wp' ),
				'description' => __( "A shelter for options that just don't fit in anywhere else.", 'related-posts-for-wp' ),
				'fields'      => [
					'restart_wizard_button' => [
						'id'          => 'restart_wizard_button',
						'label'       => __( 'Rebuild posts linkage?', 'related-posts-for-wp' ),
						'description' => __( "Click this button if you want to restart the wizard. Please note that this will delete all current related post links, also those you've manually added. Of course, we will never delete your actual posts.", 'related-posts-for-wp' ),
						'type'        => 'button_link',
						'href'        => admin_url( '?page=rp4wp_install&reinstall=1&rp4wp_nonce=' . wp_create_nonce( self::NONCE_INSTALL ) ),
						'default'     => __( 'Rebuild', 'related-posts-for-wp' ),
					],
					'clean_on_uninstall'    => [
						'id'          => 'clean_on_uninstall',
						'label'       => __( 'Remove Data on Uninstall?', 'related-posts-for-wp' ),
						'description' => __( 'Check this box if you would like to completely remove all of its data when the plugin is deleted.', 'related-posts-for-wp' ),
						'type'        => 'checkbox',
						'default'     => 0,
					],
					'show_love'             => [
						'id'          => 'show_love',
						'label'       => __( 'Show love?', 'related-posts-for-wp' ),
						'description' => __( "Display a 'Powered by' line under your related posts. <strong>BEWARE! Only for the real fans.</strong>", 'related-posts-for-wp' ),
						'type'        => 'checkbox',
						'default'     => 0,
					],
				],
			],
		];
	}

	/**
	 * The default front-end CSS, mirrored for right-to-left languages.
	 *
	 * @return string
	 */
	private function default_css(): string {
		if ( is_rtl() ) {
			$lines = [
				'.rp4wp-related-posts ul{width:100%;padding:0;margin:0;float:right;}',
				'.rp4wp-related-posts ul>li{list-style:none;padding:0;margin:0;padding-bottom:20px;float:right;}',
				'.rp4wp-related-posts ul>li>p{margin:0;padding:0;}',
				'.rp4wp-related-post-image{width:35%;padding-left:25px;-moz-box-sizing:border-box;-webkit-box-sizing:border-box;box-sizing:border-box;float:right;}',
			];
		} else {
			$lines = [
				'.rp4wp-related-posts ul{width:100%;padding:0;margin:0;float:left;}',
				'.rp4wp-related-posts ul>li{list-style:none;padding:0;margin:0;padding-bottom:20px;clear:both;}',
				'.rp4wp-related-posts ul>li>p{margin:0;padding:0;}',
				'.rp4wp-related-post-image{width:35%;padding-right:25px;-moz-box-sizing:border-box;-webkit-box-sizing:border-box;box-sizing:border-box;float:left;}',
			];
		}

		return implode( PHP_EOL, $lines );
	}
}
