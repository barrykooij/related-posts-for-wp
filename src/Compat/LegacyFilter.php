<?php
/**
 * The legacy filter proxy class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Compat;

/**
 * Stands in for a 2.x RP4WP_Filter_* object. See LegacyHook.
 */
class LegacyFilter extends LegacyHook {

	/**
	 * The callback WordPress calls: runs the real callback and returns its result.
	 *
	 * @param mixed ...$args The filter arguments.
	 *
	 * @return mixed
	 */
	public function run( ...$args ) {
		return call_user_func_array( $this->callback, $args );
	}
}
