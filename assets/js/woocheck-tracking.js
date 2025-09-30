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
            if (response.success && response.data.message) {
                $container.find('.tracking-message').text(response.data.message);
            } else {
                $container.find('.tracking-message').text("Estamos consultando el estado de este envío...");
            }
        });
    }

    // Run immediately + every 20s
    refreshTracking();
    setInterval(refreshTracking, 20000);
});
