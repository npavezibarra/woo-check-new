jQuery(document).ready(function($) {
    function refreshTracking() {
        var $container = $('#tracking-status');

        if ($container.length === 0) {
            return;
        }

        var orderId = $container.data('order-id');

        if (!orderId || typeof WooCheckAjax === 'undefined' || !WooCheckAjax.ajax_url) {
            return;
        }

        $.post(WooCheckAjax.ajax_url, {
            action: 'woocheck_shipit_status',
            order_id: orderId
        }, function(response) {
            var defaultMessage = "Estamos consultando el estado de este envío...";

            if (!response || !response.success || !response.data) {
                $container.find('.tracking-message').text(defaultMessage);
                return;
            }

            var data = response.data;

            if (data.tracking_number) {
                $container.find('.tracking-number').text(data.tracking_number);
            }

            if (data.courier) {
                $container.find('.tracking-courier').text(data.courier);
            }

            if (data.message) {
                $container.find('.tracking-message').text(data.message);
            } else {
                $container.find('.tracking-message').text(defaultMessage);
            }
        });
    }

    // Run immediately + every 20s
    refreshTracking();
    setInterval(refreshTracking, 20000);
});
