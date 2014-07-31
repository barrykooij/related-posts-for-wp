<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

abstract class SRP_Constants {

//	const LINK_TITLE = 'srp_link';
	const LINK_PT = 'srp_link';

	// Post meta
	const PM_PARENT = 'srp_parent';
	const PM_CHILD = 'srp_child';
	const PM_CACHED = 'srp_cached';
	const PM_AUTO_LINKED = 'srp_auto_linked';

	// Options
	const OPTION_DO_INSTALL = 'srp_do_install';

}