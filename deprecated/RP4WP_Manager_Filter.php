<?php
/**
 * The deprecated RP4WP_Manager_Filter class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;

/**
 * The 2.x filter manager. It loads 2.x filter classes by name, and hands out the object that is hooked in for a 2.x
 * filter class, so code written for 2.x can unhook it:
 *
 *     $filter = RP4WP_Manager_Filter::get_filter_object( 'RP4WP_Filter_After_Post' );
 *     remove_filter( $filter->get_tag(), array( $filter, 'run' ), $filter->get_priority() );
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks::get(), or the rp4wp_modules filter to turn a feature off.
 */
class RP4WP_Manager_Filter {

	/**
	 * The filters to load, like "after_post".
	 *
	 * @var array<int, string>
	 */
	private $filter_names = array();

	/**
	 * The filters loaded by load_filter(), by class name.
	 *
	 * @var array<string, RP4WP_Filter>
	 */
	private static $filters = array();

	/**
	 * Constructor.
	 *
	 * @param array $filter_names The filters to load, like "after_post".
	 */
	public function __construct( array $filter_names ) {
		Deprecation::class_used( __CLASS__, LegacyHooks::class );

		$this->filter_names = $filter_names;
	}

	/**
	 * Load one 2.x filter class by its short name.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param string $filter_name The short name, like "after_post".
	 *
	 * @return void
	 */
	public function load_filter( $filter_name ) {
		Deprecation::method( __METHOD__ );

		$class_name = 'RP4WP_Filter_' . str_replace( ' ', '_', ucwords( str_replace( '_', ' ', $filter_name ) ) );

		self::$filters[ $class_name ] = new $class_name();
	}

	/**
	 * Load the filters given to the constructor.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function load_filters() {
		Deprecation::method( __METHOD__ );

		foreach ( $this->filter_names as $filter_name ) {
			$this->load_filter( $filter_name );
		}
	}

	/**
	 * The object that is hooked in for a 2.x filter class.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param string $class_name The 2.x class, like RP4WP_Filter_After_Post.
	 *
	 * @return object|null An object with the methods of RP4WP_Filter; null when the class is unknown.
	 */
	public static function get_filter_object( $class_name ) {
		Deprecation::method( __METHOD__, LegacyHooks::class . '::get()' );

		if ( isset( self::$filters[ $class_name ] ) ) {
			return self::$filters[ $class_name ];
		}

		return LegacyHooks::get( (string) $class_name );
	}
}
