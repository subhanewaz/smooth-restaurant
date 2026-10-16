<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'about_us_register_api' );

function about_us_register_api() {
	register_rest_route(
		ABOUT_US_API_NAMESPACE,
		ABOUT_US_API_ROUTE,
		array(
		
			array(
				'methods'             => 'GET',
				'callback'            => 'about_us_get_rows',
				'permission_callback' => '__return_true',
			),
		
			array(
				'methods'             => 'POST',
				'callback'            => 'about_us_save_row',
				'permission_callback' => 'about_us_can_edit',
			),
		)
	);
}

function about_us_can_edit() {
	return current_user_can( ABOUT_US_CAPABILITY );
}

function about_us_save_row( $request ) {
	global $wpdb;
	$table = about_us_table_name();

	$id    = (int) $request->get_param( 'id' );
	$title = sanitize_text_field( $request->get_param( 'title' ) );
	$body  = sanitize_textarea_field( $request->get_param( 'body' ) );

	if ( '' === $title ) {
		return new WP_Error( 'missing_title', 'Please send a title.', array( 'status' => 400 ) );
	}

	if ( $id > 0 ) {
		
		if ( ! about_us_get_row( $id ) ) {
			return new WP_Error( 'not_found', 'Section not found.', array( 'status' => 404 ) );
		}

		$result = $wpdb->update(
			$table,
			array(
				'title' => $title,
				'body'  => $body,
			),
			array( 'id' => $id )
		);
	} else {
	
		$result = $wpdb->insert(
			$table,
			array(
				'title' => $title,
				'body'  => $body,
			)
		);
		$id = $wpdb->insert_id;
	}

	if ( false === $result ) {
		return new WP_Error( 'save_failed', 'Could not save the section.', array( 'status' => 500 ) );
	}

	return array(
		'id'    => $id,
		'title' => $title,
		'body'  => $body,
	);
}
