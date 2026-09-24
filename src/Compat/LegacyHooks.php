<?php
/**
 * The legacy hooks registry class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Compat;

/**
 * Registers callbacks the way 2.x did, under the name of the 2.x hook or filter class.
 *
 * In 2.x every hook callback was an object of an RP4WP_Hook_* or RP4WP_Filter_* class, and
 * RP4WP_Manager_Hook::get_hook_object() / RP4WP_Manager_Filter::get_filter_object() handed it out for unhooking.
 * Modules that took over such a callback register it here, so those calls keep returning an object that unhooks it.
 */
class LegacyHooks {

	/**
	 * The registered proxies by 2.x class name.
	 *
	 * @var array<string, LegacyHook>
	 */
	private static array $hooks = [];

	/**
	 * Add an action under the name of its 2.x hook class.
	 *
	 * @param string   $legacy_class The 2.x class, for example RP4WP_Hook_Frontend_Css.
	 * @param string   $tag          The WordPress action.
	 * @param callable $callback     The callback.
	 * @param int      $priority     The priority.
	 * @param int      $args         The number of arguments.
	 * @param array    $methods      Other public methods of the 2.x class: name => callable.
	 *
	 * @return LegacyAction
	 */
	public static function add_action( string $legacy_class, string $tag, callable $callback, int $priority = 10, int $args = 1, array $methods = [] ): LegacyAction {
		$hook = new LegacyAction( $tag, $callback, $priority, $args );
		$hook->set_methods( $methods );
		add_action( $tag, [ $hook, 'run' ], $priority, $args );

		self::$hooks[ $legacy_class ] = $hook;

		return $hook;
	}

	/**
	 * Add a filter under the name of its 2.x filter class.
	 *
	 * @param string   $legacy_class The 2.x class, for example RP4WP_Filter_After_Post.
	 * @param string   $tag          The WordPress filter.
	 * @param callable $callback     The callback.
	 * @param int      $priority     The priority.
	 * @param int      $args         The number of arguments.
	 * @param array    $methods      Other public methods of the 2.x class: name => callable.
	 *
	 * @return LegacyFilter
	 */
	public static function add_filter( string $legacy_class, string $tag, callable $callback, int $priority = 10, int $args = 1, array $methods = [] ): LegacyFilter {
		$hook = new LegacyFilter( $tag, $callback, $priority, $args );
		$hook->set_methods( $methods );
		add_filter( $tag, [ $hook, 'run' ], $priority, $args );

		self::$hooks[ $legacy_class ] = $hook;

		return $hook;
	}

	/**
	 * The proxy registered under a 2.x class name.
	 *
	 * @param string $legacy_class The 2.x class.
	 *
	 * @return LegacyHook|null
	 */
	public static function get( string $legacy_class ): ?LegacyHook {
		return self::$hooks[ $legacy_class ] ?? null;
	}
}
