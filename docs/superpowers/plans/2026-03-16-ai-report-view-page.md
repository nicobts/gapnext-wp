# AI Report View Page Section — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hide AI report + JSON buttons from the submissions list, and add a dedicated AI Report section with animated progress log at the bottom of the single submission view page.

**Architecture:** PHP-only changes to comment out buttons in the list page; add enqueue + AI section HTML to the view page; JS extended with a second init block that runs a timed step animation while AJAX is in flight, resolving to success or error. No backend changes.

**Tech Stack:** PHP 7.4+, jQuery, WordPress AJAX

---

## File Map

- Modify: `includes/class-audit-manager.php`
  - Comment out 3 items in `render_submissions_page()` (~line 329 JSON, lines ~336-347 AI buttons)
  - Comment out JSON button in `render_submission_view()` export row (~line 424)
  - Add `wp_enqueue_script` + `wp_localize_script` at top of `render_submission_view()` (after the `!$sub` guard, before `$answers` ~line 365)
  - Add AI Report section HTML at bottom of `render_submission_view()` (before closing `</div>` ~line 679)
- Modify: `assets/gapnext-admin-ai.js`
  - Add view-page init block with animated steps, AJAX call, success/error/try-again logic

**Note on commenting out PHP template code:** PHP block comments (`/* */`) cannot contain `<?php` tags — the parser executes them anyway. Use `if (false):...endif;` to safely comment out mixed PHP/HTML template blocks.

---

## Chunk 1: PHP changes

### Task 1: Comment out buttons in the submissions list

**Files:**
- Modify: `includes/class-audit-manager.php` lines ~329, ~335–347

- [ ] **Step 1: Comment out the JSON button in the list actions column**

In `render_submissions_page()`, find this line (~line 329):
```php
                                <a href="<?php echo esc_url( add_query_arg( 'format', 'json', $base_export ) ); ?>" class="button button-small">JSON</a>
```
Replace it with:
```php
                                <?php if ( false ) : // JSON button — commented out, re-enable when needed ?>
                                <a href="<?php echo esc_url( add_query_arg( 'format', 'json', $base_export ) ); ?>" class="button button-small">JSON</a>
                                <?php endif; ?>
```

- [ ] **Step 2: Comment out the AI generate/download block in the list actions column**

Find this block (~lines 335–347):
```php
                                <?php if ( $ai_client->is_configured() ) : ?>
                                    <?php if ( ! empty( $sub->ai_report_url ) ) : ?>
                                        <a href="<?php echo esc_url( add_query_arg( 'token', get_option( 'gapnext_ai_api_key', '' ), $sub->ai_report_url ) ); ?>"
                                           target="_blank" class="button button-small button-primary">
                                            <?php esc_html_e( '&#x2B07; Download AI Report', 'gapnext-wp' ); ?>
                                        </a>
                                    <?php else : ?>
                                        <button type="button" class="button button-small gapnext-ai-generate"
                                                data-id="<?php echo esc_attr( $sub->id ); ?>">
                                            <?php esc_html_e( 'Generate AI Report', 'gapnext-wp' ); ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
```
Replace the entire block with:
```php
                                <?php if ( false ) : // AI Report buttons — commented out, managed from view page instead ?>
                                <?php if ( $ai_client->is_configured() ) : ?>
                                    <?php if ( ! empty( $sub->ai_report_url ) ) : ?>
                                        <a href="<?php echo esc_url( add_query_arg( 'token', get_option( 'gapnext_ai_api_key', '' ), $sub->ai_report_url ) ); ?>"
                                           target="_blank" class="button button-small button-primary">
                                            <?php esc_html_e( '&#x2B07; Download AI Report', 'gapnext-wp' ); ?>
                                        </a>
                                    <?php else : ?>
                                        <button type="button" class="button button-small gapnext-ai-generate"
                                                data-id="<?php echo esc_attr( $sub->id ); ?>">
                                            <?php esc_html_e( 'Generate AI Report', 'gapnext-wp' ); ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php endif; ?>
```

