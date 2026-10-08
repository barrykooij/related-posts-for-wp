<?php
/**
 * The settings class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Settings;

use LV2\WordPress\RelatedPostsForWP\Install\Planner;

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
	 * The default CSS of 2.x, for left-to-right and right-to-left sites. Saving the settings stored it, so most sites
	 * that never touched it hold one of these; they get the current default instead (D32).
	 */
	public const LEGACY_DEFAULT_CSS = [
		'ltr' => '.rp4wp-related-posts ul{width:100%;padding:0;margin:0;float:left;}
.rp4wp-related-posts ul>li{list-style:none;padding:0;margin:0;padding-bottom:20px;clear:both;}
.rp4wp-related-posts ul>li>p{margin:0;padding:0;}
.rp4wp-related-post-image{width:35%;padding-right:25px;-moz-box-sizing:border-box;-webkit-box-sizing:border-box;box-sizing:border-box;float:left;}',
		'rtl' => '.rp4wp-related-posts ul{width:100%;padding:0;margin:0;float:right;}
.rp4wp-related-posts ul>li{list-style:none;padding:0;margin:0;padding-bottom:20px;float:right;}
.rp4wp-related-posts ul>li>p{margin:0;padding:0;}
.rp4wp-related-post-image{width:35%;padding-left:25px;-moz-box-sizing:border-box;-webkit-box-sizing:border-box;box-sizing:border-box;float:right;}',
	];

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
		$options = wp_parse_args( get_option( self::OPTION, [] ), $this->defaults() );

		if ( isset( $options['css'] ) && is_string( $options['css'] ) && self::is_legacy_default_css( $options['css'] ) && ! self::is_styled_elsewhere() ) {
			$options['css'] = $this->defaults()['css'];
		}

		return apply_filters( 'rp4wp_options', $options );
	}

	/**
	 * Whether CSS is one of the 2.x defaults. Whitespace does not count: browsers post textareas with `\r\n`.
	 *
	 * @param string $css The CSS.
	 *
	 * @return bool
	 */
	public static function is_legacy_default_css( string $css ): bool {
		$css = (string) preg_replace( '/\s+/', '', $css );

		foreach ( self::LEGACY_DEFAULT_CSS as $legacy ) {
			if ( (string) preg_replace( '/\s+/', '', $legacy ) === $css ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the Additional CSS of the Customizer styles the related posts. Such a site was styled on top of the 2.x
	 * default, so it keeps that default (D34).
	 *
	 * @return bool
	 */
	public static function is_styled_elsewhere(): bool {
		return false !== stripos( (string) wp_get_custom_css(), 'rp4wp' );
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
		if ( ! is_array( $post_data ) ) {
			return $post_data;
		}

		// Unchecked checkboxes are not posted.
		if ( ! isset( $post_data['automatic_linking'] ) ) {
			$post_data['automatic_linking'] = 0;
		}

		if ( ! isset( $post_data['display_image'] ) ) {
			$post_data['display_image'] = 0;
		}

		$post_data['automatic_linking'] = intval( $post_data['automatic_linking'] );

		// Code that saves part of the option (the REST API registers this callback too) may leave these out.
		foreach ( [ 'automatic_linking_post_amount', 'excerpt_length' ] as $number ) {
			if ( isset( $post_data[ $number ] ) ) {
				$post_data[ $number ] = intval( $post_data[ $number ] );
			}
		}

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
						'ui'          => 'number',
						'min'         => 1,
						'max'         => Planner::max_amount(),
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
						'ui'          => 'number',
						'min'         => 0,
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
						'description' => __( 'Warning! This is an advanced feature! An error here will break frontend display. To disable, leave field empty.', 'related-posts-for-wp' ) . ' ' . sprintf(
							/* translators: 1: the custom property for the space between posts, 2: the custom property for the image width */
							__( 'To change only the space between posts or the image width, set %1$s or %2$s in your theme instead.', 'related-posts-for-wp' ),
							'<code>--rp4wp-gap</code>',
							'<code>--rp4wp-image-width</code>'
						),
						'type'        => 'textarea',
						'ui'          => 'code',
						'default'     => $this->default_css(),
					],
				],
			],
			'misc'    => [
				'id'          => 'misc',
				'label'       => __( 'Misc', 'related-posts-for-wp' ),
				'description' => __( "A shelter for options that just don't fit in anywhere else.", 'related-posts-for-wp' ),
				'fields'      => [
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
	 * The default front-end CSS: a flexbox row per post, with the image on the side where the text starts, so it suits
	 * right-to-left languages too. The custom properties let a theme change the gap and the image width.
	 *
	 * @return string
	 */
	private function default_css(): string {
		$lines = [
			'.rp4wp-related-posts ul{display:grid;gap:var(--rp4wp-gap,1.25rem);margin:0;padding:0;list-style:none;}',
			'.rp4wp-related-posts li{display:flex;gap:var(--rp4wp-gap,1.25rem);align-items:flex-start;margin:0;padding:0;}',
			'.rp4wp-related-post-image{flex:0 0 var(--rp4wp-image-width,35%);}',
			'.rp4wp-related-post-image img{display:block;max-width:100%;height:auto;}',
			'.rp4wp-related-post-content{flex:1;min-width:0;}',
		];

		return implode( PHP_EOL, $lines );
	}
}
