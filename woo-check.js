jQuery(function($) {
    // Change the label text for the City field in both billing and shipping sections
    $('label[for="billing_city"]').text('Región *');
    $('label[for="shipping_city"]').text('Región *');

    // Ensure the checkout page title has a consistent ID for styling/hooks
    $('body.woocommerce-checkout h1.alignwide.wp-block-post-title').attr('id', 'checkout-page-title');

    const focusableSelector = 'a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])';
    const $body = $(document.body);

    const panelTransitionDuration = 250;

    const modalConfigs = [
        {
            formSelector: '.woocommerce-form-login',
            triggerSelector: 'a.showlogin',
            modalId: 'checkout-login-modal',
            ariaLabel: 'Formulario de inicio de sesión'
        },
        {
            formSelector: '#woocommerce-checkout-form-coupon',
            triggerSelector: 'a.showcoupon',
            modalId: 'checkout-coupon-modal',
            ariaLabel: 'Formulario de cupón'
        }
    ];

    const initialiseModal = function(config, $form) {
        let modalData = $form.data('wooCheckModalData');

        if (!modalData) {
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

            $form.removeAttr('style');
            $panel.append($close);
            $panel.append($form);
            $modal.append($panel);
            $('body').append($modal);

            let $activeTrigger = null;
            let hideTimer = null;

            const closeModal = function(restoreFocus = true) {
                if (!$modal.hasClass('is-visible')) {
                    return;
                }

                window.clearTimeout(hideTimer);

                $modal.attr('aria-hidden', 'true');
                $modal.removeClass('is-ready');

                hideTimer = window.setTimeout(function() {
                    $modal.removeClass('is-visible');

                    if (!$('.checkout-modal.is-visible').not($modal).length) {
                        $body.removeClass('checkout-modal-open');
                    }
                }, panelTransitionDuration);

                if ($activeTrigger && $activeTrigger.length) {
                    $activeTrigger.attr('aria-expanded', 'false');

                    if (restoreFocus) {
                        $activeTrigger.trigger('focus');
                    }
                }

                $activeTrigger = null;
            };

            $modal.data('wooCheckClose', closeModal);

            const openModal = function($trigger) {
                window.clearTimeout(hideTimer);

                $form.show();

                $('.checkout-modal.is-visible').not($modal).each(function() {
                    const $otherModal = $(this);
                    const otherClose = $otherModal.data('wooCheckClose');

                    if (typeof otherClose === 'function') {
                        otherClose(false);
                    } else {
                        $otherModal.removeClass('is-visible').attr('aria-hidden', 'true');
                    }
                });

                $modal.removeClass('is-ready');
                $body.addClass('checkout-modal-open');
                $modal.addClass('is-visible').attr('aria-hidden', 'false');

                const raf = window.requestAnimationFrame || function(callback) {
                    return window.setTimeout(callback, 16);
                };

                raf(function() {
                    $modal.addClass('is-ready');
                });

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
                }, panelTransitionDuration);
            };

            $close.on('click', function() {
                closeModal();
            });

            $modal.on('click', function(event) {
                if (event.target === $modal[0]) {
                    closeModal();
                }
            });

            $(document).on('keydown.' + config.modalId, function(event) {
                if (event.key === 'Escape' && $modal.hasClass('is-visible')) {
                    closeModal();
                }
            });

            modalData = {
                modal: $modal,
                openModal: openModal,
                closeModal: closeModal,
                form: $form
            };

            $form.data('wooCheckModalData', modalData);
        }

        const namespace = '.woo-check-modal-' + config.modalId;

        $body.off('click', config.triggerSelector);
        $body.off('click' + namespace, config.triggerSelector);

        if (config.triggerSelector.startsWith('a.')) {
            const secondarySelector = config.triggerSelector.slice(1);

            $body.off('click', secondarySelector);
            $body.off('click' + namespace, secondarySelector);
        }

        $body.on('click' + namespace, config.triggerSelector, function(event) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            modalData.openModal($(this));
        });

        let $triggers = $(config.triggerSelector);

        if (config.triggerSelector.startsWith('a.')) {
            $triggers = $triggers.add($(config.triggerSelector.slice(1)));
        }

        $triggers.each(function() {
            const $trigger = $(this);

            $trigger.attr({
                'aria-controls': config.modalId,
                'aria-expanded': 'false'
            });
        });
    };

    const initialiseModals = function() {
        modalConfigs.forEach(function(config) {
            const $forms = $(config.formSelector);

            if (!$forms.length) {
                return;
            }

            $forms.each(function() {
                initialiseModal(config, $(this));
            });
        });
    };

    initialiseModals();
    $(window).on('load', initialiseModals);
    $(document.body).on('updated_checkout', initialiseModals);
});
