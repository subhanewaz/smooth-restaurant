<?php
/**
 * About Us module, loaded by smooth-restaurant.php.
 *
 * No "Plugin Name" header: this is no longer a separate plugin, it runs
 * whenever Smooth Restaurant is active.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


define( 'ABOUT_US_VERSION', '1.0.0' );
define( 'ABOUT_US_PATH', plugin_dir_path( __FILE__ ) );
define( 'ABOUT_US_URL', plugin_dir_url( __FILE__ ) );


define( 'ABOUT_US_TABLE', 'about_us' );
define( 'ABOUT_US_DEFAULT_TITLE', 'About The Restaurant' );
define( 'ABOUT_US_DEFAULT_BODY', 'Where every bite sparks a grin' );
define( 'ABOUT_US_DB_VERSION_OPTION', 'about_us_db_version' );


define( 'ABOUT_US_API_NAMESPACE', 'about-us/v1' );
define( 'ABOUT_US_API_ROUTE', '/sections' );
define( 'ABOUT_US_CAPABILITY', 'manage_options' );


define( 'ABOUT_US_SHORTCODE', 'about_us' );
define( 'ABOUT_US_SCRIPT', 'about-us' );
define( 'ABOUT_US_BOX_CLASS', 'about-us' );

require_once ABOUT_US_PATH . 'includes/database.php';
require_once ABOUT_US_PATH . 'includes/api.php';
require_once ABOUT_US_PATH . 'includes/shortcode.php';

// CLASH: register_activation_hook( __FILE__, ... ) never fires from here,
// because WordPress only runs activation hooks for the main plugin file
// (smooth-restaurant.php), and that plugin may already be active.
// register_activation_hook( __FILE__, 'about_us_create_table' );

// Instead, create the table once, the first time the plugin loads.
add_action( 'plugins_loaded', 'about_us_maybe_create_table' );

function about_us_maybe_create_table() {
	if ( get_option( ABOUT_US_DB_VERSION_OPTION ) === ABOUT_US_VERSION ) {
		return;
	}

	about_us_create_table();
	update_option( ABOUT_US_DB_VERSION_OPTION, ABOUT_US_VERSION );
}
