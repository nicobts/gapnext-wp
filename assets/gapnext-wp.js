/* GapNext WP — Frontend JS */
(function ($) {
    'use strict';

    $(function () {
        var currentStep   = 1;
        var totalQuestions = parseInt($('input[name="total_questions"]').val(), 10) || 0;
        var isDirty       = false;
        var draftId       = 0;
        var autosaveTimer = null;
        var auditUuid     = (window.GapNextWPForm && GapNextWPForm.audit_uuid) ? GapNextWPForm.audit_uuid : '';
        var lsKey         = auditUuid ? 'gapnext_draft_' + auditUuid : '';

        // -------------------------------------------------------
        // Draft: restore from localStorage on load
        // -------------------------------------------------------
        if ( lsKey ) {
            try {
                var saved = localStorage.getItem( lsKey );
                if ( saved ) {
                    var draft = JSON.parse( saved );
                    populateFromDraft( draft );
                    draftId = draft.draft_id || 0;
                    $('#gapnext-draft-id').val( draftId );
                    showDraftStatus( GapNextWP.i18n.draft_restored + ' (' + draft.saved_at + ')' );
                }
            } catch (e) {}
        }

        // -------------------------------------------------------
        // Mark dirty on any field change
        // -------------------------------------------------------
        $(document).on( 'change input', '#gapnext-audit-form input, #gapnext-audit-form textarea, #gapnext-audit-form select', function () {
            if ( $(this).attr('type') === 'file' ) return; // skip file inputs
            isDirty = true;
        });

        // -------------------------------------------------------
        // Auto-save every 30 seconds
        // -------------------------------------------------------
        autosaveTimer = setInterval( doAutoSave, 30000 );

        function doAutoSave() {
            if ( ! isDirty || ! auditUuid ) return;
            isDirty = false;

            var data = collectDraftData();

            $.post( GapNextWP.ajax_url, data, function ( response ) {
                if ( response && response.success ) {
                    draftId = response.data.draft_id;
                    $('#gapnext-draft-id').val( draftId );

                    // Persist to localStorage
                    if ( lsKey ) {
                        try {
                            var toStore = collectDraftData();
                            toStore.draft_id = draftId;
                            toStore.saved_at = formatTime( new Date() );
                            localStorage.setItem( lsKey, JSON.stringify( toStore ) );
                        } catch (e) {}
                    }

                    showDraftStatus( GapNextWP.i18n.draft_saved + ' ' + formatTime( new Date() ) );
                }
            } );
        }

        function collectDraftData() {
            var data = {
                action:              'gapnext_autosave',
                nonce:               GapNextWP.autosave_nonce,
                audit_uuid:          auditUuid,
                draft_id:            draftId,
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
                data[ $(this).attr('name') ] = $(this).val();
            });

            // Notes
            $('[name^="notes["]').each(function () {
                var val = $(this).val();
                if ( val ) data[ $(this).attr('name') ] = val;
            });

            return data;
        }

        function populateFromDraft( draft ) {
            var fields = [
                'company_name', 'company_address', 'company_vat', 'company_sector',
                'contact_name', 'contact_role', 'contact_email', 'contact_phone',
                'consultant_name', 'consultant_company', 'consultant_email', 'consultant_phone'
            ];
            fields.forEach(function (f) {
                if ( draft[f] ) $('[name="' + f + '"]').val( draft[f] );
            });

            // Answers — look for saved answer[ref] keys
            $.each(draft, function (key, val) {
                var m = key.match(/^answer\[(.+)\]$/);
                if ( m ) {
                    var $radio = $('[name="answer[' + m[1] + ']"][value="' + val + '"]');
                    if ( $radio.length ) {
                        $radio.prop( 'checked', true );
                        $radio.closest('.gapnext-answer').addClass('selected')
                            .siblings('.gapnext-answer').removeClass('selected');
                    }
                }
                var n = key.match(/^notes\[(.+)\]$/);
                if ( n ) {
                    $('[name="notes[' + n[1] + ']"]').val( val );
                }
            });

            updateProgress();
        }

        function showDraftStatus( msg ) {
            $('#gapnext-draft-status').text( msg ).show();
        }

        function formatTime( d ) {
            return d.getHours().toString().padStart(2,'0') + ':' +
                   d.getMinutes().toString().padStart(2,'0');
        }

        // -------------------------------------------------------
        // Accordion
        // -------------------------------------------------------
        $(document).on('click', '.gapnext-accordion-header', function () {
            var $body = $(this).next('.gapnext-accordion-body');
            var $icon = $(this).find('.gapnext-accordion-icon');
            var isOpen = $body.hasClass('open');
            $(this).toggleClass('open', !isOpen);
            $body.toggleClass('open', !isOpen);
            $icon.text(isOpen ? '▼' : '▲');
        });

        // Open first accordion by default
        $('.gapnext-accordion-header').first().trigger('click');

        // -------------------------------------------------------
        // Answer Toggle visual state
        // -------------------------------------------------------
        $(document).on('change', '.gapnext-answer input[type="radio"]', function () {
            var $question = $(this).closest('.gapnext-question');
            $question.find('.gapnext-answer').removeClass('selected');
            $(this).closest('.gapnext-answer').addClass('selected');
            updateProgress();
        });

        // -------------------------------------------------------
        // Progress Bar + Score Breakdown
        // -------------------------------------------------------
        function updateProgress() {
            var compliant = 0, partial = 0, nonCompliant = 0, na = 0, answered = 0;

            $('.gapnext-question').each(function () {
                var $checked = $(this).find('.gapnext-answer input[type="radio"]:checked');
                if ( $checked.length ) {
                    answered++;
                    var val = $checked.val();
                    if ( val === '1' )    compliant++;
                    else if ( val === '0.5' ) partial++;
                    else if ( val === '0' )   nonCompliant++;
                    else if ( val === 'na' )  na++;
                }
            });

            var pct = totalQuestions > 0 ? Math.round( (answered / totalQuestions) * 100 ) : 0;
            $('#gapnext-progress-bar').css( 'width', pct + '%' );
            $('#gapnext-progress-label').text( pct + '%' );

            // Breakdown chips
            $('#gn-count-answered').text( answered );
            $('#gn-count-compliant').text( compliant );
            $('#gn-count-partial').text( partial );
            $('#gn-count-noncompliant').text( nonCompliant );
            $('#gn-count-na').text( na );

            // Projected score = Conformities / (Total questions − N/A)
            var denominator = totalQuestions - na;
            var projScore   = denominator > 0 ? Math.round( compliant / denominator * 100 ) : 0;
            $('#gn-projected-score').text( denominator > 0 ? projScore + '%' : '—' );

            // Step 3 review panel
            var unanswered = totalQuestions - answered;
            $('#gn-rev-compliant').text( compliant );
            $('#gn-rev-partial').text( partial );
            $('#gn-rev-noncompliant').text( nonCompliant );
            $('#gn-rev-na').text( na );
            $('#gn-rev-unanswered').text( unanswered );
            $('#gn-rev-score').text( denominator > 0 ? projScore + '%' : '—' );

            var label = GapNextWP.i18n.questions_answered || 'questions answered';
            $('#gapnext-summary-text').text( answered + ' / ' + totalQuestions + ' ' + label );
        }

        // -------------------------------------------------------
        // Step Navigation
        // -------------------------------------------------------
        $(document).on('click', '.gapnext-next', function () {
            var nextStep = parseInt($(this).data('next'), 10);
            if (!validateStep(currentStep)) return;
            doAutoSave(); // save on step advance
            showStep(nextStep);
        });

        $(document).on('click', '.gapnext-prev', function () {
            showStep(parseInt($(this).data('prev'), 10));
        });

        function showStep(step) {
            $('.gapnext-step-content').hide();
            $('#gapnext-step-' + step).show();
            $('.gapnext-step').removeClass('active');
            $('.gapnext-step[data-step="' + step + '"]').addClass('active');
            currentStep = step;
            $('html, body').animate({ scrollTop: ($('#gapnext-form-wrap').offset().top - 40) }, 300);
        }

        function validateStep(step) {
            if (step === 1) {
                var valid = true;
                $('#gapnext-step-1 [required]').each(function () {
                    if (!$(this).val().trim()) {
                        $(this).css('border-color', '#dc2626');
                        valid = false;
                    } else {
                        $(this).css('border-color', '');
                    }
                });
                if (!valid) {
                    alert(GapNextWP.i18n.required_fields);
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

                        var score = Math.round(response.data.score * 100);
                        $('#gapnext-final-score').text(score + '%');

                        // Populate per-status breakdown from server response
                        var st = response.data.stats;
                        if ( st ) {
                            var finalUnanswered = (st.total || 0) - (st.answered || 0);
                            $('#gn-final-compliant').text( st.compliant || 0 );
                            $('#gn-final-partial').text( st.partial || 0 );
                            $('#gn-final-noncompliant').text( st.non_comply || 0 );
                            $('#gn-final-na').text( st.na || 0 );
                            $('#gn-final-unanswered').text( finalUnanswered >= 0 ? finalUnanswered : '—' );
                        }

                        $('#gapnext-success').show();

                        // Build download URLs from submission_id + audit_uuid
                        var subId    = response.data.submission_id;
                        var auditId  = response.data.audit_uuid;
                        if ( subId && auditId ) {
                            var dlBase = GapNextWP.download_url + '?action=gapnext_download&sub=' + subId + '&audit=' + encodeURIComponent( auditId );
                            $('#gapnext-dl-pdf').attr( 'href', dlBase + '&format=pdf' );
                            $('#gapnext-dl-csv').attr( 'href', dlBase + '&format=csv' );
                            $('#gapnext-dl-md').attr(  'href', dlBase + '&format=md'  );
                            $('#gapnext-downloads').show();

                            // Results page link
                            if ( GapNextWP.results_page_url ) {
                                var resultsUrl = GapNextWP.results_page_url + '?sub=' + subId + '&audit=' + encodeURIComponent( auditId );
                                $('#gapnext-results-link').attr( 'href', resultsUrl ).closest('#gapnext-results-wrap').show();
                            }
                        }

                        $('html, body').animate({ scrollTop: ($('#gapnext-form-wrap').offset().top - 40) }, 300);
                    } else {
                        alert(response.data || GapNextWP.i18n.error);
                        $btn.prop('disabled', false).text($btn.data('original-text') || 'Submit');
                        // Restart autosave on error
                        autosaveTimer = setInterval(doAutoSave, 30000);
                    }
                },
                error: function () {
                    alert(GapNextWP.i18n.error);
                    $btn.prop('disabled', false).text($btn.data('original-text') || 'Submit');
                    autosaveTimer = setInterval(doAutoSave, 30000);
                },
            });
        });

        // Store original submit button text
        $('#gapnext-submit-btn').data('original-text', $('#gapnext-submit-btn').text());
    });

}(jQuery));
