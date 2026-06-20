<?php
/**
 * Plugin Name: Meeting Reminder
 * Plugin URI: https://aacanberra.org/
 * Description: Sends admin-managed reminder emails for Canberra AA CSO committee meetings using The Events Calendar REST API.
 * Version: 1.0.0
 * Author: OpenAI Codex
 * License: GPL-2.0-or-later
 * Text Domain: meeting-reminder
 * Requires at least: 6.4
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MEETING_REMINDER_VERSION', '1.0.0' );
define( 'MEETING_REMINDER_FILE', __FILE__ );
define( 'MEETING_REMINDER_PATH', plugin_dir_path( __FILE__ ) );
define( 'MEETING_REMINDER_URL', plugin_dir_url( __FILE__ ) );

require_once MEETING_REMINDER_PATH . 'includes/class-meeting-reminder-installer.php';
require_once MEETING_REMINDER_PATH . 'includes/class-meeting-reminder-settings.php';
require_once MEETING_REMINDER_PATH . 'includes/class-meeting-reminder-logger.php';
require_once MEETING_REMINDER_PATH . 'includes/class-meeting-reminder-state.php';
require_once MEETING_REMINDER_PATH . 'includes/meeting-autogen.php';
require_once MEETING_REMINDER_PATH . 'includes/class-meeting-reminder-tec-client.php';
require_once MEETING_REMINDER_PATH . 'includes/Calendar/IcsGenerator.php';
require_once MEETING_REMINDER_PATH . 'includes/Calendar/IcsAttachmentService.php';
require_once MEETING_REMINDER_PATH . 'includes/class-meeting-reminder-mail-renderer.php';
require_once MEETING_REMINDER_PATH . 'includes/class-meeting-reminder-mail-sender.php';
require_once MEETING_REMINDER_PATH . 'includes/class-meeting-reminder-scheduler.php';
require_once MEETING_REMINDER_PATH . 'includes/class-meeting-reminder-admin.php';
require_once MEETING_REMINDER_PATH . 'includes/class-meeting-reminder-plugin.php';

register_activation_hook( __FILE__, array( 'Meeting_Reminder_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Meeting_Reminder_Installer', 'deactivate' ) );

function meeting_reminder() {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new Meeting_Reminder_Plugin();
	}

	return $plugin;
}

meeting_reminder();
