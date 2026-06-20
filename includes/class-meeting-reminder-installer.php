<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meeting_Reminder_Installer {
	const CRON_HOOK = 'meeting_reminder_run';
	const AUTOGEN_CRON_HOOK = 'meeting_autogen_cron';

	public static function activate() {
		self::create_tables();
		self::register_cron();
		Meeting_Reminder_Settings::ensure_defaults();
	}

	public static function deactivate() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}

		wp_clear_scheduled_hook( self::AUTOGEN_CRON_HOOK );
	}

	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$log_table       = $wpdb->prefix . 'meeting_reminder_logs';
		$state_table     = $wpdb->prefix . 'meeting_reminder_state';

		$sql_logs = "CREATE TABLE {$log_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			level varchar(20) NOT NULL,
			message text NOT NULL,
			context longtext NULL,
			created_at_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY level (level),
			KEY created_at_gmt (created_at_gmt)
		) {$charset_collate};";

		$sql_state = "CREATE TABLE {$state_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id varchar(191) NOT NULL,
			reminder_key varchar(100) NOT NULL,
			sent_at_gmt datetime NOT NULL,
			event_start_utc datetime NULL,
			recipient_hash varchar(64) NULL,
			payload longtext NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_reminder (event_id, reminder_key),
			KEY sent_at_gmt (sent_at_gmt)
		) {$charset_collate};";

		dbDelta( $sql_logs );
		dbDelta( $sql_state );
	}

	public static function register_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}
}
