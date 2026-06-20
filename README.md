# Meeting Reminder

`Meeting Reminder` is a production-oriented WordPress plugin for Canberra AA CSO committee meeting reminders. It uses The Events Calendar REST API as the source of truth, renders multipart text and HTML emails, sends through `wp_mail()`, records operational logs in custom tables, and stores send-state to prevent duplicate reminders.

## File Tree

```text
meeting-reminder/
|-- meeting-reminder.php
|-- uninstall.php
|-- README.md
|-- includes/
|   |-- class-meeting-reminder-admin.php
|   |-- class-meeting-reminder-installer.php
|   |-- class-meeting-reminder-logger.php
|   |-- class-meeting-reminder-mail-renderer.php
|   |-- class-meeting-reminder-mail-sender.php
|   |-- class-meeting-reminder-plugin.php
|   |-- class-meeting-reminder-scheduler.php
|   |-- class-meeting-reminder-settings.php
|   |-- class-meeting-reminder-state.php
|   |-- class-meeting-reminder-tec-client.php
|   |-- Calendar/
|   |   |-- IcsAttachmentService.php
|   |   |-- IcsGenerator.php
|-- templates/
|   |-- default-html.html
|   |-- default-subject.txt
|   |-- default-text.txt
|-- tests/
|   |-- manual-regression-checklist.md
```

## Setup

1. Copy the plugin folder into `wp-content/plugins/meeting-reminder`.
2. Activate the plugin in WordPress.
3. Visit `Settings > CSO Reminder`.
4. Confirm the TEC endpoint, keyword filter, recipient list, lead time, and send time.
   By default, the TEC endpoint must be hosted on the same WordPress site; enable the external endpoint option only when that is intentional.
5. Use the manual action buttons to preview the next event, preview the rendered email, send a test message, or run the scheduled logic immediately.
6. If the plugin should create monthly CSO meeting events, enable event autogeneration and configure the event title, start time, duration, and optional Zoom details.
7. Ensure server-side cron triggers `wp-cron.php` regularly.

## Data Flow

1. The scheduled event `meeting_reminder_run` is registered on activation and checked hourly by WordPress cron.
2. The scheduler asks the TEC client for the next qualifying event from `/wp-json/tribe/events/v1/events`.
3. The scheduler applies the configured keyword filter and calculates whether the current local time has reached the send window.
4. If the reminder is due, the scheduler atomically reserves the event/stage before sending so overlapping cron runs cannot both send the same reminder.
5. The renderer builds subject, text, and HTML content from templates.
6. If enabled, the ICS attachment service generates a temporary `.ics` file from normalized event data and adds it to the outgoing message.
7. The mail sender delivers the message through `wp_mail()` with multipart HTML and plain-text support.
8. Temporary ICS files are cleaned up after send attempts, and ICS success/failure is written to the log table.
9. Send-state is finalized in `wp_meeting_reminder_state`, and logs are written to `wp_meeting_reminder_logs`.

## SQL Schema

```sql
CREATE TABLE wp_meeting_reminder_logs (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	level varchar(20) NOT NULL,
	message text NOT NULL,
	context longtext NULL,
	created_at_gmt datetime NOT NULL,
	PRIMARY KEY (id),
	KEY level (level),
	KEY created_at_gmt (created_at_gmt)
);

CREATE TABLE wp_meeting_reminder_state (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	event_id varchar(191) NOT NULL,
	reminder_key varchar(100) NOT NULL,
	sent_at_gmt datetime NOT NULL,
	event_start_utc datetime NULL,
	recipient_hash varchar(64) NULL,
	payload longtext NULL,
	PRIMARY KEY (id),
	UNIQUE KEY event_reminder (event_id, reminder_key),
	KEY sent_at_gmt (sent_at_gmt)
);
```

WordPress prefixes are applied dynamically, so the actual table names use your site prefix instead of always `wp_`.

## Extension Points

- `meeting_reminder_reminder_stages`
- `meeting_reminder_event_matches`
- `meeting_reminder_template_vars`
- `meeting_reminder_email_payload`
- `meeting_reminder_mail_attachments`
- `meeting_reminder_after_send`

## ICS Support

The plugin can optionally attach a generated `.ics` file to scheduled reminders and manual test sends. The ICS data includes:

- `VCALENDAR`
- `VEVENT`
- `UID`
- `DTSTAMP`
- `DTSTART`
- `DTEND`
- `SUMMARY`
- `DESCRIPTION`
- `LOCATION`
- `URL`
- `ORGANIZER`

The generator uses UTC `DTSTART` and `DTEND`, escapes ICS text fields, and writes CRLF line endings. Admin tools also let you preview and download the generated ICS content before sending.
