<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meeting_Reminder_Logger {
	public function log( $level, $message, $context = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'meeting_reminder_logs';

		$wpdb->insert(
			$table,
			array(
				'level'          => sanitize_key( $level ),
				'message'        => wp_strip_all_tags( $message ),
				'context'        => wp_json_encode( $context ),
				'created_at_gmt' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

	public function get_recent( $limit = 20 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'meeting_reminder_logs';
		$limit = max( 1, absint( $limit ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at_gmt DESC, id DESC LIMIT %d",
				$limit
			)
		);
	}
}
