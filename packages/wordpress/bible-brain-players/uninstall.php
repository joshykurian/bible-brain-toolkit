<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'bbplay_settings' );
delete_option( 'bbp_settings' ); // name used by early test builds
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_bbplay\_%' OR option_name LIKE '\_transient\_timeout\_bbplay\_%'" );
