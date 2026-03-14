/* global jQuery, gapnextAI */
jQuery(function ($) {
    'use strict';

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
                // Replace button + spinner with a download link
                $btn.next('.gapnext-ai-spinner').remove();
                $btn.replaceWith(
                    $('<a>')
                        .attr({ href: resp.data.download_url, target: '_blank', class: 'button button-small button-primary' })
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
});
