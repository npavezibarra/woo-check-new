jQuery(document).ready(function($) {
    var FALLBACK_MESSAGE = (typeof WooCheckAjax !== 'undefined' && WooCheckAjax.fallback_message)
        ? WooCheckAjax.fallback_message
        : 'Estamos consultando el estado de este envío...';

    function getProviderInfo(provider) {
        var providerSlug = '';

        if (typeof provider === 'string' && provider.trim() !== '') {
            providerSlug = provider.trim().toLowerCase();
        }

        var providerLabels = {
            'recibelo': 'Recíbelo',
            'shipit': 'Shipit'
        };

        var providerLabel = providerLabels[providerSlug] || '';

        return {
            slug: providerSlug,
            label: providerLabel
        };
    }

    function applyTrackingData($container, data, providerInfo) {
        if (!data) {
            return;
        }

        if (data.tracking_number) {
            $container.find('.tracking-number').text(data.tracking_number);
        }

        var courierLabel = providerInfo && providerInfo.label ? providerInfo.label : '';

        if (!courierLabel && data.courier) {
            courierLabel = data.courier;
        }

        if (courierLabel) {
            $container.find('.tracking-courier').text('(' + courierLabel + ')');
        } else {
            $container.find('.tracking-courier').text('');
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

            var linkLabel = courierLabel ? 'Ver seguimiento en ' + courierLabel : 'Ver seguimiento';
            $anchor.attr('href', data.tracking_url).text(linkLabel);
            $linkWrapper.show();
        } else {
            $linkWrapper.hide();
            $anchor.remove();
        }
    }

    function refreshTracking($container, providerInfo) {
        if ($container.length === 0 || typeof WooCheckAjax === 'undefined') {
            return;
        }

        var orderId = $container.data('order-id');

        if (!orderId || !WooCheckAjax.ajax_url) {
            return;
        }

        if (providerInfo.slug === 'recibelo') {
            return;
        }

        $.post(WooCheckAjax.ajax_url, {
            action: 'woocheck_shipit_status',
            order_id: orderId
        }).done(function(response) {
            if (response && response.success) {
                applyTrackingData($container, response.data, providerInfo);
            } else {
                $container.find('.tracking-message').text(FALLBACK_MESSAGE);
            }
        }).fail(function() {
            $container.find('.tracking-message').text(FALLBACK_MESSAGE);
        });
    }

    var $trackingContainer = $('#tracking-status');
    var providerInfo = getProviderInfo($trackingContainer.data('tracking-provider'));

    if ($trackingContainer.length === 0) {
        return;
    }

    if (providerInfo.label) {
        var $courier = $trackingContainer.find('.tracking-courier');

        if ($courier.length) {
            $courier.text('(' + providerInfo.label + ')');
        }
    }

    refreshTracking($trackingContainer, providerInfo);

    if (providerInfo.slug !== 'recibelo') {
        setInterval(function() {
            refreshTracking($trackingContainer, providerInfo);
        }, 20000);
    }
});
