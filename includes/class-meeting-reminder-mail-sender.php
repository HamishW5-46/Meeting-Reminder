<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meeting_Reminder_Mail_Sender {
	private $active_payload;

	public function send( $to, $message, $settings ) {
		if ( empty( $to ) ) {
			return false;
		}

		$this->active_payload = $message;

		add_filter( 'wp_mail_content_type', array( $this, 'filter_content_type' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'filter_from_name' ), 10, 1 );
		add_filter( 'wp_mail_from', array( $this, 'filter_from_email' ), 10, 1 );
		add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );

		$headers     = array();
		$subject     = isset( $message['subject'] ) ? (string) $message['subject'] : '';
		$html_body   = isset( $message['html'] ) ? (string) $message['html'] : '';
		$attachments = isset( $message['attachments'] ) && is_array( $message['attachments'] ) ? $message['attachments'] : array();

		if ( ! empty( $settings['from_email'] ) && is_email( $settings['from_email'] ) ) {
			$headers[] = 'Reply-To: ' . sanitize_email( $settings['from_email'] );
		}

		$result = wp_mail( $to, $subject, $html_body, $headers, $attachments );

		remove_filter( 'wp_mail_content_type', array( $this, 'filter_content_type' ) );
		remove_filter( 'wp_mail_from_name', array( $this, 'filter_from_name' ), 10 );
		remove_filter( 'wp_mail_from', array( $this, 'filter_from_email' ), 10 );
		remove_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );

		$this->active_payload = null;

		return (bool) $result;
	}

	public function filter_content_type() {
		return 'text/html';
	}

	public function filter_from_name( $name ) {
		$settings = Meeting_Reminder_Settings::get();

		return ! empty( $settings['from_name'] ) ? (string) $settings['from_name'] : $name;
	}

	public function filter_from_email( $email ) {
		$settings = Meeting_Reminder_Settings::get();

		return ! empty( $settings['from_email'] ) && is_email( $settings['from_email'] ) ? (string) $settings['from_email'] : $email;
	}

	public function configure_phpmailer( $phpmailer ) {
		if ( empty( $this->active_payload ) ) {
			return;
		}

		$phpmailer->isHTML( true );
		$phpmailer->Body    = isset( $this->active_payload['html'] ) ? (string) $this->active_payload['html'] : '';
		$phpmailer->AltBody = isset( $this->active_payload['text'] ) ? (string) $this->active_payload['text'] : wp_strip_all_tags( $phpmailer->Body );
	}
}
