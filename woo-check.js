jQuery(document).ready(function($) {
    // Change the label text for the City field in both billing and shipping sections
    $('label[for="billing_city"]').text('Región *');
    $('label[for="shipping_city"]').text('Región *');

    // Ensure the checkout page title has a consistent ID for styling/hooks
    $('body.woocommerce-checkout h1.alignwide.wp-block-post-title').attr('id', 'checkout-page-title');

    const focusableSelector = 'a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])';

    const modalConfigs = [
        {
            formSelector: '.woocommerce-form-login',
            triggerSelector: '.showlogin',
            modalId: 'checkout-login-modal',
            ariaLabel: 'Formulario de inicio de sesión'
        },
        {
            formSelector: '#woocommerce-checkout-form-coupon',
            triggerSelector: '.showcoupon',
            modalId: 'checkout-coupon-modal',
            ariaLabel: 'Formulario de cupón'
        }
    ];

    modalConfigs.forEach(function(config) {
        const $form = $(config.formSelector);

        if (!$form.length) {
            return;
        }

        const $modal = $('<div />', {
            class: 'checkout-modal',
            id: config.modalId,
            'aria-hidden': 'true'
        });

        const $panel = $('<div />', {
            class: 'checkout-modal__panel',
            role: 'dialog',
            'aria-modal': 'true',
            'aria-label': config.ariaLabel
        });

        const $close = $('<button />', {
            type: 'button',
            class: 'checkout-modal__close',
            'aria-label': 'Cerrar',
            html: '&times;'
        });

        $form.show().appendTo($panel);
        $panel.prepend($close);
        $modal.append($panel);
        $('body').append($modal);

        let $activeTrigger = null;

        const closeModal = function(restoreFocus = true) {
            if (!$modal.hasClass('is-visible')) {
                return;
            }

            $modal.removeClass('is-visible').attr('aria-hidden', 'true');

            if ($activeTrigger && $activeTrigger.length) {
                $activeTrigger.attr('aria-expanded', 'false');

                if (restoreFocus) {
                    $activeTrigger.trigger('focus');
                }
            }

            $activeTrigger = null;

            if (!$('.checkout-modal.is-visible').not($modal).length) {
                $('body').removeClass('checkout-modal-open');
            }
        };

        $modal.data('wooCheckClose', closeModal);

        const openModal = function($trigger) {
            $('.checkout-modal.is-visible').not($modal).each(function() {
                const $otherModal = $(this);
                const otherClose = $otherModal.data('wooCheckClose');

                if (typeof otherClose === 'function') {
                    otherClose(false);
                } else {
                    $otherModal.removeClass('is-visible').attr('aria-hidden', 'true');
                }
            });

            $('body').addClass('checkout-modal-open');
            $modal.addClass('is-visible').attr('aria-hidden', 'false');

            if ($trigger && $trigger.length) {
                $activeTrigger = $trigger;
                $activeTrigger.attr('aria-expanded', 'true');
            } else {
                $activeTrigger = null;
            }

            setTimeout(function() {
                const $focusable = $panel.find(focusableSelector).filter(':visible');
                const $firstField = $focusable.not('.checkout-modal__close').first();

                if ($firstField.length) {
                    $firstField.trigger('focus');
                } else {
                    $close.trigger('focus');
                }
            }, 50);
        };

        $(document).on('click', config.triggerSelector, function(event) {
            event.preventDefault();
            event.stopImmediatePropagation();
            openModal($(this));
        });

        $close.on('click', function() {
            closeModal();
        });

        $modal.on('click', function(event) {
            if ($(event.target).is($modal)) {
                closeModal();
            }
        });

        $(document).on('keydown.' + config.modalId, function(event) {
            if (event.key === 'Escape' && $modal.hasClass('is-visible')) {
                closeModal();
            }
        });

        $(config.triggerSelector).each(function() {
            const $trigger = $(this);

            if (typeof $trigger.attr('aria-expanded') === 'undefined') {
                $trigger.attr('aria-expanded', 'false');
            }
        });
    });
});
