<?php

class SRP_Hook_Post_Type extends SRP_Hook {
	protected $tag = 'init';

	public function run() {
		register_post_type( SRP_Constants::LINK_PT, array( 'public' => false, 'label' => 'Simple Related Posts Link' ) );
	}
}