<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

abstract class RP4WP_Hook {
	protected $tag = null;
	protected $priority = 10;
	protected $args = 1;

	/**
	 * Construct method. Set tag and register hook.
	 *
	 * @access public
	 */
	public function __construct() {
		$this->register();
	}

	/**
	 * Register the hook.
	 *
	 * @access public
	 * @return void
	 */
	public function register() {
		// Tag must be set
		if ( $this->tag === null ) {
			trigger_error( 'ERROR IN HOOK: NO TAG SET', E_USER_ERROR );
		}

		add_action( $this->tag, array( $this, 'run' ), $this->priority, $this->args );
	}

	/**
	 * Get args
	 *
	 * @return int
	 */
	public function get_args() {
		return $this->args;
	}

	/**
	 * Get priority
	 *
	 * @return int
	 */
	public function get_priority() {
		return $this->priority;
	}

	/**
	 * Get tag
	 *
	 * @return string
	 */
	public function get_tag() {
		return $this->tag;
	}


}