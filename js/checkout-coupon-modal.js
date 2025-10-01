jQuery(function ($) {
    var $couponForm = $('#woocommerce-checkout-form-coupon');

    if (!$couponForm.length) {
        return;
    }

    $('.showcoupon').attr('aria-expanded', 'false');

    var $overlay = $('<div />', {
        class: 'coupon-modal-overlay',
        'aria-hidden': 'true'
    });

    var $modal = $('<div />', {
        class: 'coupon-modal',
        role: 'dialog',
        'aria-modal': 'true',
        'aria-label': $couponForm.data('modal-label') || 'Formulario de cupón'
    });

    var lastTrigger = null;
    var focusableSelector = [
        'a[href]',
        'area[href]',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        'button:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    $couponForm.show().appendTo($modal);
    $overlay.append($modal);
    $('body').append($overlay);

    var closeModal = function () {
        if (!$overlay.is(':visible')) {
            return;
        }

        $overlay.attr('aria-hidden', 'true').removeClass('is-visible');

        $('body').removeClass('checkout-modal-open');

        if (lastTrigger) {
            lastTrigger.attr('aria-expanded', 'false');
            lastTrigger.trigger('focus');
        }
    };

    var openModal = function () {
        if ($overlay.is(':visible')) {
            return;
        }

        $('body').addClass('checkout-modal-open');
        $overlay.attr('aria-hidden', 'false').addClass('is-visible');

        var $focusable = $modal.find(focusableSelector).filter(':visible');

        if ($focusable.length) {
            $focusable.first().trigger('focus');
        }
    };

    $(document).on('click', '.showcoupon', function (event) {
        event.preventDefault();

        lastTrigger = $(this);
        lastTrigger.attr('aria-expanded', 'true');

        openModal();
    });

    $overlay.on('click', function (event) {
        if ($(event.target).is($overlay)) {
            closeModal();
        }
    });

    $(document).on('keydown.checkoutCouponModal', function (event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
});
