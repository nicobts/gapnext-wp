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
    if ($('#gapnext-ai-generate-view').length === 0 && $('#gapnext-ai-regenerate-view').length === 0) return;

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
        // If AJAX resolved before first step timer, activate step 0 briefly first
        if (currentStep < 0) {
            activateStep(0);
        }
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
        $('.gapnext-ai-download-btn').remove();
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
                        .attr({ href: downloadUrl, target: '_blank', class: 'button button-primary gapnext-ai-download-btn', style: 'margin-top:16px;display:inline-block' })
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
    }

    $generateBtn.on('click', startGeneration);

    // ── Regenerate handler ─────────────────────────────────────────────────────
    // Only active when #gapnext-ai-regenerate-view is in the DOM (report exists state).
    var $regenBtn        = $('#gapnext-ai-regenerate-view');
    var $regenPanel      = $('#gapnext-ai-regen-log-panel');
    var $regenSteps      = $('#gapnext-ai-regen-log-steps');
    var $correctionsArea = $('#gapnext-ai-corrections');
    var $latestDownload  = $('#gapnext-ai-latest-download');
    var regenTimers      = [];
    var regenCurrentStep = -1;

    // Scoped helpers — operate on regen panel, NOT the generate flow's $logSteps / currentStep.
    function regenActivateStep(index) {
        if (index > 0) {
            $regenSteps.children().eq(index - 1)
                .removeClass('active')
                .addClass('done')
                .find('.gapnext-ai-step-icon').text('\u2713');
        }
        $regenSteps.children().eq(index)
            .addClass('active')
            .find('.gapnext-ai-step-icon').html(dotHtml());
        regenCurrentStep = index;
    }

    function regenTickAllRemaining() {
        if (regenCurrentStep < 0) {
            regenActivateStep(0);
        }
        var from = regenCurrentStep >= 0 ? regenCurrentStep : 0;
        $regenSteps.children().each(function (i) {
            if (i >= from) {
                $(this).removeClass('active').addClass('done')
                    .find('.gapnext-ai-step-icon').text('\u2713');
            }
        });
    }

    function regenClearTimers() {
        regenTimers.forEach(function (t) { clearTimeout(t); });
        regenTimers = [];
    }

    function startRegeneration() {
        var corrections = $correctionsArea.val().trim();

        $regenBtn.prop('disabled', true);
        $regenSteps.empty();
        $.each(STEPS, function (i, text) {
            $regenSteps.append(buildStepEl(text));
        });
        $regenPanel.show();
        regenCurrentStep = -1;

        $.each(STEP_DELAYS, function (i, delay) {
            regenTimers.push(setTimeout(function () {
                regenActivateStep(i);
            }, delay));
        });

        $.post(gapnextAI.ajaxUrl, {
            action:        'gapnext_ai_generate',
            nonce:          gapnextAI.nonce,
            submission_id:  gapnextAI.submissionId,
            comments:       corrections
        })
        .done(function (resp) {
            regenClearTimers();
            if (resp.success && resp.data && resp.data.download_url) {
                regenTickAllRemaining();

                $regenSteps.append(
                    '<div style="margin-top:12px;font-size:13px;font-weight:600;color:#166534">' +
                    '\u2705 Report regenerated successfully!' +
                    '</div>'
                );

                // Update the "latest download" link
                var newUrl = resp.data.download_url + '?token=' + encodeURIComponent(gapnextAI.apiKey);
                $latestDownload.attr('href', newUrl);

                // Prepend new row to history table
                var date    = new Date().toLocaleString();
                var comment = resp.data.comments || '\u2014';
                var dlUrl   = resp.data.download_url + '?token=' + encodeURIComponent(gapnextAI.apiKey);
                $('<tr>')
                    .append($('<td>').text(date))
                    .append($('<td>').text(comment))
                    .append($('<td>').append($('<a>').attr({href: dlUrl, target: '_blank'}).text('\u2b07 Report')))
                    .prependTo('#gapnext-ai-history-table tbody');

                // Reset after 1s
                setTimeout(function () {
                    $correctionsArea.val('');
                    $regenPanel.hide();
                    $regenSteps.empty();
                    regenCurrentStep = -1;
                    $regenBtn.prop('disabled', false);
                }, 1000);

            } else {
                regenOnError((resp.data && resp.data.message) ? resp.data.message : gapnextAI.errorText);
            }
        })
        .fail(function () {
            regenClearTimers();
            regenOnError(gapnextAI.networkErrorText);
        });
    }

    function regenOnError(message) {
        var failIndex = regenCurrentStep >= 0 ? regenCurrentStep : 0;
        $regenSteps.children().eq(failIndex)
            .removeClass('active').addClass('failed')
            .find('.gapnext-ai-step-icon').text('\u2717');

        $regenSteps.append(
            '<div style="margin-top:10px;font-size:13px;color:#991b1b">' +
            '\u274c ' + $('<span>').text(message).html() +
            '</div>'
        );

        var $tryAgain = $('<button type="button" class="button" style="margin-top:14px">')
            .text('Try Again');
        $tryAgain.on('click', function () {
            regenClearTimers();
            regenCurrentStep = -1;
            $regenPanel.hide();
            $regenSteps.empty();
            $regenBtn.prop('disabled', false);
        });
        $regenSteps.append($tryAgain);
    }

    $regenBtn.on('click', startRegeneration);
});
