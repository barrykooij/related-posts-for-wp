<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

abstract class SRP_Constants {

	// Post meta
	const PM_CHILD = 'srp_child';
	const PM_CACHED = 'srp_cached';

	// Transients
	const TRANSIENT_RELATED_QUEUE_INDEX_WORDS = 'srp_related_queue';
	const TRANSIENT_RELATED_CURRENT_INDEX_WORDS = 'srp_related_current';

}