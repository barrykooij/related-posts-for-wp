<?php
/**
 * The deprecated RP4WP_Hook class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * The 2.x base class of the plugin's hooks. Code that extends it for its own hooks keeps working.
 *
 * @deprecated 3.0.0 Use add_action() directly.
 */
abstract class RP4WP_Hook {

	/**
	 * The action.
	 *
	 * @var string|null
	 */
	protected $tag = null;

	/**
	 * The priority.
	 *
	 * @var int
	 */
	protected $priority = 10;

	/**
	 * The number of arguments.
	 *
	 * @var int
	 */
	protected $args = 1;

	/**
	 * Constructor. Adds run() to the action.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, 'add_action()' );

		$this->attach_legacy_hook();
	}

	/**
	 * Add run() to the action.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function register() {
		Deprecation::method( __METHOD__, 'add_action()' );

		$this->attach_legacy_hook();
	}

	/**
	 * The number of arguments.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return int
	 */
	public function get_args() {
		Deprecation::method( __METHOD__ );

		return $this->args;
	}

	/**
	 * The priority.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return int
	 */
	public function get_priority() {
		Deprecation::method( __METHOD__ );

		return $this->priority;
	}

	/**
	 * The action.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return string|null
	 */
	public function get_tag() {
		Deprecation::method( __METHOD__ );

		return $this->tag;
	}

	/**
	 * Add run() to the action. 2.x stopped with a fatal error when a subclass had no tag; this reports it and
	 * adds nothing.
	 *
	 * @internal
	 *
	 * @return void
	 */
	protected function attach_legacy_hook() {
		if ( null === $this->tag ) {
			_doing_it_wrong( esc_html( static::class ), 'A hook needs a tag.', '3.0.0' );

			return;
		}

		add_action( $this->tag, array( $this, 'run' ), $this->priority, $this->args );
	}
}
