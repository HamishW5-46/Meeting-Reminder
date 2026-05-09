<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'meeting_reminder_settings' );
wp_clear_scheduled_hook( 'meeting_reminder_run' );
wp_clear_scheduled_hook( 'meeting_autogen_cron' );

global $wpdb;

$tables = array(
	$wpdb->prefix . 'meeting_reminder_logs',
	$wpdb->prefix . 'meeting_reminder_state',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}
