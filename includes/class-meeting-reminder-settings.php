<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meeting_Reminder_Settings {
	const OPTION_KEY = 'meeting_reminder_settings';

	public static function get() {
		$saved    = get_option( self::OPTION_KEY, array() );
		$defaults = self::get_defaults();

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, $defaults );
	}

	public static function ensure_defaults() {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, self::get_defaults() );
		}
	}

	public static function get_defaults() {
		$rest_url          = home_url( '/wp-json/tribe/events/v1/events' );
		$timezone          = wp_timezone_string();
		$default_recipient = get_option( 'admin_email', '' );

		return array(
			'events_endpoint'      => esc_url_raw( $rest_url ),
			'allow_external_events_endpoint' => 0,
			'title_keyword'        => 'CSO',
			'days_before'          => 7,
			'send_time'            => '09:00',
			'recipient_emails'     => $default_recipient,
			'from_name'            => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'from_email'           => $default_recipient,
			'template_subject'     => self::read_template( 'default-subject.txt' ),
			'template_text'        => self::read_template( 'default-text.txt' ),
			'template_html'        => self::read_template( 'default-html.html' ),
			'enable_ics_attachment'=> 0,
			'ics_duration_minutes' => 60,
			'ics_location_label'   => 'Canberra AA CSO Committee Meeting',
			'ics_organizer_name'   => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'ics_organizer_email'  => $default_recipient,
			'enable_event_autogeneration' => 0,
			'autogen_event_title'  => 'Central Service Office Monthly Committee Meeting',
			'autogen_start_time'   => '16:30',
			'autogen_duration_minutes' => 60,
			'autogen_zoom_link'    => '',
			'autogen_zoom_id'      => '',
			'autogen_zoom_passcode'=> '',
			'timezone_hint'        => $timezone ? $timezone : 'UTC',
		);
	}

	public static function sanitize( $input ) {
		$current = self::get();
		$output  = $current;

		if ( ! is_array( $input ) ) {
			return $output;
		}

		$output['events_endpoint']  = isset( $input['events_endpoint'] ) ? esc_url_raw( trim( (string) $input['events_endpoint'] ) ) : $current['events_endpoint'];
		$output['allow_external_events_endpoint'] = ! empty( $input['allow_external_events_endpoint'] ) ? 1 : 0;
		$output['title_keyword']    = isset( $input['title_keyword'] ) ? sanitize_text_field( $input['title_keyword'] ) : '';
		$output['days_before']      = isset( $input['days_before'] ) ? max( 0, absint( $input['days_before'] ) ) : $current['days_before'];
		$output['send_time']        = isset( $input['send_time'] ) ? self::sanitize_time( (string) $input['send_time'], $current['send_time'] ) : $current['send_time'];
		$output['recipient_emails'] = isset( $input['recipient_emails'] )
			? self::sanitize_email_list( (string) $input['recipient_emails'] )
			: $current['recipient_emails'];
		$output['from_name']        = isset( $input['from_name'] ) ? sanitize_text_field( $input['from_name'] ) : $current['from_name'];
		$output['from_email']       = isset( $input['from_email'] ) && is_email( $input['from_email'] ) ? sanitize_email( $input['from_email'] ) : '';
		$output['template_subject'] = isset( $input['template_subject'] ) ? sanitize_text_field( (string) $input['template_subject'] ) : $current['template_subject'];
		$output['template_text']    = isset( $input['template_text'] ) ? sanitize_textarea_field( (string) $input['template_text'] ) : $current['template_text'];
		$output['template_html']    = isset( $input['template_html'] ) ? wp_kses_post( (string) $input['template_html'] ) : $current['template_html'];
		$output['enable_ics_attachment'] = ! empty( $input['enable_ics_attachment'] ) ? 1 : 0;
		$output['ics_duration_minutes']  = isset( $input['ics_duration_minutes'] ) ? max( 1, absint( $input['ics_duration_minutes'] ) ) : $current['ics_duration_minutes'];
		$output['ics_location_label']    = isset( $input['ics_location_label'] ) ? sanitize_text_field( $input['ics_location_label'] ) : $current['ics_location_label'];
		$output['ics_organizer_name']    = isset( $input['ics_organizer_name'] ) ? sanitize_text_field( $input['ics_organizer_name'] ) : $current['ics_organizer_name'];
		$output['ics_organizer_email']   = isset( $input['ics_organizer_email'] ) && is_email( $input['ics_organizer_email'] ) ? sanitize_email( $input['ics_organizer_email'] ) : '';
		$output['enable_event_autogeneration'] = ! empty( $input['enable_event_autogeneration'] ) ? 1 : 0;
		$output['autogen_event_title']  = isset( $input['autogen_event_title'] ) ? sanitize_text_field( $input['autogen_event_title'] ) : $current['autogen_event_title'];
		$output['autogen_start_time']   = isset( $input['autogen_start_time'] ) ? self::sanitize_time( (string) $input['autogen_start_time'], $current['autogen_start_time'] ) : $current['autogen_start_time'];
		$output['autogen_duration_minutes'] = isset( $input['autogen_duration_minutes'] ) ? max( 1, absint( $input['autogen_duration_minutes'] ) ) : $current['autogen_duration_minutes'];
		$output['autogen_zoom_link']    = isset( $input['autogen_zoom_link'] ) ? esc_url_raw( trim( (string) $input['autogen_zoom_link'] ) ) : $current['autogen_zoom_link'];
		$output['autogen_zoom_id']      = isset( $input['autogen_zoom_id'] ) ? sanitize_text_field( $input['autogen_zoom_id'] ) : $current['autogen_zoom_id'];
		$output['autogen_zoom_passcode']= isset( $input['autogen_zoom_passcode'] ) ? sanitize_text_field( $input['autogen_zoom_passcode'] ) : $current['autogen_zoom_passcode'];
		$output['timezone_hint']         = $current['timezone_hint'];

		return $output;
	}

	public static function get_recipient_list( $settings = null ) {
		if ( null === $settings ) {
			$settings = self::get();
		}

		$raw = isset( $settings['recipient_emails'] ) ? (string) $settings['recipient_emails'] : '';

		if ( '' === $raw ) {
			return array();
		}

		$parts      = preg_split( '/[\r\n,;]+/', $raw );
		$recipients = array();

		foreach ( $parts as $part ) {
			$email = sanitize_email( trim( (string) $part ) );

			if ( $email && is_email( $email ) ) {
				$recipients[] = $email;
			}
		}

		return array_values( array_unique( $recipients ) );
	}

	private static function read_template( $filename ) {
		$path = MEETING_REMINDER_PATH . 'templates/' . $filename;

		if ( ! file_exists( $path ) ) {
			return '';
		}

		$content = file_get_contents( $path );

		return false === $content ? '' : $content;
	}

	private static function sanitize_time( $value, $fallback ) {
		$value = trim( $value );

		if ( preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ) {
			return $value;
		}

		return $fallback;
	}

	private static function sanitize_email_list( $value ) {
		$emails = self::get_recipient_list(
			array(
				'recipient_emails' => $value,
			)
		);

		return implode( "\n", $emails );
	}
}
