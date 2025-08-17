<?php
/**
 * Uninstall for LLM Bot Tracker
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Drop custom table and delete options created by this plugin.
global $wpdb;
$table = $wpdb->prefix . 'wpcs_hits';
$requests_table = $wpdb->prefix . 'wpcs_requests';

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is built from core prefix and known suffix.
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is built from core prefix and known suffix.
$wpdb->query( "DROP TABLE IF EXISTS {$requests_table}" );

delete_option( 'wpcs_db_version' );


