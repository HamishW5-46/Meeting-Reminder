<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meeting_Reminder_Scheduler {
	private $tec_client;
	private $ics_service;
	private $renderer;
	private $sender;
	private $state;
	private $logger;

	public function __construct( $tec_client, $ics_service, $renderer, $sender, $state, $logger ) {
		$this->tec_client  = $tec_client;
		$this->ics_service = $ics_service;
		$this->renderer    = $renderer;
		$this->sender      = $sender;
		$this->state       = $state;
		$this->logger      = $logger;
	}

	public function get_stages( $settings ) {
		$stages = array(
			array(
				'key'         => 'primary',
				'label'       => __( 'Primary reminder', 'cso-meeting-reminder' ),
				'days_before' => (int) $settings['days_before'],
			),
		);

		// Future multi-stage reminders can extend this array while reusing the same state table.
		return apply_filters( 'meeting_reminder_reminder_stages', $stages, $settings );
	}

	public function preview_next_event( $settings ) {
		$event = $this->tec_client->get_next_event( $settings );

		if ( is_wp_error( $event ) ) {
			return $event;
		}

		return array(
			'event' => $event,
		);
	}

	public function preview_ics( $settings ) {
		$event = $this->tec_client->get_next_event( $settings );

		if ( is_wp_error( $event ) ) {
			return $event;
		}

		$package = $this->ics_service->build_attachment_package( $event, $settings );
		$this->ics_service->cleanup( $package );

		return array(
			'event' => $event,
			'ics'   => array(
				'enabled'  => ! empty( $package['enabled'] ),
				'filename' => isset( $package['filename'] ) ? $package['filename'] : '',
				'content'  => isset( $package['content'] ) ? $package['content'] : '',
				'error'    => isset( $package['error'] ) && is_wp_error( $package['error'] ) ? $package['error']->get_error_message() : '',
			),
		);
	}

	public function preview_email( $settings ) {
		$event = $this->tec_client->get_next_event( $settings );

		if ( is_wp_error( $event ) ) {
			return $event;
		}

		$stages = $this->get_stages( $settings );
		$stage  = isset( $stages[0] ) ? $stages[0] : array(
			'key'         => 'primary',
			'days_before' => (int) $settings['days_before'],
		);

		return array(
			'event'   => $event,
			'message' => $this->renderer->render( $event, $settings, $stage ),
			'stage'   => $stage,
		);
	}

	public function run( $trigger = 'cron' ) {
		$settings = Meeting_Reminder_Settings::get();
		$event    = $this->tec_client->get_next_event( $settings );

		if ( is_wp_error( $event ) ) {
			$this->logger->log(
				'info',
				'No event available for reminder evaluation.',
				array(
					'trigger' => $trigger,
					'error'   => $event->get_error_message(),
				)
			);

			return $event;
		}

		$recipients = Meeting_Reminder_Settings::get_recipient_list( $settings );

		if ( empty( $recipients ) ) {
			$error = new WP_Error( 'meeting_reminder_missing_recipients', __( 'No reminder recipients are configured.', 'cso-meeting-reminder' ) );
			$this->logger->log( 'error', 'Reminder skipped because no recipients are configured.', array( 'trigger' => $trigger ) );

			return $error;
		}

		$now    = new DateTimeImmutable( 'now', wp_timezone() );
		$stages = $this->get_stages( $settings );

		foreach ( $stages as $stage ) {
			$decision = $this->evaluate_stage( $event, $stage, $settings, $now );

			if ( is_wp_error( $decision ) ) {
				$this->logger->log(
					'error',
					'Reminder stage could not be evaluated.',
					array(
						'trigger' => $trigger,
						'stage'   => $stage,
						'error'   => $decision->get_error_message(),
					)
				);
				continue;
			}

			if ( ! $decision['should_send'] ) {
				continue;
			}

			$recipient_hash = hash( 'sha256', implode( ',', $recipients ) );
			$claim          = $this->state->claim_send(
				(string) $event['id'],
				(string) $stage['key'],
				(string) $event['utc_start_date'],
				array(
					'trigger'        => $trigger,
					'recipient_hash' => $recipient_hash,
				)
			);

			if ( is_wp_error( $claim ) ) {
				$this->logger->log(
					'error',
					'Reminder send state could not be reserved.',
					array(
						'trigger'      => $trigger,
						'event_id'     => $event['id'],
						'reminder_key' => $stage['key'],
						'error'        => $claim->get_error_message(),
					)
				);

				return $claim;
			}

			if ( ! $claim ) {
				$this->logger->log(
					'info',
					'Duplicate or concurrent reminder prevented.',
					array(
						'trigger'      => $trigger,
						'event_id'     => $event['id'],
						'reminder_key' => $stage['key'],
					)
				);

				return array(
					'status'  => 'already_sent',
					'event'   => $event,
					'stage'   => $stage,
					'trigger' => $trigger,
				);
			}

			$message     = $this->renderer->render( $event, $settings, $stage );
			$ics_package = $this->ics_service->build_attachment_package( $event, $settings );

			if ( ! empty( $ics_package['attachments'] ) ) {
				$message['attachments'] = array_merge( $message['attachments'], $ics_package['attachments'] );
			}

			// Final payload filter for custom delivery metadata or alternate content tweaks.
			$message = apply_filters( 'meeting_reminder_email_payload', $message, $event, $stage, $settings );
			$sent    = $this->sender->send( $recipients, $message, $settings );
			$this->ics_service->cleanup( $ics_package );

			if ( ! $sent ) {
				$this->state->release_claim( (string) $event['id'], (string) $stage['key'] );
				$this->logger->log(
					'error',
					'Reminder email send failed.',
					array(
						'trigger'      => $trigger,
						'event_id'     => $event['id'],
						'reminder_key' => $stage['key'],
						'recipients'   => $recipients,
					)
				);

				return new WP_Error( 'meeting_reminder_send_failed', __( 'wp_mail() reported a send failure.', 'cso-meeting-reminder' ) );
			}

			$marked_sent = $this->state->mark_sent(
				(string) $event['id'],
				(string) $stage['key'],
				(string) $event['utc_start_date'],
				array(
					'trigger'        => $trigger,
					'recipient_hash' => $recipient_hash,
					'message'        => array(
						'subject' => $message['subject'],
					),
				)
			);

			if ( ! $marked_sent ) {
				$this->logger->log(
					'error',
					'Reminder email was sent, but send state could not be finalized.',
					array(
						'trigger'      => $trigger,
						'event_id'     => $event['id'],
						'reminder_key' => $stage['key'],
					)
				);

				return new WP_Error( 'meeting_reminder_state_finalize_failed', __( 'The email was sent, but the send state could not be finalized.', 'cso-meeting-reminder' ) );
			}

			do_action( 'meeting_reminder_after_send', $event, $stage, $message, $recipients, $trigger );

			$this->logger->log(
				'info',
				'Reminder email sent.',
				array(
					'trigger'      => $trigger,
					'event_id'     => $event['id'],
					'reminder_key' => $stage['key'],
					'recipients'   => $recipients,
				)
			);

			return array(
				'status'       => 'sent',
				'event'        => $event,
				'stage'        => $stage,
				'message'      => $message,
				'scheduled_at' => $decision['scheduled_at'],
				'trigger'      => $trigger,
			);
		}

		$this->logger->log(
			'info',
			'Reminder not due yet.',
			array(
				'trigger'  => $trigger,
				'event_id' => $event['id'],
			)
		);

		return array(
			'status'  => 'not_due',
			'event'   => $event,
			'trigger' => $trigger,
		);
	}

	public function send_test_email( $recipient ) {
		$recipient = sanitize_email( $recipient );

		if ( ! is_email( $recipient ) ) {
			return new WP_Error( 'meeting_reminder_bad_test_email', __( 'A valid test email address is required.', 'cso-meeting-reminder' ) );
		}

		$settings = Meeting_Reminder_Settings::get();
		$preview  = $this->preview_email( $settings );

		if ( is_wp_error( $preview ) ) {
			$preview = array(
				'event' => array(
					'id'             => 'sample',
					'title'          => __( 'Sample CSO Committee Meeting', 'cso-meeting-reminder' ),
					'description'    => __( 'Sample event used because no live event was available for the test email.', 'cso-meeting-reminder' ),
					'start_date'     => wp_date( 'Y-m-d H:i:s', time() + WEEK_IN_SECONDS, wp_timezone() ),
					'utc_start_date' => gmdate( 'Y-m-d H:i:s', time() + WEEK_IN_SECONDS ),
					'url'            => home_url( '/' ),
					'venue_name'     => __( 'To be confirmed', 'cso-meeting-reminder' ),
				),
				'stage' => array(
					'key'         => 'test',
					'days_before' => (int) $settings['days_before'],
				),
			);
			$preview['message'] = $this->renderer->render( $preview['event'], $settings, $preview['stage'] );
		}

		$ics_package = $this->ics_service->build_attachment_package( $preview['event'], $settings );

		if ( ! empty( $ics_package['attachments'] ) ) {
			$preview['message']['attachments'] = array_merge( $preview['message']['attachments'], $ics_package['attachments'] );
		}

		$sent = $this->sender->send( array( $recipient ), $preview['message'], $settings );
		$this->ics_service->cleanup( $ics_package );

		if ( ! $sent ) {
			return new WP_Error( 'meeting_reminder_test_failed', __( 'The test email could not be sent.', 'cso-meeting-reminder' ) );
		}

		$this->logger->log(
			'info',
			'Test email sent.',
			array(
				'recipient' => $recipient,
			)
		);

		return array(
			'status'    => 'sent',
			'recipient' => $recipient,
			'event'     => $preview['event'],
			'message'   => $preview['message'],
			'ics'       => array(
				'filename' => isset( $ics_package['filename'] ) ? $ics_package['filename'] : '',
				'enabled'  => ! empty( $ics_package['enabled'] ),
				'error'    => isset( $ics_package['error'] ) && is_wp_error( $ics_package['error'] ) ? $ics_package['error']->get_error_message() : '',
			),
		);
	}

	public function clear_state() {
		$this->state->clear_all();
		$this->logger->log( 'info', 'Reminder send state cleared from the admin screen.' );
	}

	private function evaluate_stage( $event, $stage, $settings, $now ) {
		$event_start = $this->parse_datetime( (string) $event['start_date'], wp_timezone() );

		if ( ! $event_start ) {
			return new WP_Error( 'meeting_reminder_invalid_date', __( 'The event start date could not be parsed.', 'cso-meeting-reminder' ) );
		}

		$send_time_parts = explode( ':', (string) $settings['send_time'] );
		$days_before     = isset( $stage['days_before'] ) ? (int) $stage['days_before'] : (int) $settings['days_before'];
		$scheduled_date  = $event_start->sub( new DateInterval( 'P' . max( 0, $days_before ) . 'D' ) );
		$scheduled_at    = $scheduled_date->setTime( (int) $send_time_parts[0], (int) $send_time_parts[1], 0 );

		return array(
			'should_send' => $now >= $scheduled_at && $now < $event_start,
			'scheduled_at'=> $scheduled_at->format( DATE_ATOM ),
			'event_start' => $event_start->format( DATE_ATOM ),
		);
	}

	private function parse_datetime( $value, $timezone ) {
		if ( '' === $value ) {
			return null;
		}

		try {
			return new DateTimeImmutable( $value, $timezone );
		} catch ( Exception $exception ) {
			return null;
		}
	}
}
