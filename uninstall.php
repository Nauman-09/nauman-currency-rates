<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 * Removes the saved settings and every cached rate.
 *
 * @package NaumanCurrencyRates
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'ncr_settings' );

global $wpdb;

$patterns = array(
	$wpdb->esc_like( '_transient_ncr_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_ncr_' ) . '%',
);

foreach ( $patterns as $pattern ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );
}
