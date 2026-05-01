/* GapNext WP — Admin Remediation JS */
(function($) {
    'use strict';

    var nonce = window.gapnextRemediation ? gapnextRemediation.nonce : '';
    var ajaxUrl = window.gapnextRemediation ? gapnextRemediation.ajaxUrl : '';
    var subId = window.gapnextRemediation ? gapnextRemediation.submissionId : 0;
    var auditUuid = window.gapnextRemediation ? gapnextRemediation.auditUuid : '';

    // Tab switching
    $(document).on('click', '.gapnext-tab', function() {
        var target = $(this).data('tab');
        $('.gapnext-tab').removeClass('active');
        $(this).addClass('active');
        $('.gapnext-tab-panel').removeClass('active');
        $('#gapnext-panel-' + target).addClass('active');
    });

    // Initialize remediation
    $(document).on('click', '#gnr-init-btn', function() {
        var $btn = $(this);
        if (!confirm(gapnextRemediation.i18n.confirmInit)) return;
        $btn.prop('disabled', true).text(gapnextRemediation.i18n.initializing);

        $.post(ajaxUrl, {
            action: 'gapnext_remediation_init',
            nonce: nonce,
            submission_id: subId
        }).done(function(resp) {
            if (resp.success) {
                location.reload();
            } else {
                alert(resp.data || 'Error');
                $btn.prop('disabled', false).text(gapnextRemediation.i18n.initBtn);
            }
        }).fail(function() {
            alert('Network error');
            $btn.prop('disabled', false).text(gapnextRemediation.i18n.initBtn);
        });
    });

    // Inline field save (debounced)
    var saveTimers = {};
    $(document).on('change', '.gnr-field-save', function() {
        var $el = $(this);
        var ref = $el.closest('.gnr-item').data('ref');
        var field = $el.data('field');
        var value = $el.val();

        // Debounce per ref+field
        var key = ref + '_' + field;
        if (saveTimers[key]) clearTimeout(saveTimers[key]);

        saveTimers[key] = setTimeout(function() {
            $.post(ajaxUrl, {
                action: 'gapnext_remediation_update',
                nonce: nonce,
                submission_id: subId,
                question_ref: ref,
                field: field,
                value: value
            }).done(function(resp) {
                if (resp.success) {
                    // Update status badge if status changed
                    if (field === 'status') {
                        var $badge = $el.closest('.gnr-item').find('.gnr-status');
                        $badge.attr('class', 'gnr-status gnr-status-' + resp.data.status)
                              .text(resp.data.status.replace('_', ' '));
                    }
                    $el.css('border-color', '#16a34a');
                    setTimeout(function() { $el.css('border-color', ''); }, 1500);
                }
            });
        }, field === 'action' ? 1000 : 300); // longer debounce for text areas
    });

    // Textarea save on blur (for corrective action)
    $(document).on('blur', '.gnr-field-save[data-field="action"]', function() {
        $(this).trigger('change');
    });

    // Review: approve/reject
    $(document).on('click', '.gnr-btn-approve, .gnr-btn-reject', function() {
        var $btn = $(this);
        var $item = $btn.closest('.gnr-item');
        var ref = $item.data('ref');
        var decision = $btn.hasClass('gnr-btn-approve') ? 'approve' : 'reject';
        var feedback = '';

        if (decision === 'reject') {
            feedback = prompt(gapnextRemediation.i18n.rejectReason || 'Reason for rejection:');
            if (feedback === null) return; // cancelled
        }

        $btn.prop('disabled', true);
        $.post(ajaxUrl, {
            action: 'gapnext_remediation_review',
            nonce: nonce,
            submission_id: subId,
            question_ref: ref,
            decision: decision,
            feedback: feedback
        }).done(function(resp) {
            if (resp.success) {
                location.reload(); // simplest way to update all UI state
            } else {
                alert(resp.data || 'Error');
                $btn.prop('disabled', false);
            }
        });
    });

    // Add comment
    $(document).on('click', '.gnr-add-comment-btn', function() {
        var $item = $(this).closest('.gnr-item');
        var ref = $item.data('ref');
        var $input = $item.find('.gnr-comment-input');
        var comment = $input.val().trim();
        if (!comment) return;

        $.post(ajaxUrl, {
            action: 'gapnext_remediation_comment',
            nonce: nonce,
            submission_id: subId,
            question_ref: ref,
            comment: comment
        }).done(function(resp) {
            if (resp.success) {
                $input.val('');
                // Append to timeline
                var $list = $item.find('.gnr-timeline-list');
                var now = new Date().toLocaleDateString();
                $list.append('<li><span class="gnr-timeline-date">' + now + '</span> ' +
                    '<span class="gnr-timeline-actor">You:</span> ' + $('<span/>').text(comment).html() + '</li>');
            }
        });
    });

    // Toggle timeline visibility
    $(document).on('click', '.gnr-timeline-toggle', function() {
        $(this).closest('.gnr-timeline').find('.gnr-timeline-list').slideToggle(200);
    });

    // Filter items by status
    $(document).on('click', '.gnr-filter-btn', function() {
        var filter = $(this).data('filter');
        $('.gnr-filter-btn').removeClass('active');
        $(this).addClass('active');

        if (filter === 'all') {
            $('.gnr-item').show();
        } else if (filter === 'needs_action') {
            $('.gnr-item').each(function() {
                var status = $(this).data('status');
                $(this).toggle(status !== 'verified' && status !== 'open');
            });
        } else {
            $('.gnr-item').each(function() {
                $(this).toggle($(this).data('status') === filter);
            });
        }
    });

    // Create client user
    $(document).on('click', '#gnr-create-client-btn', function() {
        var $btn = $(this);
        var email = $('#gnr-client-email').val().trim();
        var name = $('#gnr-client-name').val().trim();

        if (!email || !name) {
            alert(gapnextRemediation.i18n.clientRequired || 'Email and name are required.');
            return;
        }

        $btn.prop('disabled', true);
        $.post(ajaxUrl, {
            action: 'gapnext_create_client',
            nonce: nonce,
            email: email,
            name: name,
            audit_uuid: auditUuid
        }).done(function(resp) {
            if (resp.success) {
                location.reload();
            } else {
                alert(resp.data || 'Error');
                $btn.prop('disabled', false);
            }
        });
    });

})(jQuery);
