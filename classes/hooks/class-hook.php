<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

abstract class RP4WP_Hook {
	protected $tag = null;
	protected $priority = 10;
	protected $args = 1;

	/**
	 * Construct method. Set tag and register hook.
	 *
	 * @access public
	 *
	 * @param mixed $tag (default: null)
	 *
	 * @return void
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

}