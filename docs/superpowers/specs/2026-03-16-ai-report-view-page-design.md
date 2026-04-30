# AI Report Section — View Page Design

## Goal

Move AI report generation out of the submissions list and into a dedicated section at the bottom of the single submission view page, with animated progress log feedback.

## Architecture

No FastAPI or backend changes. All work is in the WordPress plugin: PHP template changes to `class-audit-manager.php` and JS changes to `gapnext-admin-ai.js`. The existing AJAX action (`wp_ajax_gapnext_ai_generate`) is reused unchanged.

**Tech Stack:** PHP 7.4+, jQuery, WordPress AJAX, CSS animations

---

## Section 1: Submissions List Page (`render_submissions_page`)

Comment out (do not delete) the following items in the Actions column per row:
- JSON download button
- "Generate AI Report" button (`.gapnext-ai-generate`)
- "Download AI Report" link

All other actions remain visible: View, PDF, CSV, MD, Delete.

---

## Section 2: Single Submission View Page (`render_submission_view`)

### 2a. Export buttons row
Comment out (do not delete) the JSON download button. Remaining export buttons: PDF, CSV, MD.

### 2b. AI Report section (new, at the bottom — after checklist table)

Regenerating an existing report is out of scope for this change. No regenerate button is shown when a report already exists.

#### State: No report generated yet
```
┌─────────────────────────────────────────────┐
│  🤖 AI Report                               │
│                                             │
│  Generate a professional AI-powered         │
│  compliance report for this submission.     │
│                                             │
│  [ Generate AI Report ]                     │
└─────────────────────────────────────────────┘
```

#### State: Generating (button clicked)
The button is disabled and the progress log panel appears below it:
```
┌─────────────────────────────────────────────┐
│  🤖 AI Report                               │
│                                             │
│  [ Generating… ] (disabled)                 │
│                                             │
│  ┌─ Progress ───────────────────────────┐   │
│  │ ✓ Preparing checklist data…          │   │
│  │ ✓ Sending to AI pipeline…            │   │
│  │ ⏳ AI is analyzing findings… ···     │   │  ← active step with pulsing dots
│  │   Generating report document…        │   │  ← pending (greyed)
│  └──────────────────────────────────────┘   │
└─────────────────────────────────────────────┘
```

Step timing (while AJAX is in flight):
- Step 1 "Preparing checklist data…" — appears immediately (0ms)
- Step 2 "Sending to AI pipeline…" — appears after 800ms
- Step 3 "AI is analyzing compliance findings…" — appears after 2500ms
- Step 4 "Generating report document…" — appears after 5000ms

Each step gets a ✓ checkmark when the next step activates. If the AJAX response arrives before all steps have played, all remaining pending steps are ticked ✓ synchronously in the same callback with no additional delay, then the success state is shown.

#### State: Success
```
│  ✓ Preparing checklist data…                │
│  ✓ Sending to AI pipeline…                  │
│  ✓ AI is analyzing compliance findings…     │
│  ✓ Generating report document…              │
│  ✅ Report generated successfully!           │
│                                             │
│  [ ⬇ Download AI Report ]  (primary button) │
```

#### State: Error
```
│  ✓ Preparing checklist data…                │
│  ✓ Sending to AI pipeline…                  │
│  ✗ AI is analyzing compliance findings…     │  ← red
│  ❌ Generation failed: <error message>       │
│                                             │
│  [ Try Again ]                              │
```

Clicking "Try Again" returns the section to the "State: No report generated yet" appearance — the log panel is removed, all timeouts are cleared, and the "Generate AI Report" button is re-enabled and visible.

#### State: Report already exists (page load)
If `$sub->ai_report_url` is set, render directly without generate button:
```
┌─────────────────────────────────────────────┐
│  🤖 AI Report                    ● Available │
│                                             │
│  [ ⬇ Download AI Report ]                  │
└─────────────────────────────────────────────┘
```

---

## Section 3: Script Enqueue

The `gapnext-admin-ai.js` enqueue block currently lives inside `render_submissions_page()`. It must also be called from inside `render_submission_view()` — either by moving the enqueue to before the early-return routing at the top of the main page handler, or by adding a separate enqueue call at the top of `render_submission_view()`.

When enqueueing on the view page, add `submissionId` to the `wp_localize_script` data:
```php
'submissionId' => (int) $_GET['view_sub'],
```
This key is only meaningful on the view page. On the list page, `submissionId` is not present in the localized data (or can be `0`/absent).

The JS guard for the view-page init block is element existence: if `#gapnext-ai-generate-view` is not in the DOM, the view-page handler does nothing. This prevents the view-page handler from firing on the list page even if the script is loaded on both pages.

---

## Section 4: JS (`gapnext-admin-ai.js`)

Add a second init block for the view page:
- Bind click on `#gapnext-ai-generate-view`
- Guard: if `$('#gapnext-ai-generate-view').length === 0`, do nothing
- Disable button, change text to "Generating…", show log panel, start step animation with `setTimeout` chain
- Fire AJAX (`wp_ajax_gapnext_ai_generate`) with `submission_id: gapnextAI.submissionId`
- On AJAX success: clear all pending timeouts, tick all remaining steps ✓ synchronously, append success line, replace generate button with download link (`resp.data.download_url + '?token=' + encodeURIComponent(gapnextAI.apiKey)`)
- On AJAX error: clear all pending timeouts, mark the current active step ✗ in red, append error message line, show "Try Again" button
- "Try Again" click: clear log panel from DOM, clear any remaining timeouts, restore original "Generate AI Report" button to enabled state

The existing list-page button handler (`.gapnext-ai-generate` click delegation) remains unchanged.

---

## Testing

1. Submissions list page: confirm JSON, Generate AI Report, Download AI Report buttons are not visible (commented out in PHP)
2. Single submission view: confirm JSON button not visible in export row
3. Single submission view (no report): click Generate — progress steps animate in sequence, download button appears on success with `?token=` in URL
4. Single submission view (no report): simulate error — current step turns red, error message shown, clicking Try Again returns to initial state with generate button re-enabled
5. Single submission view (report exists): page loads showing "Available" badge + download button, no generate button
6. Single submission view: script loads correctly (no JS errors on list page or view page)
