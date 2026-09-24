<?php
/**
 * The deprecated RP4WP_Manager_Hook class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;

/**
 * The 2.x hook manager. It loads 2.x hook classes by name, and hands out the object that is hooked in for a 2.x
 * hook class, so code written for 2.x can unhook it:
 *
 *     $hook = RP4WP_Manager_Hook::get_hook_object( 'RP4WP_Hook_Frontend_Css' );
 *     remove_action( $hook->get_tag(), array( $hook, 'run' ), $hook->get_priority() );
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks::get(), or the rp4wp_modules filter to turn a feature off.
 */
class RP4WP_Manager_Hook {

	/**
	 * The hooks to load, like "frontend_css".
	 *
	 * @var array<int, string>
	 */
	private $action_names = array();

	/**
	 * The hooks loaded by load_hook(), by class name.
	 *
	 * @var array<string, RP4WP_Hook>
	 */
	private static $hooks = array();

	/**
	 * Constructor.
	 *
	 * @param array $action_names The hooks to load, like "frontend_css".
	 */
	public function __construct( array $action_names ) {
		Deprecation::class_used( __CLASS__, LegacyHooks::class );

		$this->action_names = $action_names;
	}

	/**
	 * Load one 2.x hook class by its short name.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param string $action_name The short name, like "frontend_css".
	 *
	 * @return void
	 */
	public function load_hook( $action_name ) {
		Deprecation::method( __METHOD__ );

		$class_name = 'RP4WP_Hook_' . str_replace( ' ', '_', ucwords( str_replace( '_', ' ', $action_name ) ) );

		self::$hooks[ $class_name ] = new $class_name();
	}

	/**
	 * Load the hooks given to the constructor.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function load_hooks() {
		Deprecation::method( __METHOD__ );

		foreach ( $this->action_names as $action_name ) {
			$this->load_hook( $action_name );
		}
	}

	/**
	 * The object that is hooked in for a 2.x hook class.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param string $class_name The 2.x class, like RP4WP_Hook_Frontend_Css.
	 *
	 * @return object|null An object with the methods of RP4WP_Hook; null when the class is unknown.
	 */
	public static function get_hook_object( $class_name ) {
		Deprecation::method( __METHOD__, LegacyHooks::class . '::get()' );

		if ( isset( self::$hooks[ $class_name ] ) ) {
			return self::$hooks[ $class_name ];
		}

		return LegacyHooks::get( (string) $class_name );
	}
}
