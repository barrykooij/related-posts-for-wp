<?php
/**
 * The legacy action proxy class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Compat;

/**
 * Stands in for a 2.x RP4WP_Hook_* object. See LegacyHook.
 */
class LegacyAction extends LegacyHook {

	/**
	 * The callback WordPress calls: runs the real callback.
	 *
	 * @param mixed ...$args The action arguments.
	 *
	 * @return void
	 */
	public function run( ...$args ): void {
		call_user_func_array( $this->callback, $args );
	}
}
