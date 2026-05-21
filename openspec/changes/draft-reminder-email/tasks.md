## 1. Core Class

- [x] 1.1 Create `includes/class-draft-reminder.php` with `GapNext_Draft_Reminder` class — register cron hook `gapnext_send_draft_reminder` in constructor
- [x] 1.2 Implement `schedule( int $draft_id, string $contact_email )` — unschedule existing event for this draft ID, then schedule a new single event 24h from now (only if feature enabled and email non-empty)
- [x] 1.3 Implement `cancel( int $draft_id )` — unschedule the cron event for this draft ID
- [x] 1.4 Implement cron callback `send_reminder( int $draft_id )` — load submission, verify still draft, load audit for language/UUID, build and send bilingual HTML email with resume link

## 2. Email Template

- [x] 2.1 Build the HTML email body in the cron callback — styled consistently with existing notification email (blue header, CTA button with audit link, company name, standard name)
- [x] 2.2 Implement bilingual subject and body text (IT: "Completa la tua Gap Analysis" / EN: "Complete your Gap Analysis")

## 3. Hook into Autosave & Submit

- [x] 3.1 In `class-ajax.php` `handle_autosave()` — after successful draft save, call `GapNext_Draft_Reminder::schedule( $draft_id, $contact_email )`
- [x] 3.2 In `class-ajax.php` `handle_submit()` — after successful submission, call `GapNext_Draft_Reminder::cancel( $draft_id )` (when promoting a draft)

## 4. Admin Setting

- [x] 4.1 Add `gapnext_draft_reminder_enabled` default option in `class-installer.php` `set_defaults()` (default: `1`)
- [x] 4.2 Add checkbox field in `class-admin.php` `render_settings_page()` under the Notification Email row
- [x] 4.3 Save the setting in `class-admin.php` `save_settings()`

## 5. Bootstrap

- [x] 5.1 Instantiate `GapNext_Draft_Reminder` in `gapnext-wp.php` boot sequence (to register the cron hook)
