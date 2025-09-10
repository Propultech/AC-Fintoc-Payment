define(['jquery', 'mage/translate'], function ($) {
    'use strict';

    function bindHandlers($form, config) {
        var allowPartial = !!config.allowPartial;
        var maxAmount = parseFloat(config.maxAmount || 0) || 0;

        function updateMode() {
            var mode = $form.find('input[name="mode"]:checked').val();
            if (mode === 'amount' && allowPartial) {
                $form.find('#refund-amount').show();
                $form.find('#refund-amount-currency').show();
                $form.find('#amount-note').show();
                $form.find('#items-section').hide();
            } else if (mode === 'items' && allowPartial) {
                $form.find('#refund-amount').hide();
                $form.find('#refund-amount-currency').hide();
                $form.find('#amount-note').hide();
                $form.find('#items-section').show();
            } else {
                $form.find('#refund-amount').hide();
                $form.find('#refund-amount-currency').hide();
                $form.find('#amount-note').hide();
                $form.find('#items-section').hide();
            }
        }

        // Mode change
        $(document).on('change', 'input[name="mode"]', updateMode);

        // Amount input guard (if present)
        var $amount = $form.find('#refund-amount');
        if ($amount.length) {
            $amount.on('input', function () {
                var v = parseFloat(this.value || '0');
                if (v > maxAmount) this.value = maxAmount.toFixed(2);
                if (v < 0) this.value = '';
            });
        }

        // Submit validation
        $form.on('submit', function (e) {
            // Require non-empty comment
            var comment = ($form.find('#comment').val() || '').trim();
            if (!comment) {
                e.preventDefault();
                alert($.mage.__(config.messages && config.messages.noComment || 'Please enter a reason/comment for this refund.'));
                return false;
            }
            var mode = $form.find('input[name="mode"]:checked').val();
            if (mode === 'amount') {
                if (!allowPartial) {
                    e.preventDefault();
                    alert($.mage.__(config.messages && config.messages.partialDisabled || 'Partial refunds are disabled.'));
                    return false;
                }
                var v = 0;
                if ($amount.length) {
                    v = parseFloat($amount.val() || '0');
                }
                if (!(v > 0) || v > maxAmount + 0.0001) {
                    e.preventDefault();
                    var msg = $.mage.__(config.messages && config.messages.invalidAmount || 'Please enter a valid amount greater than 0 and up to %1');
                    msg = msg.replace('%1', (maxAmount || 0).toFixed(2));
                    alert(msg);
                    return false;
                }
            } else if (mode === 'items') {
                if (!allowPartial) {
                    e.preventDefault();
                    alert($.mage.__(config.messages && config.messages.partialDisabled || 'Partial refunds are disabled.'));
                    return false;
                }
                var any = false;
                $form.find('input[name^="qty["]').each(function () {
                    var q = parseFloat($(this).val() || '0');
                    if (q > 0) any = true;
                });
                if (!any) {
                    e.preventDefault();
                    alert($.mage.__(config.messages && config.messages.noQty || 'Please enter at least one quantity to refund.'));
                    return false;
                }
            }
        });

        // Initialize state
        updateMode();
    }

    // data-mage-init entry point
    return function (config, element) {
        var $el = $(element);
        bindHandlers($el.is('form') ? $el : $el.closest('form'), config || {});
    };
});
