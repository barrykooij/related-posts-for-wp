<?php

class RP4WP_Hook_Post_Type extends RP4WP_Hook {
	protected $tag = 'init';

	public function run() {
		register_post_type( RP4WP_Constants::LINK_PT, array( 'public' => false, 'label' => 'Related Posts for WordPress Link' ) );
	}
}