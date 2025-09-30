jQuery(document).ready(function($) {
    var FALLBACK_MESSAGE = (typeof WooCheckAjax !== 'undefined' && WooCheckAjax.fallback_message)
        ? WooCheckAjax.fallback_message
        : 'Estamos consultando el estado de este envío...';

    function applyTrackingData($container, data) {
        if (!data) {
            return;
        }

        if (data.tracking_number) {
            $container.find('.tracking-number').text(data.tracking_number);
        }

        if (data.courier) {
            $container.find('.tracking-courier').text('(' + data.courier + ')');
        } else {
            $container.find('.tracking-courier').text('(Shipit)');
        }

        var message = data.message ? data.message : FALLBACK_MESSAGE;
        $container.find('.tracking-message').text(message);

        var $linkWrapper = $container.find('.tracking-link');
        var $anchor = $linkWrapper.find('a');

        if (data.tracking_url) {
            if ($anchor.length === 0) {
                $anchor = $('<a/>', {
                    target: '_blank',
                    rel: 'noopener noreferrer'
                }).appendTo($linkWrapper.empty());
            }

            var linkLabel = data.courier ? 'Ver seguimiento en ' + data.courier : 'Ver seguimiento';
            $anchor.attr('href', data.tracking_url).text(linkLabel);
            $linkWrapper.show();
        } else {
            $linkWrapper.hide();
            $anchor.remove();
        }
    }

    function refreshTracking() {
        var $container = $('#tracking-status');

        if ($container.length === 0 || typeof WooCheckAjax === 'undefined') {
            return;
        }

        var orderId = $container.data('order-id');

        if (!orderId || !WooCheckAjax.ajax_url) {
            return;
        }

        $.post(WooCheckAjax.ajax_url, {
            action: 'woocheck_shipit_status',
            order_id: orderId
        }).done(function(response) {
            if (response && response.success) {
                applyTrackingData($container, response.data);
            } else {
                $container.find('.tracking-message').text(FALLBACK_MESSAGE);
            }
        }).fail(function() {
            $container.find('.tracking-message').text(FALLBACK_MESSAGE);
        });
    }

    refreshTracking();
    setInterval(refreshTracking, 20000);
});
