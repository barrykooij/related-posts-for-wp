<?php
/**
 * The module interface file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP;

/**
 * A part of the plugin that registers its WordPress hooks. Main::setup() calls setup() on every module in order.
 */
interface Module {

	/**
	 * Register the module's hooks. Must not do work beyond that.
	 *
	 * @return void
	 */
	public static function setup(): void;
}
