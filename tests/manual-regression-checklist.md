# Manual Regression Checklist

Run these checks on a WordPress site with The Events Calendar active.

## Reminder Sending

1. Configure a same-site Events Calendar endpoint and confirm "Preview Next Event" finds a matching future event.
2. Configure an external endpoint while "Allow external events endpoint" is disabled and confirm preview is blocked.
3. Enable "Allow external events endpoint" and confirm the external endpoint can be queried.
4. Trigger two "Run Scheduled Logic Now" requests for the same due event as closely together as possible and confirm only one reminder sends.
5. Confirm a failed `wp_mail()` attempt releases the send claim so a later retry can send.
6. Confirm a successful send writes a state row with payload status `sent`.

## Event Lookup

1. Create more than 25 upcoming non-matching events before a matching CSO event.
2. Confirm the matching event is still found through paginated lookup.

## Templates and ICS

1. Preview the rendered email during AEST and AEDT periods and confirm `{{event_timezone}}` renders the event-time abbreviation.
2. Enable ICS attachments and preview/download an invite with non-ASCII characters in the event title, description, and venue.
3. Send a test email with ICS enabled and confirm temporary files are removed after the send attempt.

## Autogeneration

1. Leave event autogeneration disabled and confirm the daily autogen cron hook is cleared.
2. Enable event autogeneration, configure event title/time/duration and optional Zoom details, then run the autogen cron hook.
3. Confirm missing monthly events are created without exposing Zoom details unless those fields are configured.
4. Deactivate or uninstall the plugin and confirm both reminder and autogen cron hooks are cleared.
