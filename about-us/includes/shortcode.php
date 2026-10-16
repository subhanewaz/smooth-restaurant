<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( ABOUT_US_SHORTCODE, 'about_us_show_page' );

function about_us_show_page() {
	wp_enqueue_script(
		ABOUT_US_SCRIPT,
		ABOUT_US_URL . 'assets/about-us.js',
		array(),
		ABOUT_US_VERSION,
		true
	);

	$api_url = rest_url( ABOUT_US_API_NAMESPACE . ABOUT_US_API_ROUTE );

	return '<div class="' . esc_attr( ABOUT_US_BOX_CLASS ) . '" data-url="' . esc_url( $api_url ) . '"></div>';
}
