<?php
/**
 * Secure Offline CC for WooCommerce Uninstall
 *
 * Fired when the plugin is deleted.
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options
delete_option( 'woocommerce_socc_settings' );

// Delete custom DB table
global $wpdb;
$table_name = $wpdb->prefix . 'socc_audit_log';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
