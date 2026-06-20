<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meeting_Reminder_Plugin {
	private $scheduler;
	private $admin;

	public function __construct() {
		$logger        = new Meeting_Reminder_Logger();
		$state         = new Meeting_Reminder_State();
		$tec           = new Meeting_Reminder_TEC_Client( $logger );
		$ics_generator = new Meeting_Reminder_Ics_Generator();
		$ics_service   = new Meeting_Reminder_Ics_Attachment_Service( $ics_generator, $logger );
		$renderer      = new Meeting_Reminder_Mail_Renderer();
		$sender        = new Meeting_Reminder_Mail_Sender();

		$this->scheduler = new Meeting_Reminder_Scheduler( $tec, $ics_service, $renderer, $sender, $state, $logger );
		$this->admin     = new Meeting_Reminder_Admin( $this->scheduler, $logger );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( Meeting_Reminder_Installer::CRON_HOOK, array( $this, 'run_cron' ) );
		add_action( 'init', array( $this, 'ensure_cron' ) );

		if ( is_admin() ) {
			$this->admin->hooks();
		}
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'cso-meeting-reminder', false, dirname( plugin_basename( MEETING_REMINDER_FILE ) ) . '/languages' );
	}

	public function ensure_cron() {
		Meeting_Reminder_Installer::register_cron();
	}

	public function run_cron() {
		$this->scheduler->run( 'cron' );
	}
}
