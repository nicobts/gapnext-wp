## Why

Gap analysis checklists often take hours or days to complete. When a user starts filling out the form and closes the browser, their draft is saved server-side but there is no mechanism to remind them to come back and finish. Without a nudge, incomplete audits are easily forgotten — leading to lost work and stalled consultant engagements.

## What Changes

- **Draft reminder email**: When a form is autosaved with a valid `contact_email`, schedule a reminder email to be sent 24 hours after the last save — but only if the submission hasn't been completed yet. The email contains the audit link so the user can click and resume exactly where they left off.
- **Reschedule on every save**: Each autosave resets the 24h countdown, so the reminder is always 24h after the *last* interaction — not 24h after the first save.
- **Cancel on submit**: When the form is finally submitted (`status = 'submitted'`), any pending reminder cron event is cleared.
- **Admin setting**: A toggle in GapNext Settings to enable/disable draft reminder emails (enabled by default).

## Capabilities

### New Capabilities
- `draft-reminder`: Scheduling, sending, and cancelling draft reminder emails via WP Cron, including the email template and admin setting

### Modified Capabilities
- None

## Impact

- **Files modified**: `includes/class-ajax.php` (hook into autosave and submit to schedule/cancel cron), `includes/class-admin.php` (new setting toggle), `includes/class-installer.php` (default option)
- **New file**: `includes/class-draft-reminder.php` — encapsulates cron scheduling, email building, and the cron callback
- **WP Cron**: Uses `wp_schedule_single_event` / `wp_unschedule_event` — no external dependencies
- **No database changes**: Uses WP options for the setting and cron's built-in event storage