- [ ] **Step 3: Verify in browser**

Open `admin.php?page=gapnext-submissions`. Confirm the Actions column shows only: View, PDF, CSV, MD, Delete. No JSON button, no AI buttons.

---

### Task 2: View page — comment out JSON, add enqueue, add AI Report section

**Files:**
- Modify: `includes/class-audit-manager.php` — `render_submission_view()` method

- [ ] **Step 1: Comment out JSON in the export buttons row**

In `render_submission_view()`, find this line (~line 424):
```php
                <a href="<?php echo esc_url( add_query_arg( 'format', 'json', $base_export ) ); ?>" class="button">⬇ JSON</a>
```
Replace it with:
```php
                <?php if ( false ) : // JSON export — commented out, re-enable when needed ?>
                <a href="<?php echo esc_url( add_query_arg( 'format', 'json', $base_export ) ); ?>" class="button">⬇ JSON</a>
                <?php endif; ?>
```

- [ ] **Step 2: Add script enqueue at the top of render_submission_view()**

Find the beginning of the variable declarations in `render_submission_view()`, right after the `!$sub` guard. The first variable assignment line looks like:
```php
        $answers    = json_decode( $sub->answers,        true ) ?: [];
```
Add the enqueue block immediately before that line:
```php
        // Enqueue AI script for the view page
        $ai_client_view = new GapNext_AI_Client();
        if ( $ai_client_view->is_configured() ) {
            wp_enqueue_script(
                'gapnext-admin-ai',
                GAPNEXT_WP_URL . 'assets/gapnext-admin-ai.js',
                [ 'jquery' ],
                GAPNEXT_WP_VERSION,
                true
            );
            wp_localize_script( 'gapnext-admin-ai', 'gapnextAI', [
                'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
                'nonce'            => wp_create_nonce( 'gapnext_ai_generate' ),
                'apiKey'           => get_option( 'gapnext_ai_api_key', '' ),
                'submissionId'     => isset( $_GET['view_sub'] ) ? (int) $_GET['view_sub'] : 0,
                'generateText'     => __( 'Generate AI Report', 'gapnext-wp' ),
                'generatingText'   => __( 'Generating\u2026', 'gapnext-wp' ),
                'downloadText'     => __( '\u2b07 Download AI Report', 'gapnext-wp' ),
                'errorText'        => __( 'Generation failed. Try again.', 'gapnext-wp' ),
                'networkErrorText' => __( 'Network error. Check connection.', 'gapnext-wp' ),
            ] );
        }

```

- [ ] **Step 3: Add the AI Report section at the bottom of render_submission_view()**

