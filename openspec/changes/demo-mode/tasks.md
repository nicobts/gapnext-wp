## 1. Admin Settings & Audit Creation

- [x] 1.1 Add `gapnext_demo_question_limit` default option (default: `15`) in `class-installer.php` `set_defaults()`
- [x] 1.2 Add "Demo Question Limit" number input field in `class-admin.php` `render_settings_page()` after the Draft Reminder Email row
- [x] 1.3 Save the `gapnext_demo_question_limit` setting in `class-admin.php` `save_settings()`
- [x] 1.4 Add `demo` option to the access mode dropdown in `class-admin.php` settings (Default Access Mode) and in `class-audit-manager.php` audit creation form
- [x] 1.5 Add `demo` to the access mode validation in `class-audit-manager.php` `create_audit()`

## 2. Demo Question Slicing in Checklist

- [x] 2.1 In `class-checklist.php` `render()`, detect `$audit->access_mode === 'demo'` and slice `$standard['clauses']` to keep only the first N level-2 questions plus their parent level-1 headers
- [x] 2.2 Pass `is_demo` flag and `full_question_count` (total level-2 count from unsliced standard) to `wp_localize_script` in `GapNextWPForm`
- [x] 2.3 Pass `is_demo` flag to the form view so it can render the demo banner and force self-assessment mode

## 3. Demo Form Behavior

- [x] 3.1 In `includes/views/form.php`, add a demo banner div (bilingual: "This is a demo — N of X questions") shown when `is_demo` is true
- [x] 3.2 In `includes/views/form.php`, when `is_demo` is true, hide the mode selector step and hardcode `filling_mode` hidden input to `'self'`
- [x] 3.3 In `assets/gapnext-wp.js`, when `GapNextWPForm.is_demo` is true, skip the mode selector step (start at step 1), apply self-assessment mode, and hide consultant fieldset
- [x] 3.4 Add CSS for the demo banner in `assets/gapnext-wp.css`

## 4. Demo Submission Handling

- [x] 4.1 In `class-ajax.php` `handle_submit()`, detect demo audit (`access_mode = 'demo'`) and set `status = 'demo'` instead of `'submitted'`, force `filling_mode = 'self'`
- [x] 4.2 In `class-ajax.php` `handle_submit()`, cancel draft reminder on demo submission (same as regular submit)

## 5. Demo PDF Watermark

- [x] 5.1 Add `public $is_demo = false;` property to `GapNext_TCPDF` class in `class-gapnext-tcpdf.php`
- [x] 5.2 In `GapNext_TCPDF::Header()`, when `$this->is_demo` is true, draw a rotated semi-transparent "GapNext DEMO" text watermark using `StartTransform/Rotate/SetAlpha/Text/StopTransform`
- [x] 5.3 In `class-export-pdf.php` `build()`, set `$pdf->is_demo = true` when `$sub->status === 'demo'`
- [x] 5.4 In `class-export-pdf.php` `build_cover_html()`, add "DEMO" label below the report title when submission is demo
- [x] 5.5 In `class-export-pdf.php` `build_checklist_html()`, when demo, filter clauses to only show questions that exist in `$answers` (skip unanswered questions from full standard)

## 6. Demo Results Page

- [x] 6.1 In `class-results.php`, detect `$sub->status === 'demo'` and pass `is_demo` flag plus `full_question_count` to the results view
- [x] 6.2 In `includes/views/results.php`, add a demo banner at the top when `is_demo` is true (bilingual: "Demo report — N of X questions evaluated")
- [x] 6.3 In `includes/views/results.php`, add an upgrade CTA section after the score summary for demo results (bilingual: "Want the full analysis? Contact us")
- [x] 6.4 Add CSS for the demo banner and CTA in `assets/gapnext-wp.css` (or inline if results page has its own styles)

## 7. Demo Badge in Admin Submissions

- [x] 7.1 In `class-audit-manager.php` submissions table rendering, add a "Demo" badge (styled span) when `$sub->status === 'demo'`
