<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meeting_Reminder_Mail_Renderer {
	public function render( $event, $settings, $stage ) {
		$vars    = $this->build_template_vars( $event, $settings, $stage );
		$subject = strtr( (string) $settings['template_subject'], $vars );
		$text    = strtr( (string) $settings['template_text'], $vars );
		$html    = strtr( (string) $settings['template_html'], $vars );

		return array(
			'subject'     => $subject,
			'text'        => $text,
			'html'        => $html,
			// Reserved for future ICS or supporting attachments.
			'attachments' => apply_filters( 'meeting_reminder_mail_attachments', array(), $event, $stage, $settings ),
			'vars'        => $vars,
		);
	}

	private function build_template_vars( $event, $settings, $stage ) {
		$timezone = wp_timezone();
		$start    = $this->parse_local_date( (string) $event['start_date'], $timezone );
		$date     = $start ? wp_date( 'l, j F Y', $start->getTimestamp(), $timezone ) : '';
		$time     = $start ? wp_date( 'g:i a', $start->getTimestamp(), $timezone ) : '';
		$tz_label = $start ? wp_date( 'T', $start->getTimestamp(), $timezone ) : (string) $settings['timezone_hint'];

		$vars = array(
			'{{site_name}}'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{{event_title}}'    => (string) $event['title'],
			'{{event_date}}'     => $date,
			'{{event_time}}'     => $time,
			'{{event_timezone}}' => $tz_label,
			'{{event_url}}'      => (string) $event['url'],
			'{{event_venue}}'    => (string) $event['venue_name'],
			'{{days_before}}'    => (string) $stage['days_before'],
			'{{event_summary}}'  => wp_strip_all_tags( (string) $event['description'] ),
		);

		// Filters can inject new placeholders without rewriting the renderer.
		return apply_filters( 'meeting_reminder_template_vars', $vars, $event, $settings, $stage );
	}

	private function parse_local_date( $value, $timezone ) {
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
