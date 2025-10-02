jQuery(document).ready(function($) {
    var FALLBACK_MESSAGE = (typeof WooCheckAjax !== 'undefined' && WooCheckAjax.fallback_message)
        ? WooCheckAjax.fallback_message
        : 'Estamos consultando el estado de este envío...';
    var WAITING_COPY = 'Esperando tracking number...';
    var RECIBELO_TRACKING_URL = 'https://recibelo.cl/seguimiento';

    function setTrackingNumber($container, value) {
        $container.find('.tracking-number').text(value || '');
    }

    function updateCourierDisplay($container, courierName, options) {
        options = options || {};

        var $courier = $container.find('.tracking-courier');
        if ($courier.length === 0) {
            return;
        }

        var isRecibelo = !!options.isRecibelo;
        var providerLabel = options.providerLabel || '';
        var trackingUrl = options.trackingUrl || '';

        var displayName = courierName || '';
        if (!displayName && isRecibelo) {
            displayName = 'Recíbelo';
        }

        if (!displayName && providerLabel) {
            displayName = providerLabel;
        }

        $courier.empty();

        if (!displayName) {
            return;
        }

        var href = '';
        if (isRecibelo) {
            href = RECIBELO_TRACKING_URL;
        } else if (trackingUrl) {
            href = trackingUrl;
        }

        if (href) {
            var $anchor = $('<a/>', {
                text: displayName,
                href: href,
                target: '_blank',
                rel: 'noopener noreferrer'
            });

            $courier.append('(').append($anchor).append(')');
        } else {
            $courier.text('(' + displayName + ')');
        }
    }

    function setTrackingMessage($container, message, shouldShow) {
        var $message = $container.find('.tracking-message');
        if ($message.length === 0) {
            return;
        }

        var text = message || FALLBACK_MESSAGE;
        $message.text(text);

        if (shouldShow) {
            $message.show();
        } else {
            $message.hide();
        }
    }

    function shouldShowTrackingMessage($container) {
        var hasTrackingNumber = $.trim($container.find('.tracking-number').text()) !== '';
        var hasCourier = $.trim($container.find('.tracking-courier').text()) !== '';

        return !hasTrackingNumber && !hasCourier;
    }

    function hideLegacyTrackingLink($container) {
        var $linkWrapper = $container.find('.tracking-link');
        if ($linkWrapper.length === 0) {
            return;
        }

        $linkWrapper.hide().empty();
    }

    function applyTrackingData($container, data) {
        if (!data) {
            return;
        }

        var providerSlug = ($container.data('tracking-provider') || '').toString().toLowerCase();
        var providerLabel = ($container.data('tracking-provider-label') || '').toString();
        var isRecibelo = providerSlug === 'recibelo';

        var trackingNumber = data.tracking_number ? data.tracking_number.toString() : '';
        var courierName = data.courier ? data.courier.toString() : '';
        var trackingUrl = data.tracking_url ? data.tracking_url.toString() : '';
        var message = data.message ? data.message : FALLBACK_MESSAGE;

        if (!trackingNumber && isRecibelo) {
            setTrackingNumber($container, WAITING_COPY);
        } else {
            setTrackingNumber($container, trackingNumber);
        }

        updateCourierDisplay($container, courierName, {
            isRecibelo: isRecibelo,
            providerLabel: providerLabel,
            trackingUrl: trackingUrl
        });

        hideLegacyTrackingLink($container);

        setTrackingMessage($container, message, shouldShowTrackingMessage($container));
    }

    function refreshTracking() {
        var $container = $('#tracking-status');

        if ($container.length === 0 || typeof WooCheckAjax === 'undefined') {
            return;
        }

        var orderId = $container.data('order-id');
        var provider = $container.data('tracking-provider');
        var action = (provider && provider.toLowerCase() === 'recibelo')
            ? 'woocheck_recibelo_status'
            : 'woocheck_shipit_status';

        if (!orderId || !WooCheckAjax.ajax_url) {
            return;
        }

        $.post(WooCheckAjax.ajax_url, {
            action: action,
            order_id: orderId
        }).done(function(response) {
            if (response && response.success) {
                applyTrackingData($container, response.data);
            } else {
                setTrackingMessage($container, FALLBACK_MESSAGE, shouldShowTrackingMessage($container));
            }
        }).fail(function() {
            setTrackingMessage($container, FALLBACK_MESSAGE, shouldShowTrackingMessage($container));
        });
    }

    refreshTracking();
    setInterval(refreshTracking, 20000);
});
