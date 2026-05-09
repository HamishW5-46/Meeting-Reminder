<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meeting_Reminder_State {
	public function has_sent( $event_id, $reminder_key ) {
		global $wpdb;

		$table = $wpdb->prefix . 'meeting_reminder_state';
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE event_id = %s AND reminder_key = %s LIMIT 1",
				$event_id,
				$reminder_key
			)
		);

		return ! empty( $found );
	}

	public function claim_send( $event_id, $reminder_key, $event_start_utc, $payload = array() ) {
		global $wpdb;

		$table   = $wpdb->prefix . 'meeting_reminder_state';
		$payload = array_merge(
			$payload,
			array(
				'status' => 'sending',
			)
		);

		$inserted = $wpdb->insert(
			$table,
			array(
				'event_id'        => $event_id,
				'reminder_key'    => $reminder_key,
				'sent_at_gmt'     => current_time( 'mysql', true ),
				'event_start_utc' => $event_start_utc ? gmdate( 'Y-m-d H:i:s', strtotime( $event_start_utc ) ) : null,
				'recipient_hash'  => isset( $payload['recipient_hash'] ) ? sanitize_text_field( (string) $payload['recipient_hash'] ) : null,
				'payload'         => wp_json_encode( $payload ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false !== $inserted ) {
			return true;
		}

		if ( ! empty( $wpdb->last_error ) && false === stripos( $wpdb->last_error, 'duplicate' ) ) {
			return new WP_Error( 'meeting_reminder_state_claim_failed', $wpdb->last_error );
		}

		return false;
	}

	public function mark_sent( $event_id, $reminder_key, $event_start_utc, $payload = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'meeting_reminder_state';

		$payload = array_merge(
			$payload,
			array(
				'status' => 'sent',
			)
		);

		return false !== $wpdb->replace(
			$table,
			array(
				'event_id'        => $event_id,
				'reminder_key'    => $reminder_key,
				'sent_at_gmt'     => current_time( 'mysql', true ),
				'event_start_utc' => $event_start_utc ? gmdate( 'Y-m-d H:i:s', strtotime( $event_start_utc ) ) : null,
				'recipient_hash'  => isset( $payload['recipient_hash'] ) ? sanitize_text_field( (string) $payload['recipient_hash'] ) : null,
				'payload'         => wp_json_encode( $payload ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	public function release_claim( $event_id, $reminder_key ) {
		global $wpdb;

		$table = $wpdb->prefix . 'meeting_reminder_state';

		return false !== $wpdb->delete(
			$table,
			array(
				'event_id'     => $event_id,
				'reminder_key' => $reminder_key,
			),
			array( '%s', '%s' )
		);
	}

	public function clear_all() {
		global $wpdb;

		$table = $wpdb->prefix . 'meeting_reminder_state';
		$wpdb->query( "DELETE FROM {$table}" );
	}
}
