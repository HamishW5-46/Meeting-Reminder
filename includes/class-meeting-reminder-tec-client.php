<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meeting_Reminder_TEC_Client {
	private $logger;

	public function __construct( Meeting_Reminder_Logger $logger ) {
		$this->logger = $logger;
	}

	public function get_next_event( $settings ) {
		$endpoint = isset( $settings['events_endpoint'] ) ? esc_url_raw( (string) $settings['events_endpoint'] ) : '';

		if ( '' === $endpoint ) {
			return new WP_Error( 'meeting_reminder_missing_endpoint', __( 'The Events Calendar endpoint is not configured.', 'cso-meeting-reminder' ) );
		}

		if ( ! $this->is_endpoint_allowed( $endpoint, $settings ) ) {
			return new WP_Error( 'meeting_reminder_external_endpoint_blocked', __( 'The Events Calendar endpoint must be on this site unless external endpoints are explicitly enabled.', 'cso-meeting-reminder' ) );
		}

		$request_args = apply_filters(
			'meeting_reminder_api_request_args',
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		for ( $page = 1; $page <= 5; $page++ ) {
			$query_args = array(
				'per_page'   => 25,
				'page'       => $page,
				'start_date' => wp_date( 'Y-m-d H:i:s', time(), wp_timezone() ),
			);

			$url      = add_query_arg( $query_args, $endpoint );
			$response = wp_remote_get( $url, $request_args );

			if ( is_wp_error( $response ) ) {
				$this->logger->log(
					'error',
					'TEC request failed.',
					array(
						'error' => $response->get_error_message(),
						'url'   => $url,
					)
				);

				return $response;
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			$body        = wp_remote_retrieve_body( $response );

			if ( 200 !== $status_code ) {
				$this->logger->log(
					'error',
					'TEC endpoint returned a non-200 response.',
					array(
						'status_code' => $status_code,
						'body'        => function_exists( 'mb_substr' ) ? mb_substr( $body, 0, 500 ) : substr( $body, 0, 500 ),
						'url'         => $url,
					)
				);

				return new WP_Error( 'meeting_reminder_bad_status', __( 'The Events Calendar endpoint returned an unexpected response.', 'cso-meeting-reminder' ) );
			}

			$data = json_decode( $body, true );

			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
				$this->logger->log(
					'error',
					'TEC response could not be decoded.',
					array(
						'json_error' => json_last_error_msg(),
						'body'       => function_exists( 'mb_substr' ) ? mb_substr( $body, 0, 500 ) : substr( $body, 0, 500 ),
					)
				);

				return new WP_Error( 'meeting_reminder_bad_json', __( 'The Events Calendar endpoint returned invalid JSON.', 'cso-meeting-reminder' ) );
			}

			$events = isset( $data['events'] ) && is_array( $data['events'] ) ? $data['events'] : array();

			if ( empty( $events ) ) {
				break;
			}

			foreach ( $events as $event ) {
				if ( ! is_array( $event ) ) {
					continue;
				}

				if ( ! $this->matches_keyword( $event, (string) $settings['title_keyword'] ) ) {
					continue;
				}

				$event = $this->normalize_event( $event );
				// Extension point for future qualification rules beyond a title match.
				$match = (bool) apply_filters( 'meeting_reminder_event_matches', true, $event, $settings );

				if ( $match ) {
					return $event;
				}
			}
		}

		return new WP_Error( 'meeting_reminder_no_event', __( 'No qualifying upcoming event was found.', 'cso-meeting-reminder' ) );
	}

	private function is_endpoint_allowed( $endpoint, $settings ) {
		if ( ! empty( $settings['allow_external_events_endpoint'] ) ) {
			return true;
		}

		$endpoint_host = wp_parse_url( $endpoint, PHP_URL_HOST );
		$site_host     = wp_parse_url( home_url(), PHP_URL_HOST );

		return is_string( $endpoint_host )
			&& is_string( $site_host )
			&& strtolower( $endpoint_host ) === strtolower( $site_host );
	}

	private function matches_keyword( $event, $keyword ) {
		if ( '' === trim( $keyword ) ) {
			return true;
		}

		$title = isset( $event['title'] ) ? wp_strip_all_tags( (string) $event['title'] ) : '';

		return false !== stripos( $title, $keyword );
	}

	private function normalize_event( $event ) {
		$title      = isset( $event['title'] ) ? wp_strip_all_tags( (string) $event['title'] ) : '';
		$start_date = isset( $event['start_date'] ) ? (string) $event['start_date'] : '';
		$utc_start  = isset( $event['utc_start_date'] ) ? (string) $event['utc_start_date'] : $start_date;
		$url        = isset( $event['url'] ) ? esc_url_raw( (string) $event['url'] ) : '';
		$venue_name = '';

		if ( isset( $event['venue']['venue'] ) ) {
			$venue_name = sanitize_text_field( (string) $event['venue']['venue'] );
		}

		return array(
			'id'             => isset( $event['id'] ) ? (string) $event['id'] : md5( wp_json_encode( $event ) ),
			'title'          => $title,
			'description'    => isset( $event['description'] ) ? wp_kses_post( (string) $event['description'] ) : '',
			'start_date'     => $start_date,
			'utc_start_date' => $utc_start,
			'end_date'       => isset( $event['end_date'] ) ? (string) $event['end_date'] : '',
			'timezone'       => isset( $event['timezone'] ) ? sanitize_text_field( (string) $event['timezone'] ) : wp_timezone_string(),
			'url'            => $url,
			'venue_name'     => $venue_name,
			'raw'            => $event,
		);
	}
}