Find the end of `render_submission_view()`. The last HTML is the checklist table followed by a closing `</div>` then `<?php` (~line 679):
```php
        </div>
        <?php
    }
```
Insert the AI Report section before that closing `</div>`:
```php
            <?php
            // ============================================================
            // AI REPORT SECTION
            // ============================================================
            $ai_client_section = new GapNext_AI_Client();
            if ( $ai_client_section->is_configured() ) :
            ?>
            <div style="margin-top:40px;padding-top:32px;border-top:2px solid #e5e7eb">
                <h2 style="font-size:18px;font-weight:700;color:#1d2327;margin:0 0 4px;display:flex;align-items:center;gap:8px">
                    🤖 <?php esc_html_e( 'AI Report', 'gapnext-wp' ); ?>
                    <?php if ( ! empty( $sub->ai_report_url ) ) : ?>
                        <span style="font-size:12px;font-weight:600;background:#dcfce7;color:#166534;border-radius:20px;padding:2px 10px;letter-spacing:.3px">
                            ● <?php esc_html_e( 'Available', 'gapnext-wp' ); ?>
                        </span>
                    <?php endif; ?>
                </h2>

                <?php if ( ! empty( $sub->ai_report_url ) ) : ?>
                    <!-- Report already exists: show download only -->
                    <p style="color:#6b7280;font-size:13px;margin:6px 0 16px">
                        <?php esc_html_e( 'An AI-generated compliance report is available for this submission.', 'gapnext-wp' ); ?>
                    </p>
                    <a href="<?php echo esc_url( add_query_arg( 'token', get_option( 'gapnext_ai_api_key', '' ), $sub->ai_report_url ) ); ?>"
                       target="_blank" class="button button-primary">
                        ⬇ <?php esc_html_e( 'Download AI Report', 'gapnext-wp' ); ?>
                    </a>

                <?php else : ?>
                    <!-- No report yet: show generate button + log panel -->
                    <p style="color:#6b7280;font-size:13px;margin:6px 0 16px">
                        <?php esc_html_e( 'Generate a professional AI-powered compliance report for this submission.', 'gapnext-wp' ); ?>
                    </p>
                    <button type="button" id="gapnext-ai-generate-view" class="button button-primary">
                        <?php esc_html_e( 'Generate AI Report', 'gapnext-wp' ); ?>
                    </button>

                    <!-- Progress log panel (hidden until generation starts) -->
                    <div id="gapnext-ai-log-panel" style="display:none;margin-top:20px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:20px 24px;max-width:520px">
                        <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px">
                            <?php esc_html_e( 'Progress', 'gapnext-wp' ); ?>
                        </div>
                        <div id="gapnext-ai-log-steps"></div>
                    </div>
                <?php endif; ?>
            </div>
            <style>
            @keyframes gapnext-pulse-dots {
                0%, 80%, 100% { opacity: 0; }
                40%           { opacity: 1; }
            }
            .gapnext-ai-dot {
                display: inline-block;
                width: 4px; height: 4px;
                border-radius: 50%;
                background: #6366f1;
                margin: 0 2px;
                animation: gapnext-pulse-dots 1.4s infinite ease-in-out;
            }
            .gapnext-ai-dot:nth-child(2) { animation-delay: .2s; }
            .gapnext-ai-dot:nth-child(3) { animation-delay: .4s; }
            .gapnext-ai-step {
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 13px;
                color: #374151;
                padding: 5px 0;
                opacity: .38;
                transition: opacity .25s;
            }
            .gapnext-ai-step.active  { opacity: 1; }
            .gapnext-ai-step.done    { opacity: 1; color: #374151; }
            .gapnext-ai-step.failed  { opacity: 1; color: #991b1b; }
            .gapnext-ai-step-icon    { font-size: 14px; width: 18px; text-align: center; flex-shrink: 0; }
            .gapnext-ai-step-text    { flex: 1; }
            </style>
            <?php endif; ?>
```

- [ ] **Step 4: Verify in browser**

Open a submission view (`admin.php?page=gapnext-submissions&view_sub=<id>`).
- Export buttons row shows PDF, CSV, MD only — no JSON.
- If no report exists: "Generate AI Report" button and description are visible at the bottom.
- If report exists: "● Available" badge + "⬇ Download AI Report" button visible at bottom.
- No JS errors in browser console.

---

## Chunk 2: JavaScript

### Task 3: Add view-page init block to gapnext-admin-ai.js

**Files:**
- Modify: `assets/gapnext-admin-ai.js`

- [ ] **Step 1: Replace the file contents**

Replace the entire contents of `assets/gapnext-admin-ai.js` with:

