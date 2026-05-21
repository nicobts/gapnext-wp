## ADDED Requirements

### Requirement: Schedule reminder on autosave
The system SHALL schedule a draft reminder email 24 hours after each autosave, provided the draft has a valid `contact_email` and the reminder feature is enabled in settings.

#### Scenario: First autosave schedules a reminder
- **WHEN** a draft is autosaved for the first time with a non-empty `contact_email`
- **THEN** the system SHALL schedule a cron event to send a reminder email 24 hours from now, passing the draft submission ID

#### Scenario: Subsequent autosave reschedules the reminder
- **WHEN** a draft is autosaved again (same draft ID)
- **THEN** the system SHALL cancel any existing scheduled reminder for that draft ID and schedule a new one 24 hours from now

#### Scenario: Autosave without contact email does not schedule
- **WHEN** a draft is autosaved with an empty `contact_email`
- **THEN** the system SHALL NOT schedule a reminder email

#### Scenario: Feature disabled in settings
- **WHEN** the admin setting `gapnext_draft_reminder_enabled` is `0`
- **THEN** the system SHALL NOT schedule any reminder emails on autosave

### Requirement: Cancel reminder on submission
The system SHALL cancel any pending draft reminder when the form is successfully submitted.

#### Scenario: Form submitted cancels pending reminder
- **WHEN** a submission is finalized (status changes from `draft` to `submitted`)
- **THEN** the system SHALL unschedule any cron event for `gapnext_send_draft_reminder` with that draft ID

### Requirement: Send reminder email
When the cron event fires, the system SHALL send a bilingual HTML reminder email to the draft's `contact_email` with a link to resume the form.

#### Scenario: Reminder sent for pending draft
- **WHEN** the cron callback fires for a draft ID and the submission still has `status = 'draft'` and a valid `contact_email`
- **THEN** the system SHALL send an HTML email to `contact_email` containing the audit resume link, the standard name, and the company name

#### Scenario: Draft already submitted when cron fires
- **WHEN** the cron callback fires for a draft ID but the submission's status is no longer `'draft'`
- **THEN** the system SHALL NOT send an email

#### Scenario: Email language matches audit language
- **WHEN** the reminder email is sent for a draft whose audit language is `'it'`
- **THEN** the email subject and body text SHALL be in Italian

#### Scenario: Email language for English audit
- **WHEN** the reminder email is sent for a draft whose audit language is `'en'`
- **THEN** the email subject and body text SHALL be in English

### Requirement: Admin setting to enable/disable reminders
The system SHALL provide a setting in the GapNext Settings page to enable or disable draft reminder emails.

#### Scenario: Setting is enabled by default
- **WHEN** the plugin is activated for the first time
- **THEN** the `gapnext_draft_reminder_enabled` option SHALL default to `1` (enabled)

#### Scenario: Admin disables reminders
- **WHEN** the admin unchecks the draft reminder setting and saves
- **THEN** the system SHALL store `gapnext_draft_reminder_enabled` as `0` and no new reminders SHALL be scheduled

#### Scenario: Admin re-enables reminders
- **WHEN** the admin checks the draft reminder setting and saves
- **THEN** the system SHALL store `gapnext_draft_reminder_enabled` as `1` and new autosaves SHALL schedule reminders again
