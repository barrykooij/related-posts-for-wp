<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

abstract class RP4WP_Constants {

	// Link title
	const LINK_PT = 'rp4wp_link';

	// Linked meta
	const PM_PARENT = 'rp4wp_parent';
	const PM_CHILD = 'rp4wp_child';

	// Post meta
	const PM_CACHED = 'rp4wp_cached'; // Posts that words are saved of
	const PM_POST_AUTO_LINKED = 'rp4wp_auto_linked'; // Posts that have automatically linked posts

	// Options
	const OPTION_DO_INSTALL = 'rp4wp_do_install';

	// Nag options
	const OPTION_INSTALL_DATE = 'rp4wp-install-date';
	const OPTION_ADMIN_NOTICE_KEY = 'rp4wp-hide-nag';


}