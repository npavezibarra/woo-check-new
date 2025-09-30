jQuery(document).ready(function($) {
    function refreshTracking() {
        var $container = $('#tracking-status');
        if ($container.length === 0) {
            return;
        }

        var orderId = $container.data('order-id');
        if (!orderId || typeof woocheck_ajax === 'undefined' || !woocheck_ajax.ajax_url) {
            return;
        }

        $.ajax({
            url: woocheck_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'woocheck_shipit_status',
                order_id: orderId
            },
            success: function(response) {
                if (response && response.success && response.data && response.data.html) {
                    $container.html(response.data.html);
                }
            }
        });
    }

    refreshTracking();
    setInterval(refreshTracking, 60000);
});
