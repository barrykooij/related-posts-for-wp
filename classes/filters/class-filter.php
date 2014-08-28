<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

abstract class RP4WP_Filter {
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
			trigger_error( 'ERROR IN FILTER: NO TAG SET', E_USER_ERROR );
		}

		add_filter( $this->tag, array( $this, 'run' ), $this->priority, $this->args );
	}

	/**
	 * Get the args
	 *
	 * @return int
	 */
	public function get_args() {
		return $this->args;
	}

	/**
	 * Get the priority
	 *
	 * @return int
	 */
	public function get_priority() {
		return $this->priority;
	}

	/**
	 * Get the tag
	 *
	 * @return string
	 */
	public function get_tag() {
		return $this->tag;
	}



}