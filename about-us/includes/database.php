<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function about_us_table_name() {
	global $wpdb;
	return $wpdb->prefix . ABOUT_US_TABLE;
}

function about_us_create_table() {
	global $wpdb;
	$table = about_us_table_name();

	$sql = "CREATE TABLE $table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		title varchar(200) NOT NULL,
		body text NOT NULL,
		PRIMARY KEY  (id)
	) " . $wpdb->get_charset_collate() . ';';

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
	if ( $count > 0 ) {
		return;
	}

	$wpdb->insert(
		$table,
		array(
			'title' => ABOUT_US_DEFAULT_TITLE,
			'body'  => ABOUT_US_DEFAULT_BODY,
		)
	);
}

function about_us_get_rows() {
	global $wpdb;
	$table = about_us_table_name();
	return $wpdb->get_results( "SELECT id, title, body FROM $table ORDER BY id" );
}

function about_us_get_row( $id ) {
	global $wpdb;
	$table = about_us_table_name();
	return $wpdb->get_row( $wpdb->prepare( "SELECT id, title, body FROM $table WHERE id = %d", $id ) );
}
