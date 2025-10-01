jQuery(document).ready(function($) {
    // Change the label text for the City field in both billing and shipping sections
    $('label[for="billing_city"]').text('Región *');
    $('label[for="shipping_city"]').text('Región *');

    // Ensure the checkout page title has a consistent ID for styling/hooks
    $('body.woocommerce-checkout h1.alignwide.wp-block-post-title').attr('id', 'checkout-page-title');

    const modalConfigs = [
        {
            formSelector: '.woocommerce-form-login',
            triggerSelector: '.showlogin',
            modalId: 'checkout-login-modal',
            ariaLabel: 'Formulario de inicio de sesión'
        },
        {
            formSelector: 'form.checkout_coupon',
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

        const closeModal = function() {
            $modal.removeClass('is-visible').attr('aria-hidden', 'true');

            if (!$('.checkout-modal.is-visible').not($modal).length) {
                $('body').removeClass('checkout-modal-open');
            }
        };

        const openModal = function() {
            $('.checkout-modal.is-visible').not($modal).each(function() {
                $(this).removeClass('is-visible').attr('aria-hidden', 'true');
            });

            $('body').addClass('checkout-modal-open');
            $modal.addClass('is-visible').attr('aria-hidden', 'false');

            setTimeout(function() {
                const $focusable = $modal.find('input, select, textarea, button, a[href], [tabindex]:not([tabindex="-1"])').filter(':visible');
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
            openModal();
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
    });
});
