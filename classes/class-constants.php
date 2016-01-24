<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

abstract class RP4WP_Constants {

	// Link title
	const LINK_PT = 'rp4wp_link';

	// Linked meta
	const PM_PT_PARENT = 'rp4wp_pt_parent';
	const PM_PARENT = 'rp4wp_parent';
	const PM_CHILD = 'rp4wp_child';

	// Post meta
	const PM_POST_AUTO_LINKED = 'rp4wp_auto_linked'; // Posts that have automatically linked posts

	// Options
	const OPTION_DO_INSTALL = 'rp4wp_do_install';
	const OPTION_IS_INSTALLING = 'rp4wp_is_installing';

	// Nag options
	const OPTION_INSTALL_DATE = 'rp4wp_install_date';
	const OPTION_ADMIN_NOTICE_KEY = 'rp4wp_hide_nag';

	// Nonce
	const NONCE_INSTALL = 'rp4wp-install-secret';
	const NONCE_AJAX = 'rp4wp-ajax-nonce-omgrandomword';

}