```javascript
/* global jQuery, gapnextAI */
jQuery(function ($) {
    'use strict';

    // ── List-page handler ─────────────────────────────────────────────────────
    // Handles .gapnext-ai-generate buttons in the submissions table.
    // (Currently those buttons are commented out in PHP, but handler stays for
    //  when they are re-enabled.)
    $(document).on('click', '.gapnext-ai-generate', function () {
        var $btn  = $(this);
        var subId = $btn.data('id');

        if (!subId) return;

        // Prevent double-click
        $btn.prop('disabled', true)
            .text(gapnextAI.generatingText)
            .after('<span class="gapnext-ai-spinner" style="margin-left:6px;display:inline-block">\u23f3</span>');

        $.post(gapnextAI.ajaxUrl, {
            action:        'gapnext_ai_generate',
            nonce:          gapnextAI.nonce,
            submission_id:  subId
        })
        .done(function (resp) {
            if (resp.success && resp.data && resp.data.download_url) {
                var downloadUrl = resp.data.download_url + '?token=' + encodeURIComponent(gapnextAI.apiKey);
                $btn.next('.gapnext-ai-spinner').remove();
                $btn.replaceWith(
                    $('<a>')
                        .attr({ href: downloadUrl, target: '_blank', class: 'button button-small button-primary' })
                        .text(gapnextAI.downloadText)
                );
            } else {
                var msg = (resp.data && resp.data.message) ? resp.data.message : gapnextAI.errorText;
                $btn.next('.gapnext-ai-spinner').remove();
                $btn.prop('disabled', false).text(gapnextAI.generateText);
                $btn.after('<span style="margin-left:6px;color:#991b1b;font-size:12px">' + $('<span>').text(msg).html() + '</span>');
            }
        })
        .fail(function () {
            $btn.next('.gapnext-ai-spinner').remove();
            $btn.prop('disabled', false).text(gapnextAI.generateText);
            $btn.after('<span style="margin-left:6px;color:#991b1b;font-size:12px">' + $('<span>').text(gapnextAI.networkErrorText).html() + '</span>');
        });
    });

    // ── View-page handler ─────────────────────────────────────────────────────
    // Guard: only runs when #gapnext-ai-generate-view exists in the DOM.
    // On the list page this element is absent so this block exits immediately.
    if ($('#gapnext-ai-generate-view').length === 0) return;

    var STEPS = [
        'Preparing checklist data\u2026',
        'Sending to AI pipeline\u2026',
        'AI is analyzing compliance findings\u2026',
        'Generating report document\u2026'
    ];
    var STEP_DELAYS = [0, 800, 2500, 5000]; // ms after click when each step becomes active

    var $generateBtn = $('#gapnext-ai-generate-view');
    var $logPanel    = $('#gapnext-ai-log-panel');
    var $logSteps    = $('#gapnext-ai-log-steps');
    var timers       = [];
    var currentStep  = -1;

    function dotHtml() {
        return '<span class="gapnext-ai-dot"></span>' +
               '<span class="gapnext-ai-dot"></span>' +
               '<span class="gapnext-ai-dot"></span>';
    }

    function buildStepEl(text) {
        return $('<div class="gapnext-ai-step">' +
            '<span class="gapnext-ai-step-icon">\u23f3</span>' +
            '<span class="gapnext-ai-step-text">' + $('<span>').text(text).html() + '</span>' +
        '</div>');
    }

    function activateStep(index) {
        // Mark previous step done
        if (index > 0) {
            $logSteps.children().eq(index - 1)
                .removeClass('active')
                .addClass('done')
                .find('.gapnext-ai-step-icon').text('\u2713');
        }
        // Activate current step with pulsing dots
        $logSteps.children().eq(index)
            .addClass('active')
            .find('.gapnext-ai-step-icon').html(dotHtml());

        currentStep = index;
    }

    function tickAllRemaining() {
        // Synchronously tick all steps from currentStep onward (includes active step)
        var from = currentStep >= 0 ? currentStep : 0;
        $logSteps.children().each(function (i) {
            if (i >= from) {
                $(this).removeClass('active').addClass('done')
                    .find('.gapnext-ai-step-icon').text('\u2713');
            }
        });
    }

    function clearTimers() {
        timers.forEach(function (t) { clearTimeout(t); });
        timers = [];
    }

    function resetToInitial() {
        clearTimers();
        currentStep = -1;
        $logPanel.hide();
        $logSteps.empty();
        // Remove any download button that may have been appended
        $logPanel.next('a.button-primary').remove();
        $generateBtn.prop('disabled', false).text(gapnextAI.generateText).show();
    }

    function startGeneration() {
        // Disable generate button
        $generateBtn.prop('disabled', true).text(gapnextAI.generatingText);

        // Build all step elements (initially greyed / opacity .38)
        $logSteps.empty();
        $.each(STEPS, function (i, text) {
            $logSteps.append(buildStepEl(text));
        });

        // Show log panel
        $logPanel.show();
        currentStep = -1;

        // Schedule step activations via setTimeout chain
        $.each(STEP_DELAYS, function (i, delay) {
            timers.push(setTimeout(function () {
                activateStep(i);
            }, delay));
        });

        // Fire AJAX
        $.post(gapnextAI.ajaxUrl, {
            action:        'gapnext_ai_generate',
            nonce:          gapnextAI.nonce,
            submission_id:  gapnextAI.submissionId
        })
        .done(function (resp) {
            clearTimers();
            if (resp.success && resp.data && resp.data.download_url) {
                // Tick all steps synchronously, including the currently active one
                tickAllRemaining();

                // Success line
                $logSteps.append(
                    '<div style="margin-top:12px;font-size:13px;font-weight:600;color:#166534">' +
                    '\u2705 Report generated successfully!' +
                    '</div>'
                );

                // Download button (appended after log panel)
                var downloadUrl = resp.data.download_url + '?token=' + encodeURIComponent(gapnextAI.apiKey);
                $generateBtn.hide();
                $logPanel.after(
                    $('<a>')
                        .attr({ href: downloadUrl, target: '_blank', class: 'button button-primary', style: 'margin-top:16px;display:inline-block' })
                        .text(gapnextAI.downloadText)
                );
            } else {
                onError((resp.data && resp.data.message) ? resp.data.message : gapnextAI.errorText);
            }
        })
        .fail(function () {
            clearTimers();
            onError(gapnextAI.networkErrorText);
        });
    }

    function onError(message) {
        // Mark current active step as failed (red ✗)
        var failIndex = currentStep >= 0 ? currentStep : 0;
        $logSteps.children().eq(failIndex)
            .removeClass('active').addClass('failed')
            .find('.gapnext-ai-step-icon').text('\u2717');

        // Error line
        $logSteps.append(
            '<div style="margin-top:10px;font-size:13px;color:#991b1b">' +
            '\u274c ' + $('<span>').text(message).html() +
            '</div>'
        );

        // Try Again button — clicking resets to initial state
        var $tryAgain = $('<button type="button" class="button" style="margin-top:14px">')
            .text('Try Again');
        $tryAgain.on('click', function () {
            resetToInitial();
        });
        $logSteps.append($tryAgain);

        // Re-enable generate button (it stays hidden behind log, reset will show it)
        $generateBtn.prop('disabled', false).text(gapnextAI.generateText);
    }

    $generateBtn.on('click', startGeneration);
});
```

- [ ] **Step 2: Verify in browser (success path)**

Open a submission view that has no AI report yet. Click "Generate AI Report":
- Button changes to "Generating…" and is disabled
- Progress log panel appears below the button
- Steps appear one by one with pulsing indigo dots on the active step, ✓ on completed steps
- On success: ALL steps show ✓ (including the one that was active when response arrived), green "Report generated successfully!" line appears, "⬇ Download AI Report" link appears below the panel
- Download URL ends with `?token=<apiKey>`

- [ ] **Step 3: Verify error path**

To test the error path, temporarily set an invalid API key in the GapNext settings, then click Generate:
- Steps begin animating
- On error: the currently active step shows ✗ in red, error message appears, "Try Again" button appears
- Clicking "Try Again" removes the log panel and restores the "Generate AI Report" button in its initial enabled state
- Clicking "Generate AI Report" again starts a fresh animation cycle

- [ ] **Step 4: Verify list page is unaffected**

Open `admin.php?page=gapnext-submissions`. Confirm no JS errors in console (the guard `if ($('#gapnext-ai-generate-view').length === 0) return;` exits early on the list page because the element is absent).
