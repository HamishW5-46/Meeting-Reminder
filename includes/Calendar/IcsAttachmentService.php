<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meeting_Reminder_Ics_Attachment_Service {
	private $generator;
	private $logger;

	public function __construct( Meeting_Reminder_Ics_Generator $generator, Meeting_Reminder_Logger $logger ) {
		$this->generator = $generator;
		$this->logger    = $logger;
	}

	public function build_attachment_package( $event, $settings ) {
		if ( empty( $settings['enable_ics_attachment'] ) ) {
			return array(
				'enabled'       => false,
				'filename'      => '',
				'content'       => '',
				'attachments'   => array(),
				'cleanup_files' => array(),
			);
		}

		$content = $this->generator->generate( $event, $settings );

		if ( is_wp_error( $content ) ) {
			$this->logger->log(
				'error',
				'ICS generation failed.',
				array(
					'event_id' => isset( $event['id'] ) ? $event['id'] : '',
					'error'    => $content->get_error_message(),
				)
			);

			return array(
				'enabled'       => true,
				'filename'      => '',
				'content'       => '',
				'attachments'   => array(),
				'cleanup_files' => array(),
				'error'         => $content,
			);
		}

		$filename = $this->generator->build_filename( $event );
		$temp_dir = trailingslashit( get_temp_dir() );
		$work_dir = $temp_dir . 'cso-reminder-' . wp_generate_password( 12, false, false );

		if ( ! wp_mkdir_p( $temp_dir ) ) {
			$error = new WP_Error( 'meeting_reminder_ics_tempfile', __( 'The ICS temporary file could not be created.', 'cso-meeting-reminder' ) );
			$this->logger->log(
				'error',
				'ICS temporary file creation failed.',
				array(
					'event_id' => isset( $event['id'] ) ? $event['id'] : '',
					'temp_dir' => $temp_dir,
				)
			);

			return array(
				'enabled'       => true,
				'filename'      => $filename,
				'content'       => $content,
				'attachments'   => array(),
				'cleanup_files' => array(),
				'error'         => $error,
			);
		}

		if ( ! wp_mkdir_p( $work_dir ) ) {
			$error = new WP_Error( 'meeting_reminder_ics_tempdir', __( 'The ICS working directory could not be created.', 'cso-meeting-reminder' ) );
			$this->logger->log(
				'error',
				'ICS working directory creation failed.',
				array(
					'event_id' => isset( $event['id'] ) ? $event['id'] : '',
					'work_dir' => $work_dir,
				)
			);

			return array(
				'enabled'       => true,
				'filename'      => $filename,
				'content'       => $content,
				'attachments'   => array(),
				'cleanup_files' => array(),
				'cleanup_dirs'  => array(),
				'error'         => $error,
			);
		}

		$tempfile = trailingslashit( $work_dir ) . $filename;
		$written = file_put_contents( $tempfile, $content );

		if ( false === $written ) {
			@unlink( $tempfile );
			@rmdir( $work_dir );
			$error = new WP_Error( 'meeting_reminder_ics_write_failed', __( 'The ICS temporary file could not be written.', 'cso-meeting-reminder' ) );
			$this->logger->log(
				'error',
				'ICS temporary file write failed.',
				array(
					'event_id' => isset( $event['id'] ) ? $event['id'] : '',
					'tempfile' => $tempfile,
				)
			);

			return array(
				'enabled'       => true,
				'filename'      => $filename,
				'content'       => $content,
				'attachments'   => array(),
				'cleanup_files' => array(),
				'cleanup_dirs'  => array(),
				'error'         => $error,
			);
		}

		$this->logger->log(
			'info',
			'ICS generated successfully.',
			array(
				'event_id' => isset( $event['id'] ) ? $event['id'] : '',
				'filename' => $filename,
			)
		);

		return array(
			'enabled'       => true,
			'filename'      => $filename,
			'content'       => $content,
			'attachments'   => array( $tempfile ),
			'cleanup_files' => array( $tempfile ),
			'cleanup_dirs'  => array( $work_dir ),
		);
	}

	public function cleanup( $package ) {
		if ( ! empty( $package['cleanup_files'] ) && is_array( $package['cleanup_files'] ) ) {
			foreach ( $package['cleanup_files'] as $filepath ) {
				if ( is_string( $filepath ) && '' !== $filepath && file_exists( $filepath ) ) {
					@unlink( $filepath );
				}
			}
		}

		if ( empty( $package['cleanup_dirs'] ) || ! is_array( $package['cleanup_dirs'] ) ) {
			return;
		}

		foreach ( $package['cleanup_dirs'] as $directory ) {
			if ( is_string( $directory ) && '' !== $directory && is_dir( $directory ) ) {
				@rmdir( $directory );
			}
		}
	}
}
