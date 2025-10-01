jQuery(document).ready(function($) {
    var FALLBACK_MESSAGE = (typeof WooCheckAjax !== 'undefined' && WooCheckAjax.fallback_message)
        ? WooCheckAjax.fallback_message
        : 'Estamos consultando el estado de este envío...';

    function normalizeProvider(provider) {
        if (!provider) {
            return '';
        }

        return String(provider).trim().toLowerCase();
    }

    function getProviderLabel(provider) {
        var normalized = normalizeProvider(provider);

        if (!normalized) {
            return 'Shipit';
        }

        var map = {
            recibelo: 'Recíbelo',
            shipit: 'Shipit'
        };

        if (map.hasOwnProperty(normalized)) {
            return map[normalized];
        }

        return normalized.charAt(0).toUpperCase() + normalized.slice(1);
    }

    function applyTrackingData($container, data, fallbackProvider) {
        if (!data) {
            return;
        }

        var providerFromData = normalizeProvider(data.provider);
        var providerSlug = providerFromData || normalizeProvider(fallbackProvider);
        var providerLabel = getProviderLabel(providerSlug);

        if (providerSlug) {
            $container.data('tracking-provider', providerSlug);
        }

        if (data.tracking_number) {
            $container.find('.tracking-number').text(data.tracking_number);
        }

        var courierLabel = data.courier ? data.courier : providerLabel;
        $container.find('.tracking-courier').text('(' + courierLabel + ')');

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

            var linkLabel = providerLabel ? 'Ver seguimiento en ' + providerLabel : 'Ver seguimiento';
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
        var providerSlug = normalizeProvider($container.data('tracking-provider'));

        if (providerSlug === 'recibelo') {
            return;
        }

        if (!orderId || !WooCheckAjax.ajax_url) {
            return;
        }

        $.post(WooCheckAjax.ajax_url, {
            action: 'woocheck_shipit_status',
            order_id: orderId
        }).done(function(response) {
            if (response && response.success) {
                applyTrackingData($container, response.data, providerSlug);
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
