<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meeting_Reminder_Admin {
	const MENU_SLUG         = 'meeting-reminder';
	const SETTINGS_GROUP    = 'meeting_reminder_group';
	const ACTION_RESULT_KEY = 'meeting_reminder_admin_result_';

	private $scheduler;
	private $logger;

	public function __construct( $scheduler, $logger ) {
		$this->scheduler = $scheduler;
		$this->logger    = $logger;
	}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_meeting_reminder_action', array( $this, 'handle_action' ) );
	}

	public function register_menu() {
		add_options_page(
			__( 'Meeting Reminder', 'meeting-reminder' ),
			__( 'Meeting Reminder', 'meeting-reminder' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			Meeting_Reminder_Settings::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Meeting_Reminder_Settings', 'sanitize' ),
				'default'           => Meeting_Reminder_Settings::get_defaults(),
			)
		);

		add_settings_section(
			'meeting_reminder_general',
			__( 'Reminder Configuration', 'meeting-reminder' ),
			array( $this, 'render_general_section' ),
			self::MENU_SLUG
		);

		$fields = array(
			'events_endpoint'  => __( 'Events endpoint', 'meeting-reminder' ),
			'allow_external_events_endpoint' => __( 'Allow external events endpoint', 'meeting-reminder' ),
			'title_keyword'    => __( 'Title keyword filter', 'meeting-reminder' ),
			'days_before'      => __( 'Send X days before', 'meeting-reminder' ),
			'send_time'        => __( 'Local send time', 'meeting-reminder' ),
			'recipient_emails' => __( 'Recipient emails', 'meeting-reminder' ),
			'from_name'        => __( 'From name', 'meeting-reminder' ),
			'from_email'       => __( 'From email', 'meeting-reminder' ),
			'template_subject' => __( 'Subject template', 'meeting-reminder' ),
			'template_text'    => __( 'Plain text template', 'meeting-reminder' ),
			'template_html'    => __( 'HTML template', 'meeting-reminder' ),
			'enable_ics_attachment' => __( 'Enable ICS attachment', 'meeting-reminder' ),
			'ics_duration_minutes'  => __( 'ICS duration (minutes)', 'meeting-reminder' ),
			'ics_location_label'    => __( 'ICS location label', 'meeting-reminder' ),
			'ics_organizer_name'    => __( 'ICS organizer name', 'meeting-reminder' ),
			'ics_organizer_email'   => __( 'ICS organizer email', 'meeting-reminder' ),
			'enable_event_autogeneration' => __( 'Enable event autogeneration', 'meeting-reminder' ),
			'autogen_event_title'   => __( 'Autogen event title', 'meeting-reminder' ),
			'autogen_start_time'    => __( 'Autogen start time', 'meeting-reminder' ),
			'autogen_duration_minutes' => __( 'Autogen duration (minutes)', 'meeting-reminder' ),
			'autogen_zoom_link'     => __( 'Autogen Zoom link', 'meeting-reminder' ),
			'autogen_zoom_id'       => __( 'Autogen Zoom ID', 'meeting-reminder' ),
			'autogen_zoom_passcode' => __( 'Autogen Zoom passcode', 'meeting-reminder' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				$key,
				$label,
				array( $this, 'render_field' ),
				self::MENU_SLUG,
				'meeting_reminder_general',
				array(
					'key' => $key,
				)
			);
		}
	}

	public function render_general_section() {
		$settings = Meeting_Reminder_Settings::get();

		echo '<p>';
		echo esc_html(
			sprintf(
				__( 'Reminders are evaluated against the next qualifying TEC event and scheduled using the site timezone: %s.', 'meeting-reminder' ),
				$settings['timezone_hint']
			)
		);
		echo '</p>';
	}

	public function render_field( $args ) {
		$settings = Reminder_Settings::get();
		$key      = $args['key'];
		$name     = Meeting_Reminder_Settings::OPTION_KEY . '[' . $key . ']';
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';

		switch ( $key ) {
			case 'events_endpoint':
			case 'title_keyword':
			case 'from_name':
			case 'from_email':
			case 'template_subject':
			case 'ics_location_label':
			case 'ics_organizer_name':
			case 'ics_organizer_email':
			case 'autogen_event_title':
			case 'autogen_zoom_link':
			case 'autogen_zoom_id':
			case 'autogen_zoom_passcode':
				printf( '<input type="text" class="regular-text" name="%1$s" value="%2$s" />', esc_attr( $name ), esc_attr( (string) $value ) );
				break;
			case 'allow_external_events_endpoint':
				printf(
					'<label><input type="checkbox" name="%1$s" value="1"%2$s /> %3$s</label>',
					esc_attr( $name ),
					checked( ! empty( $value ), true, false ),
					esc_html__( 'Permit reminder lookups from a different host than this WordPress site.', 'meeting-reminder' )
				);
				break;
			case 'enable_ics_attachment':
				printf(
					'<label><input type="checkbox" name="%1$s" value="1"%2$s /> %3$s</label>',
					esc_attr( $name ),
					checked( ! empty( $value ), true, false ),
					esc_html__( 'Attach a calendar invite (.ics) to reminder emails.', 'meeting-reminder' )
				);
				break;
			case 'enable_event_autogeneration':
				printf(
					'<label><input type="checkbox" name="%1$s" value="1"%2$s /> %3$s</label>',
					esc_attr( $name ),
					checked( ! empty( $value ), true, false ),
					esc_html__( 'Create missing monthly CSO meeting events in The Events Calendar.', 'meeting-reminder' )
				);
				break;
			case 'days_before':
				printf( '<input type="number" min="0" class="small-text" name="%1$s" value="%2$d" />', esc_attr( $name ), (int) $value );
				break;
			case 'ics_duration_minutes':
			case 'autogen_duration_minutes':
				printf( '<input type="number" min="1" class="small-text" name="%1$s" value="%2$d" />', esc_attr( $name ), (int) $value );
				break;
			case 'send_time':
			case 'autogen_start_time':
				printf( '<input type="time" step="60" name="%1$s" value="%2$s" />', esc_attr( $name ), esc_attr( (string) $value ) );
				break;
			case 'recipient_emails':
			case 'template_text':
			case 'template_html':
				printf(
					'<textarea class="large-text code" rows="%3$d" name="%1$s">%2$s</textarea>',
					esc_attr( $name ),
					esc_textarea( (string) $value ),
					'template_html' === $key ? 14 : 8
				);
				break;
		}

		echo $this->field_help( $key );
	}

	public function handle_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'meeting-reminder' ) );
		}

		check_admin_referer( 'reminder_admin_action' );

		$action   = isset( $_POST['meeting_action'] ) ? sanitize_key( wp_unslash( $_POST['meeting_action'] ) ) : '';
		$settings = Meeting_Reminder_Settings::get();
		$result   = array(
			'type'    => 'error',
			'message' => __( 'Unknown action.', 'meeting-reminder' ),
		);

		switch ( $action ) {
			case 'preview_next_event':
				$result = $this->format_result( $this->scheduler->preview_next_event( $settings ), __( 'Next event preview generated.', 'meeting-reminder' ) );
				break;
			case 'preview_email':
				$result = $this->format_result( $this->scheduler->preview_email( $settings ), __( 'Email preview generated.', 'meeting-reminder' ) );
				break;
			case 'preview_ics':
				$result = $this->format_result( $this->scheduler->preview_ics( $settings ), __( 'ICS preview generated.', 'meeting-reminder' ) );
				break;
			case 'download_ics':
				$this->download_ics( $settings );
				return;
			case 'send_test_email':
				$recipient = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : '';
				$result    = $this->format_result( $this->scheduler->send_test_email( $recipient ), __( 'Test email sent.', 'meeting-reminder' ) );
				break;
			case 'run_now':
				$result = $this->format_result( $this->scheduler->run( 'manual' ), __( 'Scheduled logic executed.', 'meeting-reminder' ) );
				break;
			case 'clear_state':
				$this->scheduler->clear_state();
				$result = array(
					'type'    => 'success',
					'message' => __( 'Send state cleared.', 'meeting-reminder' ),
				);
				break;
		}

		$this->store_result( $result );

		wp_safe_redirect( $this->get_page_url() );
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$result = $this->get_result();
		$logs   = $this->logger->get_recent( 12 );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Meeting Reminder', 'meeting-reminder' ) . '</h1>';

		if ( ! empty( $result ) ) {
			$class = 'success' === $result['type'] ? 'notice notice-success' : 'notice notice-error';
			echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $result['message'] ) . '</p></div>';
		}

		echo '<form action="options.php" method="post">';
		settings_fields( self::SETTINGS_GROUP );
		do_settings_sections( self::MENU_SLUG );
		submit_button( __( 'Save Settings', 'meeting-reminder' ) );
		echo '</form>';

		$this->render_actions_panel();
		$this->render_result_panel( $result );
		$this->render_logs_panel( $logs );

		echo '</div>';
	}

	private function render_actions_panel() {
		echo '<hr />';
		echo '<h2>' . esc_html__( 'Manual Actions', 'meeting-reminder' ) . '</h2>';
		echo '<p>' . esc_html__( 'Use these tools for previewing, testing, and operational troubleshooting.', 'meeting-reminder' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="meeting_reminder_action" />';
		wp_nonce_field( 'meeting_reminder_admin_action' );

		echo '<p>';
		echo '<button type="submit" class="button button-secondary" name="meeting_action" value="preview_next_event">' . esc_html__( 'Preview Next Event', 'meeting-reminder' ) . '</button> ';
		echo '<button type="submit" class="button button-secondary" name="meeting_action" value="preview_email">' . esc_html__( 'Preview Rendered Email', 'meeting-reminder' ) . '</button> ';
		echo '<button type="submit" class="button button-secondary" name="meeting_action" value="preview_ics">' . esc_html__( 'Preview ICS', 'meeting-reminder' ) . '</button> ';
		echo '<button type="submit" class="button button-secondary" name="meeting_action" value="download_ics">' . esc_html__( 'Download ICS', 'meeting-reminder' ) . '</button> ';
		echo '<button type="submit" class="button button-secondary" name="meeting_action" value="run_now">' . esc_html__( 'Run Scheduled Logic Now', 'meeting-reminder' ) . '</button> ';
		echo '<button type="submit" class="button button-secondary" name="meeting_action" value="clear_state">' . esc_html__( 'Clear Sent State', 'meeting-reminder' ) . '</button>';
		echo '</p>';

		echo '<p style="margin-top:16px;">';
		echo '<label for="meeting-test-email"><strong>' . esc_html__( 'Test email recipient', 'meeting-reminder' ) . '</strong></label><br />';
		echo '<input id="meetingtest-email" type="email" class="regular-text" name="test_email" value="' . esc_attr( get_option( 'admin_email', '' ) ) . '" />';
		echo '</p>';
		echo '<p><button type="submit" class="button button-secondary" name="meeting_action" value="send_test_email">' . esc_html__( 'Send Test Email', 'meeting-reminder' ) . '</button></p>';

		echo '</form>';
	}

	private function render_result_panel( $result ) {
		if ( empty( $result['data'] ) ) {
			return;
		}

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Latest Action Output', 'meeting-reminder' ) . '</h2>';

		if ( isset( $result['data']['event'] ) ) {
			$event = $result['data']['event'];
			echo '<h3>' . esc_html__( 'Event', 'meeting-reminder' ) . '</h3>';
			echo '<table class="widefat striped"><tbody>';
			echo '<tr><td><strong>' . esc_html__( 'Title', 'meeting-reminder' ) . '</strong></td><td>' . esc_html( (string) $event['title'] ) . '</td></tr>';
			echo '<tr><td><strong>' . esc_html__( 'Start', 'meeting-reminder' ) . '</strong></td><td>' . esc_html( (string) $event['start_date'] ) . '</td></tr>';
			echo '<tr><td><strong>' . esc_html__( 'Venue', 'meeting-reminder' ) . '</strong></td><td>' . esc_html( (string) $event['venue_name'] ) . '</td></tr>';
			echo '<tr><td><strong>' . esc_html__( 'URL', 'meeting-reminder' ) . '</strong></td><td><a href="' . esc_url( (string) $event['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( (string) $event['url'] ) . '</a></td></tr>';
			echo '</tbody></table>';
		}

		if ( isset( $result['data']['message'] ) ) {
			$message = $result['data']['message'];
			echo '<h3>' . esc_html__( 'Rendered Email', 'meeting-reminder' ) . '</h3>';
			echo '<p><strong>' . esc_html__( 'Subject:', 'meeting-reminder' ) . '</strong> ' . esc_html( (string) $message['subject'] ) . '</p>';
			echo '<h4>' . esc_html__( 'Text Version', 'meeting-reminder' ) . '</h4>';
			echo '<pre style="background:#fff;border:1px solid #ccd0d4;padding:12px;white-space:pre-wrap;">' . esc_html( (string) $message['text'] ) . '</pre>';
			echo '<h4>' . esc_html__( 'HTML Version', 'meeting-reminder' ) . '</h4>';
			echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:12px;">' . wp_kses_post( (string) $message['html'] ) . '</div>';
		}

		if ( isset( $result['data']['ics'] ) ) {
			$ics = $result['data']['ics'];
			echo '<h3>' . esc_html__( 'ICS Attachment', 'meeting-reminder' ) . '</h3>';
			echo '<p><strong>' . esc_html__( 'Enabled:', 'meeting-reminder' ) . '</strong> ' . esc_html( ! empty( $ics['enabled'] ) ? __( 'Yes', 'meeting-reminder' ) : __( 'No', 'meeting-reminder' ) ) . '</p>';

			if ( ! empty( $ics['filename'] ) ) {
				echo '<p><strong>' . esc_html__( 'Filename:', 'meeting-reminder' ) . '</strong> ' . esc_html( (string) $ics['filename'] ) . '</p>';
			}

			if ( ! empty( $ics['error'] ) ) {
				echo '<p><strong>' . esc_html__( 'Error:', 'meeting-reminder' ) . '</strong> ' . esc_html( (string) $ics['error'] ) . '</p>';
			}

			if ( ! empty( $ics['content'] ) ) {
				echo '<pre style="background:#fff;border:1px solid #ccd0d4;padding:12px;white-space:pre-wrap;">' . esc_html( (string) $ics['content'] ) . '</pre>';
			}
		}

		echo '<h3>' . esc_html__( 'Raw Result', 'meeting-reminder' ) . '</h3>';
		echo '<pre style="background:#fff;border:1px solid #ccd0d4;padding:12px;white-space:pre-wrap;">' . esc_html( wp_json_encode( $result['data'], JSON_PRETTY_PRINT ) ) . '</pre>';
	}

	private function render_logs_panel( $logs ) {
		echo '<hr />';
		echo '<h2>' . esc_html__( 'Recent Logs', 'meeting-reminder' ) . '</h2>';

		if ( empty( $logs ) ) {
			echo '<p>' . esc_html__( 'No log entries yet.', 'meeting-reminder' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr><th>' . esc_html__( 'When (GMT)', 'meeting-reminder' ) . '</th><th>' . esc_html__( 'Level', 'meeting-reminder' ) . '</th><th>' . esc_html__( 'Message', 'meeting-reminder' ) . '</th><th>' . esc_html__( 'Context', 'meeting-reminder' ) . '</th></tr></thead><tbody>';

		foreach ( $logs as $log ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $log->created_at_gmt ) . '</td>';
			echo '<td>' . esc_html( strtoupper( (string) $log->level ) ) . '</td>';
			echo '<td>' . esc_html( (string) $log->message ) . '</td>';
			echo '<td><code>' . esc_html( (string) $log->context ) . '</code></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	private function field_help( $key ) {
		$help = array(
			'events_endpoint'  => __( 'REST endpoint used as the source of truth, typically /wp-json/tribe/events/v1/events.', 'meeting-reminder' ),
			'allow_external_events_endpoint' => __( 'Leave disabled unless the Events Calendar endpoint is intentionally hosted on a different domain.', 'meeting-reminder' ),
			'title_keyword'    => __( 'Only events whose titles contain this keyword will qualify. Leave blank to match any event title.', 'meeting-reminder' ),
			'days_before'      => __( 'How many full days before the event the reminder should become eligible to send.', 'meeting-reminder' ),
			'send_time'        => __( 'Local site time when the reminder should send on the eligible day.', 'meeting-reminder' ),
			'recipient_emails' => __( 'One email per line, or separate multiple recipients with commas.', 'meeting-reminder' ),
			'from_name'        => __( 'Optional friendly sender name used when wp_mail() sends the reminder.', 'meeting-reminder' ),
			'from_email'       => __( 'Optional sender address. Leave blank to use the site mail defaults.', 'meeting-reminder' ),
			'template_subject' => __( 'Supports template tokens such as {{event_title}}, {{event_date}}, {{event_time}}, and {{days_before}}.', 'meeting-reminder' ),
			'template_text'    => __( 'Plain text body used as the multipart AltBody.', 'meeting-reminder' ),
			'template_html'    => __( 'HTML body used as the primary email content. Keep markup email-safe.', 'meeting-reminder' ),
			'enable_ics_attachment' => __( 'If enabled, outgoing reminders include a generated .ics calendar file.', 'meeting-reminder' ),
			'ics_duration_minutes'  => __( 'Used to calculate DTEND from the event start time in UTC.', 'meeting-reminder' ),
			'ics_location_label'    => __( 'Overrides the LOCATION value in the generated calendar file.', 'meeting-reminder' ),
			'ics_organizer_name'    => __( 'Used for the ICS ORGANIZER CN value.', 'meeting-reminder' ),
			'ics_organizer_email'   => __( 'Used for the ICS ORGANIZER mailto value.', 'meeting-reminder' ),
			'enable_event_autogeneration' => __( 'When enabled, the plugin creates or links monthly CSO meeting events for the next 12 months.', 'meeting-reminder' ),
			'autogen_event_title'   => __( 'Title used for automatically created monthly events.', 'meeting-reminder' ),
			'autogen_start_time'    => __( 'Local site time used for automatically created monthly events.', 'meeting-reminder' ),
			'autogen_duration_minutes' => __( 'Duration used for automatically created monthly events.', 'meeting-reminder' ),
			'autogen_zoom_link'     => __( 'Optional Zoom link included in automatically created event content.', 'meeting-reminder' ),
			'autogen_zoom_id'       => __( 'Optional Zoom meeting ID included in automatically created event content.', 'meeting-reminder' ),
			'autogen_zoom_passcode' => __( 'Optional Zoom passcode included in automatically created event content.', 'meeting-reminder' ),
		);

		return isset( $help[ $key ] ) ? '<p class="description">' . esc_html( $help[ $key ] ) . '</p>' : '';
	}

	private function format_result( $data, $success_message ) {
		if ( is_wp_error( $data ) ) {
			return array(
				'type'    => 'error',
				'message' => $data->get_error_message(),
			);
		}

		return array(
			'type'    => 'success',
			'message' => $success_message,
			'data'    => $data,
		);
	}

	private function store_result( $result ) {
		set_transient( self::ACTION_RESULT_KEY . get_current_user_id(), $result, MINUTE_IN_SECONDS * 10 );
	}

	private function get_result() {
		$key    = self::ACTION_RESULT_KEY . get_current_user_id();
		$result = get_transient( $key );

		if ( false === $result ) {
			return null;
		}

		delete_transient( $key );

		return is_array( $result ) ? $result : null;
	}

	private function get_page_url() {
		return admin_url( 'options-general.php?page=' . self::MENU_SLUG );
	}

	private function download_ics( $settings ) {
		$preview = $this->scheduler->preview_ics( $settings );

		if ( is_wp_error( $preview ) ) {
			$this->store_result(
				array(
					'type'    => 'error',
					'message' => $preview->get_error_message(),
				)
			);

			wp_safe_redirect( $this->get_page_url() );
			exit;
		}

		if ( empty( $preview['ics']['content'] ) ) {
			$message = ! empty( $preview['ics']['error'] ) ? (string) $preview['ics']['error'] : __( 'No ICS content was available to download.', 'meeting-reminder' );
			$this->store_result(
				array(
					'type'    => 'error',
					'message' => $message,
					'data'    => $preview,
				)
			);

			wp_safe_redirect( $this->get_page_url() );
			exit;
		}

		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( (string) $preview['ics']['filename'] ) . '"' );
		echo $preview['ics']['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
