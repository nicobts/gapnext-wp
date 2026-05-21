/* GapNext WP — Frontend JS */
(function ($) {
    'use strict';

    $(function () {
        var currentStep   = 0;
        var totalQuestions = parseInt($('input[name="total_questions"]').val(), 10) || 0;
        var isDirty       = false;
        var draftId       = 0;
        var autosaveTimer = null;
        var auditUuid     = (window.GapNextWPForm && GapNextWPForm.audit_uuid) ? GapNextWPForm.audit_uuid : '';
        var lsKey         = auditUuid ? 'gapnext_draft_' + auditUuid : '';
        var fillingMode   = '';

        // Determine total steps from the step indicator
        var $steps = $('#gapnext-steps .gapnext-step');
        var maxStep = 0;
        $steps.each(function () {
            var s = parseInt($(this).data('step'), 10);
            if (s > maxStep) maxStep = s;
        });
        var reviewStep = maxStep;

        // -------------------------------------------------------
        // Labels from localized i18n
        // -------------------------------------------------------
        var contactLabelDefault = $('#gapnext-contact-legend').text();
        var contactLabelSelf    = (window.GapNextWPForm && GapNextWPForm.sections_meta) ? '' : '';

        // -------------------------------------------------------
        // Draft: restore from server-side draft or localStorage
        // -------------------------------------------------------
        var serverDraft = (window.GapNextWPForm && GapNextWPForm.server_draft) ? GapNextWPForm.server_draft : null;

        if (serverDraft) {
            populateFromServerDraft(serverDraft);
            draftId = serverDraft.draft_id || 0;
            fillingMode = serverDraft.filling_mode || '';
            $('#gapnext-draft-id').val(draftId);
            $('#gapnext-filling-mode').val(fillingMode);
            applyFillingMode(fillingMode);

            // Show draft banner
            var bannerText = (GapNextWP.i18n.draft_restored || 'Draft restored') +
                             ' — ' + (serverDraft.saved_at || '');
            $('#gapnext-draft-banner-text').text(bannerText);
            $('#gapnext-draft-banner').show();

            // Skip mode selector, go to last step or step 1
            var resumeStep = serverDraft.last_step || 1;
            if (resumeStep < 1) resumeStep = 1;
            showStep(resumeStep);
        } else if (lsKey) {
            // Fallback: try localStorage
            try {
                var saved = localStorage.getItem(lsKey);
                if (saved) {
                    var draft = JSON.parse(saved);
                    populateFromLocalDraft(draft);
                    draftId = draft.draft_id || 0;
                    fillingMode = draft.filling_mode || '';
                    $('#gapnext-draft-id').val(draftId);
                    if (fillingMode) {
                        $('#gapnext-filling-mode').val(fillingMode);
                        applyFillingMode(fillingMode);
                        showStep(draft.last_step || 1);

                        var bannerText2 = (GapNextWP.i18n.draft_restored || 'Draft restored') +
                                          ' (' + (draft.saved_at || '') + ')';
                        $('#gapnext-draft-banner-text').text(bannerText2);
                        $('#gapnext-draft-banner').show();
                    }
                }
            } catch (e) {}
        }

        // Dismiss draft banner
        $('#gapnext-draft-dismiss').on('click', function () {
            $('#gapnext-draft-banner').fadeOut(200);
        });

        // Auto-save info accordion toggle
        $('#gapnext-autosave-toggle').on('click', function () {
            $('#gapnext-autosave-info').toggleClass('open');
        });

        // -------------------------------------------------------
        // Demo mode: always force self-assessment, hide step 0
        // -------------------------------------------------------
        var isDemo = !!(window.GapNextWPForm && GapNextWPForm.is_demo);
        if (isDemo) {
            fillingMode = 'self';
            $('#gapnext-filling-mode').val('self');
            applyFillingMode('self');
            // Hide mode selector step indicator
            $steps.filter('[data-step="0"]').hide();
            $('#gapnext-step-0').hide();
            // If no draft already navigated us somewhere, go to step 1
            if (!serverDraft) {
                showStep(1);
            }
        }

        // -------------------------------------------------------
        // Mark dirty on any field change
        // -------------------------------------------------------
        $(document).on('change input', '#gapnext-audit-form input, #gapnext-audit-form textarea, #gapnext-audit-form select', function () {
            if ($(this).attr('type') === 'file') return;
            isDirty = true;
        });

        // -------------------------------------------------------
        // Auto-save every 30 seconds
        // -------------------------------------------------------
        autosaveTimer = setInterval(doAutoSave, 30000);

        function doAutoSave(callback) {
            if (!isDirty || !auditUuid) {
                if (callback) callback(true);
                return;
            }
            isDirty = false;

            showSaveStatus('saving');
            var data = collectDraftData();

            $.post(GapNextWP.ajax_url, data, function (response) {
                if (response && response.success) {
                    draftId = response.data.draft_id;
                    $('#gapnext-draft-id').val(draftId);

                    // Persist to localStorage
                    if (lsKey) {
                        try {
                            var toStore = collectDraftData();
                            toStore.draft_id = draftId;
                            toStore.saved_at = formatTime(new Date());
                            localStorage.setItem(lsKey, JSON.stringify(toStore));
                        } catch (e) {}
                    }

                    showSaveStatus('saved');
                    if (callback) callback(true);
                } else {
                    showSaveStatus('error');
                    if (callback) callback(false);
                }
            }).fail(function () {
                showSaveStatus('error');
                if (callback) callback(false);
            });
        }

        function collectDraftData() {
            var data = {
                action:              'gapnext_autosave',
                nonce:               GapNextWP.autosave_nonce,
                audit_uuid:          auditUuid,
                draft_id:            draftId,
                filling_mode:        fillingMode,
                last_step:           currentStep,
                company_name:        $('[name="company_name"]').val()       || '',
                company_address:     $('[name="company_address"]').val()    || '',
                company_vat:         $('[name="company_vat"]').val()        || '',
                company_sector:      $('[name="company_sector"]').val()     || '',
                contact_name:        $('[name="contact_name"]').val()       || '',
                contact_role:        $('[name="contact_role"]').val()       || '',
                contact_email:       $('[name="contact_email"]').val()      || '',
                contact_phone:       $('[name="contact_phone"]').val()      || '',
                consultant_name:     $('[name="consultant_name"]').val()    || '',
                consultant_company:  $('[name="consultant_company"]').val() || '',
                consultant_email:    $('[name="consultant_email"]').val()   || '',
                consultant_phone:    $('[name="consultant_phone"]').val()   || '',
            };

            // Answers
            $('[name^="answer["]').filter(':checked').each(function () {
                data[$(this).attr('name')] = $(this).val();
            });

            // Notes
            $('[name^="notes["]').each(function () {
                var val = $(this).val();
                if (val) data[$(this).attr('name')] = val;
            });

            return data;
        }

        function populateFromServerDraft(draft) {
            var fields = [
                'company_name', 'company_address', 'company_vat', 'company_sector',
                'contact_name', 'contact_role', 'contact_email', 'contact_phone',
                'consultant_name', 'consultant_company', 'consultant_email', 'consultant_phone'
            ];
            fields.forEach(function (f) {
                if (draft[f]) $('[name="' + f + '"]').val(draft[f]);
            });

            // Answers from server draft — structured as {ref: {value, note}}
            if (draft.answers && typeof draft.answers === 'object') {
                $.each(draft.answers, function (ref, entry) {
                    var val = (typeof entry === 'object') ? entry.value : entry;
                    var note = (typeof entry === 'object') ? (entry.note || '') : '';
                    if (val !== null && val !== undefined) {
                        var $radio = $('[name="answer[' + ref + ']"][value="' + val + '"]');
                        if ($radio.length) {
                            $radio.prop('checked', true);
                            $radio.closest('.gapnext-answer').addClass('selected')
                                .siblings('.gapnext-answer').removeClass('selected');
                        }
                    }
                    if (note) {
                        $('[name="notes[' + ref + ']"]').val(note);
                    }
                });
            }

            updateProgress();
        }

        function populateFromLocalDraft(draft) {
            var fields = [
                'company_name', 'company_address', 'company_vat', 'company_sector',
                'contact_name', 'contact_role', 'contact_email', 'contact_phone',
                'consultant_name', 'consultant_company', 'consultant_email', 'consultant_phone'
            ];
            fields.forEach(function (f) {
                if (draft[f]) $('[name="' + f + '"]').val(draft[f]);
            });

            // Answers — look for saved answer[ref] keys
            $.each(draft, function (key, val) {
                var m = key.match(/^answer\[(.+)\]$/);
                if (m) {
                    var $radio = $('[name="answer[' + m[1] + ']"][value="' + val + '"]');
                    if ($radio.length) {
                        $radio.prop('checked', true);
                        $radio.closest('.gapnext-answer').addClass('selected')
                            .siblings('.gapnext-answer').removeClass('selected');
                    }
                }
                var n = key.match(/^notes\[(.+)\]$/);
                if (n) {
                    $('[name="notes[' + n[1] + ']"]').val(val);
                }
            });

            updateProgress();
        }

        // -------------------------------------------------------
        // Save status indicator
        // -------------------------------------------------------
        function showSaveStatus(state) {
            var $el = $('#gapnext-save-status');
            $el.removeClass('gapnext-save-saving gapnext-save-saved gapnext-save-error');
            if (state === 'saving') {
                $el.addClass('gapnext-save-saving').text(GapNextWP.i18n.draft_saved ? '...' : 'Saving...');
            } else if (state === 'saved') {
                $el.addClass('gapnext-save-saved').text(formatTime(new Date()));
            } else if (state === 'error') {
                $el.addClass('gapnext-save-error').text('!');
            }
        }

        function formatTime(d) {
            return d.getHours().toString().padStart(2, '0') + ':' +
                   d.getMinutes().toString().padStart(2, '0');
        }

        // -------------------------------------------------------
        // Mode Selector (Step 0)
        // -------------------------------------------------------
        $(document).on('click', '.gapnext-mode-card', function () {
            var mode = $(this).data('mode');
            fillingMode = mode;
            $('#gapnext-filling-mode').val(mode);
            $('.gapnext-mode-card').removeClass('selected');
            $(this).addClass('selected');
            applyFillingMode(mode);
            isDirty = true;
            showStep(1);
        });

        function applyFillingMode(mode) {
            var $consultantFieldset = $('#gapnext-consultant-fieldset');
            var $consultantFields = $consultantFieldset.find('.gapnext-consultant-field');

            if (mode === 'self') {
                $consultantFieldset.hide();
                $consultantFields.removeAttr('required');
                // Update contact label
                var selfLabel = $('html').attr('lang') === 'it' ? 'I tuoi dati' : 'Your Details';
                $('#gapnext-contact-legend').text(selfLabel);
            } else {
                $consultantFieldset.show();
                $consultantFields.attr('required', 'required');
                $('#gapnext-contact-legend').text(contactLabelDefault);
            }
        }

        // -------------------------------------------------------
        // Answer Toggle visual state
        // -------------------------------------------------------
        $(document).on('change', '.gapnext-answer input[type="radio"]', function () {
            var $question = $(this).closest('.gapnext-question');
            $question.find('.gapnext-answer').removeClass('selected');
            $(this).closest('.gapnext-answer').addClass('selected');
            updateProgress();
            updateSectionBadges();
        });

        // -------------------------------------------------------
        // Progress Bar + Score Breakdown
        // -------------------------------------------------------
        function updateProgress() {
            var compliant = 0, partial = 0, nonCompliant = 0, na = 0, answered = 0;

            $('.gapnext-question').each(function () {
                var $checked = $(this).find('.gapnext-answer input[type="radio"]:checked');
                if ($checked.length) {
                    answered++;
                    var val = $checked.val();
                    if (val === '1')    compliant++;
                    else if (val === '0.5') partial++;
                    else if (val === '0')   nonCompliant++;
                    else if (val === 'na')  na++;
                }
            });

            var pct = totalQuestions > 0 ? Math.round((answered / totalQuestions) * 100) : 0;
            $('#gapnext-progress-bar').css('width', pct + '%');
            $('#gapnext-progress-label').text(pct + '%');

            // Breakdown chips
            $('#gn-count-answered').text(answered);
            $('#gn-count-compliant').text(compliant);
            $('#gn-count-partial').text(partial);
            $('#gn-count-noncompliant').text(nonCompliant);
            $('#gn-count-na').text(na);

            // Projected score = Conformities / (Total questions - N/A)
            var denominator = totalQuestions - na;
            var projScore   = denominator > 0 ? Math.round(compliant / denominator * 100) : 0;
            $('#gn-projected-score').text(denominator > 0 ? projScore + '%' : '\u2014');

            // Review panel
            var unanswered = totalQuestions - answered;
            $('#gn-rev-compliant').text(compliant);
            $('#gn-rev-partial').text(partial);
            $('#gn-rev-noncompliant').text(nonCompliant);
            $('#gn-rev-na').text(na);
            $('#gn-rev-unanswered').text(unanswered);
            $('#gn-rev-score').text(denominator > 0 ? projScore + '%' : '\u2014');

            var label = GapNextWP.i18n.questions_answered || 'questions answered';
            $('#gapnext-summary-text').text(answered + ' / ' + totalQuestions + ' ' + label);
        }

        // -------------------------------------------------------
        // Per-section badge updates
        // -------------------------------------------------------
        function updateSectionBadges() {
            $steps.each(function () {
                var $step = $(this);
                var section = $step.data('section');
                if (!section) return;

                var stepNum = parseInt($step.data('step'), 10);
                var $stepDiv = $('#gapnext-step-' + stepNum);
                var total = parseInt($step.data('total'), 10) || 0;
                var answered = 0;

                $stepDiv.find('.gapnext-question').each(function () {
                    if ($(this).find('.gapnext-answer input[type="radio"]:checked').length) {
                        answered++;
                    }
                });

                $step.find('.gapnext-step-badge').text(answered + '/' + total);

                // Toggle completed state
                if (answered >= total && total > 0) {
                    $step.addClass('completed');
                } else {
                    $step.removeClass('completed');
                }
            });
        }

        // Initial badge update
        updateSectionBadges();

        // -------------------------------------------------------
        // Step Navigation
        // -------------------------------------------------------
        $(document).on('click', '.gapnext-next', function () {
            var nextStep = parseInt($(this).data('next'), 10);
            if (!validateStep(currentStep)) return;
            // Save on forward navigation
            isDirty = true;
            doAutoSave();
            showStep(nextStep);
        });

        $(document).on('click', '.gapnext-prev', function () {
            showStep(parseInt($(this).data('prev'), 10));
        });

        // Direct step navigation via step indicator
        $(document).on('click', '.gapnext-step', function () {
            var targetStep = parseInt($(this).data('step'), 10);
            if (targetStep === currentStep) return;
            // Don't allow jumping to step 0 if mode already selected
            if (targetStep === 0 && fillingMode) return;
            // Don't allow jumping forward past company info if it's not validated
            if (targetStep > 1 && currentStep <= 1 && !validateStep(1)) return;
            showStep(targetStep);
        });

        function showStep(step) {
            $('.gapnext-step-content').hide();
            $('#gapnext-step-' + step).show();
            $steps.removeClass('active');
            $steps.filter('[data-step="' + step + '"]').addClass('active');

            // Mark visited steps
            $steps.each(function () {
                var s = parseInt($(this).data('step'), 10);
                if (s < step) $(this).addClass('visited');
            });

            currentStep = step;
            $('#gapnext-last-step').val(step);
            $('html, body').animate({ scrollTop: ($('#gapnext-form-wrap').offset().top - 40) }, 300);
        }

        // -------------------------------------------------------
        // Inline toast notification (replaces browser alert)
        // -------------------------------------------------------
        function showToast(message, type) {
            type = type || 'error';
            var $existing = $('#gapnext-toast');
            if ($existing.length) $existing.remove();
            var $toast = $('<div id="gapnext-toast" class="gapnext-toast gapnext-toast-' + type + '">' +
                '<span class="gapnext-toast-msg">' + $('<span>').text(message).html() + '</span>' +
                '<button type="button" class="gapnext-toast-close">&times;</button>' +
                '</div>');
            var $container = $('#gapnext-toast-container');
            if ($container.length) {
                $container.empty().append($toast);
            } else {
                $('#gapnext-form-wrap').prepend($toast);
            }
            requestAnimationFrame(function () { $toast.addClass('gapnext-toast-visible'); });
            $toast.find('.gapnext-toast-close').on('click', function () { dismissToast($toast); });
            setTimeout(function () { dismissToast($toast); }, 6000);
        }

        function dismissToast($toast) {
            if (!$toast.length || $toast.data('dismissed')) return;
            $toast.data('dismissed', true);
            $toast.removeClass('gapnext-toast-visible');
            setTimeout(function () { $toast.remove(); }, 300);
        }

        // Clear field error on input
        $(document).on('input change', '#gapnext-step-1 [required]', function () {
            var $field = $(this);
            if ($field.val().trim()) {
                $field.removeClass('gapnext-field-error');
                $field.next('.gapnext-field-error-msg').remove();
            }
        });

        function validateStep(step) {
            if (step === 1) {
                var valid = true;
                // Clear previous inline errors
                $('#gapnext-step-1 .gapnext-field-error').removeClass('gapnext-field-error');
                $('#gapnext-step-1 .gapnext-field-error-msg').remove();

                var $firstInvalid = null;
                $('#gapnext-step-1 [required]:visible').each(function () {
                    var $field = $(this);
                    if (!$field.val().trim()) {
                        $field.addClass('gapnext-field-error');
                        // Add inline error message below the field
                        if (!$field.next('.gapnext-field-error-msg').length) {
                            var fieldLabel = $field.closest('.gapnext-field').find('label').text().replace(/\s*\*\s*$/, '') || '';
                            var msg = fieldLabel
                                ? (GapNextWP.i18n.field_required_named || '{field} is required').replace('{field}', fieldLabel)
                                : (GapNextWP.i18n.field_required || 'This field is required');
                            $field.after('<span class="gapnext-field-error-msg">' + $('<span>').text(msg).html() + '</span>');
                        }
                        if (!$firstInvalid) $firstInvalid = $field;
                        valid = false;
                    }
                });
                if (!valid) {
                    showToast(GapNextWP.i18n.required_fields);
                    if ($firstInvalid) {
                        $firstInvalid.focus();
                    }
                }
                return valid;
            }
            return true;
        }

        // -------------------------------------------------------
        // AJAX Submission
        // -------------------------------------------------------
        $('#gapnext-audit-form').on('submit', function (e) {
            e.preventDefault();

            // Stop autosave
            clearInterval(autosaveTimer);

            var $btn = $('#gapnext-submit-btn');
            $btn.prop('disabled', true).text(GapNextWP.i18n.submitting);

            var formData = new FormData(this);

            $.ajax({
                url: GapNextWP.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        // Clear draft from localStorage
                        if (lsKey) {
                            try { localStorage.removeItem(lsKey); } catch (e) {}
                        }

                        $('#gapnext-audit-form').hide();
                        $('#gapnext-draft-status').hide();
                        $('#gapnext-draft-banner').hide();

                        var score = Math.round(response.data.score * 100);
                        $('#gapnext-final-score').text(score + '%');

                        // Populate per-status breakdown from server response
                        var st = response.data.stats;
                        if (st) {
                            var finalUnanswered = (st.total || 0) - (st.answered || 0);
                            $('#gn-final-compliant').text(st.compliant || 0);
                            $('#gn-final-partial').text(st.partial || 0);
                            $('#gn-final-noncompliant').text(st.non_comply || 0);
                            $('#gn-final-na').text(st.na || 0);
                            $('#gn-final-unanswered').text(finalUnanswered >= 0 ? finalUnanswered : '\u2014');
                        }

                        $('#gapnext-success').show();

                        // Build download URLs from submission_id + audit_uuid
                        var subId   = response.data.submission_id;
                        var auditId = response.data.audit_uuid;
                        if (subId && auditId) {
                            var dlBase = GapNextWP.download_url + '?action=gapnext_download&sub=' + subId + '&audit=' + encodeURIComponent(auditId);
                            $('#gapnext-dl-pdf').attr('href', dlBase + '&format=pdf');
                            $('#gapnext-dl-csv').attr('href', dlBase + '&format=csv');
                            $('#gapnext-dl-md').attr('href', dlBase + '&format=md');
                            $('#gapnext-downloads').show();

                            // Results page link
                            if (GapNextWP.results_page_url) {
                                var resultsUrl = GapNextWP.results_page_url + '?sub=' + subId + '&audit=' + encodeURIComponent(auditId);
                                $('#gapnext-results-link').attr('href', resultsUrl).closest('#gapnext-results-wrap').show();
                            }
                        }

                        $('html, body').animate({ scrollTop: ($('#gapnext-form-wrap').offset().top - 40) }, 300);
                    } else {
                        showToast(response.data || GapNextWP.i18n.error);
                        $btn.prop('disabled', false).text($btn.data('original-text') || 'Submit');
                        autosaveTimer = setInterval(doAutoSave, 30000);
                    }
                },
                error: function () {
                    showToast(GapNextWP.i18n.error);
                    $btn.prop('disabled', false).text($btn.data('original-text') || 'Submit');
                    autosaveTimer = setInterval(doAutoSave, 30000);
                },
            });
        });

        // Store original submit button text
        $('#gapnext-submit-btn').data('original-text', $('#gapnext-submit-btn').text());
    });

}(jQuery));
