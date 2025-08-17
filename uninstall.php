<?php
/**
 * Uninstall for LLM Bot Tracker
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Drop custom table and delete options created by this plugin.
global $wpdb;
$table = $wpdb->prefix . 'wpcs_hits';
$requests_table = $wpdb->prefix . 'wpcs_requests';

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Table drop on uninstall
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Table drop on uninstall
$wpdb->query( "DROP TABLE IF EXISTS {$requests_table}" );

delete_option( 'wpcs_db_version' );


