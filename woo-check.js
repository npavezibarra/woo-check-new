jQuery(document).ready(function($) {
    // Change the label text for the City field in both billing and shipping sections
    $('label[for="billing_city"]').text('Región *');
    $('label[for="shipping_city"]').text('Región *');

    var $shippingCheckbox = $('#ship-to-different-address-checkbox');
    var $shippingOverlay = $('#shipping-modal-overlay');
    var $shippingClose = $('#shipping-modal-close');

    if ($shippingCheckbox.length && $shippingOverlay.length) {
        var openModal = function() {
            $shippingOverlay.addClass('is-visible').attr('aria-hidden', 'false');
            $('body').addClass('shipping-modal-open');
        };

        var closeModal = function() {
            $shippingOverlay.removeClass('is-visible').attr('aria-hidden', 'true');
            $('body').removeClass('shipping-modal-open');
        };

        var closeModalAndReset = function() {
            closeModal();
            if ($shippingCheckbox.is(':checked')) {
                $shippingCheckbox.prop('checked', false).trigger('change');
            }
        };

        $shippingCheckbox.on('change.shippingModal', function() {
            if ($(this).is(':checked')) {
                openModal();
            } else {
                closeModal();
            }
        });

        $shippingClose.on('click.shippingModal', function(event) {
            event.preventDefault();
            closeModalAndReset();
        });

        $shippingOverlay.on('click.shippingModal', function(event) {
            if ($(event.target).is($shippingOverlay)) {
                closeModalAndReset();
            }
        });

        $(document).on('keydown.shippingModal', function(event) {
            if (event.key === 'Escape' && $shippingOverlay.hasClass('is-visible')) {
                closeModalAndReset();
            }
        });

        if ($shippingCheckbox.is(':checked')) {
            $shippingCheckbox.trigger('change');
        } else {
            closeModal();
        }
    }
});
