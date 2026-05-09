<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meeting_Reminder_Ics_Generator {
	public function generate( $event, $settings ) {
		$utc_start = $this->parse_utc_start( $event );

		if ( ! $utc_start ) {
			return new WP_Error( 'meeting_reminder_ics_invalid_start', __( 'The ICS start date could not be generated.', 'cso-meeting-reminder' ) );
		}

		$duration = isset( $settings['ics_duration_minutes'] ) ? max( 1, absint( $settings['ics_duration_minutes'] ) ) : 60;
		$utc_end  = $utc_start->add( new DateInterval( 'PT' . $duration . 'M' ) );
		$host     = wp_parse_url( home_url(), PHP_URL_HOST );
		$host     = is_string( $host ) && '' !== $host ? $host : 'localhost';
		$uid      = sanitize_key( (string) $event['id'] ) . '@' . $host;
		$summary  = isset( $event['title'] ) ? $this->decode_text( (string) $event['title'] ) : '';
		$location = ! empty( $settings['ics_location_label'] ) ? $this->decode_text( (string) $settings['ics_location_label'] ) : $this->decode_text( (string) $event['venue_name'] );
		$url      = isset( $event['url'] ) ? esc_url_raw( (string) $event['url'] ) : '';
		$description = trim( $this->decode_text( wp_strip_all_tags( (string) $event['description'] ) ) );

		if ( $url ) {
			$description = trim( $description . "\n\n" . $url );
		}

		$organizer_name  = ! empty( $settings['ics_organizer_name'] ) ? $this->decode_text( (string) $settings['ics_organizer_name'] ) : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$organizer_email = ! empty( $settings['ics_organizer_email'] ) ? sanitize_email( (string) $settings['ics_organizer_email'] ) : get_option( 'admin_email', '' );
		$organizer_email = is_email( $organizer_email ) ? $organizer_email : get_option( 'admin_email', '' );

		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Canberra AA//Meeting Reminder//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'BEGIN:VEVENT',
			'UID:' . $this->escape_text( $uid ),
			'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
			'DTSTART:' . $utc_start->format( 'Ymd\THis\Z' ),
			'DTEND:' . $utc_end->format( 'Ymd\THis\Z' ),
			'SUMMARY:' . $this->escape_text( $summary ),
			'DESCRIPTION:' . $this->escape_text( $description ),
			'LOCATION:' . $this->escape_text( $location ),
			'URL:' . $this->escape_text( $url ),
			'ORGANIZER;CN=' . $this->escape_param( $organizer_name ) . ':MAILTO:' . $this->escape_text( $organizer_email ),
			'END:VEVENT',
			'END:VCALENDAR',
		);

		$folded = array_map( array( $this, 'fold_line' ), $lines );

		return implode( "\r\n", $folded ) . "\r\n";
	}

	public function build_filename( $event ) {
		$title = isset( $event['title'] ) ? sanitize_title( (string) $event['title'] ) : 'meeting';
		$id    = isset( $event['id'] ) ? sanitize_file_name( (string) $event['id'] ) : 'event';

		return sanitize_file_name( $title . '-' . $id . '.ics' );
	}

	private function parse_utc_start( $event ) {
		$raw_utc = isset( $event['utc_start_date'] ) ? (string) $event['utc_start_date'] : '';

		if ( '' !== $raw_utc ) {
			try {
				return new DateTimeImmutable( $raw_utc, new DateTimeZone( 'UTC' ) );
			} catch ( Exception $exception ) {
			}
		}

		$raw_local = isset( $event['start_date'] ) ? (string) $event['start_date'] : '';

		if ( '' === $raw_local ) {
			return null;
		}

		try {
			$local = new DateTimeImmutable( $raw_local, wp_timezone() );

			return $local->setTimezone( new DateTimeZone( 'UTC' ) );
		} catch ( Exception $exception ) {
			return null;
		}
	}

	private function escape_text( $value ) {
		$value = str_replace( '\\', '\\\\', (string) $value );
		$value = str_replace( ';', '\;', $value );
		$value = str_replace( ',', '\,', $value );
		$value = preg_replace( "/\r\n|\r|\n/", '\\n', $value );

		return (string) $value;
	}

	private function decode_text( $value ) {
		return html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ? get_bloginfo( 'charset' ) : 'UTF-8' );
	}

	private function escape_param( $value ) {
		$value = str_replace( '\\', '\\\\', (string) $value );
		$value = str_replace( ';', '\;', $value );
		$value = str_replace( ',', '\,', $value );
		$value = str_replace( '"', '\"', $value );

		return '"' . $value . '"';
	}

	private function fold_line( $line ) {
		$line   = (string) $line;
		$output = '';

		if ( ! function_exists( 'preg_split' ) || ! preg_match( '//u', $line ) ) {
			while ( strlen( $line ) > 73 ) {
				$output .= substr( $line, 0, 73 ) . "\r\n ";
				$line    = substr( $line, 73 );
			}

			return $output . $line;
		}

		$chars        = preg_split( '//u', $line, -1, PREG_SPLIT_NO_EMPTY );
		$current_line = '';

		foreach ( $chars as $char ) {
			if ( strlen( $current_line . $char ) > 73 ) {
				$output      .= $current_line . "\r\n ";
				$current_line = $char;
				continue;
			}

			$current_line .= $char;
		}

		return $output . $current_line;
	}
}
