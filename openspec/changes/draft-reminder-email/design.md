## Context

GapNext WP autosaves form progress to `gapnext_submissions` with `status = 'draft'`. Drafts are restored when the user reopens the same audit link. However, there is no proactive notification — if a user closes the browser and forgets, the draft sits idle indefinitely.

The plugin already sends a styled HTML email on final submission (in `class-ajax.php::send_notification`). The notification email system and WP Cron are both available.

## Goals / Non-Goals

**Goals:**
- Send a single reminder email 24h after the last draft save, if the form hasn't been submitted
- Each new autosave resets the 24h timer (so users actively working don't get spammed)
- Cancel the reminder when the form is submitted
- Provide an admin toggle to enable/disable the feature
- Email is bilingual (IT/EN) based on the audit's language setting

**Non-Goals:**
- Multiple follow-up reminders (just one 24h reminder)
- Custom delay configuration (hardcoded at 24h for simplicity)
- Reminder emails for consultants (only the contact person filling the form)
- Tracking whether the reminder email was opened/clicked

## Decisions

### 1. New class `GapNext_Draft_Reminder`

All cron logic, scheduling, and email building live in a single new class. It registers:
- The cron hook `gapnext_send_draft_reminder`
- A static method to schedule/reschedule reminders
- A static method to cancel reminders
- The cron callback that builds and sends the email

**Why a separate class?** Keeps `class-ajax.php` slim — the AJAX handlers just call `GapNext_Draft_Reminder::schedule()` and `::cancel()` at the right moments.

### 2. WP Cron with `wp_schedule_single_event`

Use `wp_schedule_single_event( time() + 86400, 'gapnext_send_draft_reminder', [ $draft_id ] )` where `$draft_id` is the submission ID. On each autosave:
1. Unschedule any existing event for that draft ID
2. Schedule a new one 24h from now

This naturally implements the "reset on every save" behavior.

**Why not a recurring cron?** A recurring job would need to scan all drafts on every run. A single event per draft is more efficient and self-cleaning.

### 3. Cron args = draft submission ID

The cron event passes `[ $draft_id ]` as args. When the callback fires:
1. Load the submission row by ID
2. Check `status` — if it's no longer `'draft'`, do nothing (already submitted)
3. Check that `contact_email` is non-empty
4. Load the audit to get the language and the checklist page URL
5. Build and send the email

This is safe against race conditions — if the form was submitted between scheduling and firing, the status check catches it.

### 4. Email content

Bilingual HTML email matching the existing notification email style:
- Subject: `[GapNext] Complete your Gap Analysis / Completa la tua Gap Analysis`
- Body: A brief message saying "You started a gap analysis for [standard] — your progress is saved. Click below to continue."
- CTA button linking to the checklist page with `?audit=UUID`
- Company name and standard shown for context

### 5. Admin setting

New option `gapnext_draft_reminder_enabled` (default: `1`). Checkbox in the Settings page under the Notification Email field. When disabled, `schedule()` is a no-op.

## Risks / Trade-offs

- **WP Cron reliability**: WP Cron is trigger-based (fires on page visits). On low-traffic sites, the reminder may fire later than exactly 24h. This is acceptable for a non-critical reminder email.
- **Email deliverability**: Relies on `wp_mail()` — same as the existing notification emails. If the site has SMTP configured, reminders will benefit from it too.
- **One reminder per draft**: If the user never returns after the first reminder, they won't get another. This is intentional to avoid being spammy. A future enhancement could add a second reminder at 72h.
