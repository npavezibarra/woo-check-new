jQuery(function($) {
    var SHIPPING_MODAL_NAMESPACE = '.shippingModal';

    // Change the label text for the City field in both billing and shipping sections
    $('label[for="billing_city"]').text('Región *');
    $('label[for="shipping_city"]').text('Región *');

    function setupShippingModal() {
        var $checkoutForm = $('form.checkout.woocommerce-checkout');
        var $shippingCheckbox = $('#ship-to-different-address-checkbox');
        var $shippingOverlay = $('#shipping-modal-overlay');
        var $shippingClose = $('#shipping-modal-close');

        if (!$shippingCheckbox.length || !$shippingOverlay.length || !$checkoutForm.length) {
            return;
        }

        // Ensure the modal lives at the root of the checkout form for consistent positioning.
        if ($shippingOverlay.parent()[0] !== $checkoutForm[0]) {
            $shippingOverlay.appendTo($checkoutForm);
        }

        var $body = $('body');
        var $focusable = $shippingOverlay.find('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
        var $firstFocusable = $focusable.first();
        var $lastFocusable = $focusable.last();

        var openModal = function() {
            $shippingOverlay.addClass('is-visible').attr('aria-hidden', 'false');
            $body.addClass('shipping-modal-open');

            window.requestAnimationFrame(function() {
                if ($firstFocusable.length) {
                    $firstFocusable.trigger('focus');
                }
            });
        };

        var closeModal = function() {
            $shippingOverlay.removeClass('is-visible').attr('aria-hidden', 'true');
            $body.removeClass('shipping-modal-open');
        };

        var closeModalAndReset = function() {
            closeModal();

            if ($shippingCheckbox.is(':checked')) {
                $shippingCheckbox.prop('checked', false).trigger('change');
            }
        };

        $shippingCheckbox.off(SHIPPING_MODAL_NAMESPACE).on('change' + SHIPPING_MODAL_NAMESPACE, function() {
            if ($(this).is(':checked')) {
                openModal();
            } else {
                closeModal();
            }
        });

        $shippingClose.off(SHIPPING_MODAL_NAMESPACE).on('click' + SHIPPING_MODAL_NAMESPACE, function(event) {
            event.preventDefault();
            closeModalAndReset();
        });

        $shippingOverlay.off('click' + SHIPPING_MODAL_NAMESPACE).on('click' + SHIPPING_MODAL_NAMESPACE, function(event) {
            if ($(event.target).is($shippingOverlay)) {
                closeModalAndReset();
            }
        });

        $(document).off('keydown' + SHIPPING_MODAL_NAMESPACE).on('keydown' + SHIPPING_MODAL_NAMESPACE, function(event) {
            if (!$shippingOverlay.hasClass('is-visible')) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeModalAndReset();
                return;
            }

            if (event.key === 'Tab' && $focusable.length) {
                if (event.shiftKey) {
                    if ($(document.activeElement).is($firstFocusable)) {
                        event.preventDefault();
                        $lastFocusable.trigger('focus');
                    }
                } else if ($(document.activeElement).is($lastFocusable)) {
                    event.preventDefault();
                    $firstFocusable.trigger('focus');
                }
            }
        });

        if ($shippingCheckbox.is(':checked')) {
            openModal();
        } else {
            closeModal();
        }
    }

    setupShippingModal();
    $(document.body).on('updated_checkout', setupShippingModal);
});
