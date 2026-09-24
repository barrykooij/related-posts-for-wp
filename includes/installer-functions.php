<?php
/**
 * The activation callback. Kept as a named function, registered with register_activation_hook().
 *
 * @package RelatedPostsForWP
 */

/**
 * Create the word cache table and start the installation wizard.
 *
 * Activation runs before this plugin's plugins_loaded callback, so load the autoloader here.
 *
 * @return void
 */
function rp4wp_activate_plugin() {
	require_once dirname( __DIR__ ) . '/vendor/autoload.php';

	\LV2\WordPress\RelatedPostsForWP\Install\Installer::activate();
}
