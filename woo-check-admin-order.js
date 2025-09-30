/* global comunasChile */
(function ($) {
    if ('undefined' === typeof comunasChile) {
        return;
    }

    function normalizeString(str) {
        return (str || '')
            .toString()
            .trim()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[’']/g, '')
            .replace(/[^a-zA-ZñÑ0-9\s]/g, '')
            .toLowerCase()
            .replace(/\s+/g, ' ');
    }

    function levenshteinDistance(a, b) {
        const matrix = [];
        const aLength = a.length;
        const bLength = b.length;

        for (let i = 0; i <= bLength; i++) {
            matrix[i] = [i];
        }

        for (let j = 0; j <= aLength; j++) {
            matrix[0][j] = j;
        }

        for (let i = 1; i <= bLength; i++) {
            for (let j = 1; j <= aLength; j++) {
                if (b.charAt(i - 1) === a.charAt(j - 1)) {
                    matrix[i][j] = matrix[i - 1][j - 1];
                } else {
                    matrix[i][j] = Math.min(
                        matrix[i - 1][j - 1] + 1,
                        matrix[i][j - 1] + 1,
                        matrix[i - 1][j] + 1
                    );
                }
            }
        }

        return matrix[bLength][aLength];
    }

    const comunaEntries = [];
    const comunaExactMap = {};
    const comunaToRegionMap = {};

    comunasChile.forEach(entry => {
        const regionName = entry.region || '';

        (entry.comunas || []).forEach(comuna => {
            const normalized = normalizeString(comuna);

            comunaEntries.push({
                label: comuna,
                value: comuna,
                normalized,
            });

            comunaExactMap[normalized] = comuna;
            comunaToRegionMap[normalized] = regionName;
        });
    });

    function findClosestComuna(value) {
        const normalizedValue = normalizeString(value);

        if (!normalizedValue) {
            return null;
        }

        let closest = null;
        let bestScore = 0;

        comunaEntries.forEach(entry => {
            const distance = levenshteinDistance(normalizedValue, entry.normalized);
            const maxLength = Math.max(normalizedValue.length, entry.normalized.length) || 1;
            const similarity = 1 - distance / maxLength;

            if (similarity > bestScore) {
                bestScore = similarity;
                closest = entry.value;
            }
        });

        return bestScore >= 0.6 ? closest : null;
    }

    function buildAutocompleteSource(request, response) {
        const term = normalizeString(request.term || '');
        let matches;

        if (!term) {
            matches = comunaEntries;
        } else {
            matches = comunaEntries.filter(entry => entry.normalized.indexOf(term) !== -1);
        }

        response(matches.map(entry => ({
            label: entry.label,
            value: entry.value,
        })));
    }

    function updateRegion($regionSelect, regionName) {
        if (!$regionSelect || !$regionSelect.length) {
            return;
        }

        if (!regionName) {
            if ($regionSelect.val()) {
                $regionSelect.val('').trigger('change');
            }
            return;
        }

        const normalizedTarget = normalizeString(regionName);
        let found = false;

        $regionSelect.find('option').each(function () {
            const $option = $(this);
            const normalizedOption = normalizeString($option.text());

            if (
                normalizedOption === normalizedTarget ||
                normalizedOption.indexOf(normalizedTarget) !== -1 ||
                normalizedTarget.indexOf(normalizedOption) !== -1
            ) {
                if ($regionSelect.val() !== $option.val()) {
                    $regionSelect.val($option.val()).trigger('change');
                }

                found = true;
                return false;
            }

            return undefined;
        });

        if (!found) {
            $regionSelect.data('woo-check-pending-region', regionName);
        } else {
            $regionSelect.removeData('woo-check-pending-region');
        }
    }

    function setValidComuna($input, $regionSelect, comuna) {
        const normalized = normalizeString(comuna);
        const exactName = comunaExactMap[normalized] || comuna;
        const regionName = comunaToRegionMap[normalized];

        $input.val(exactName);
        $input.data('woo-check-comuna-valid', true);

        if (regionName) {
            updateRegion($regionSelect, regionName);
        }
    }

    function invalidateComuna($input, $regionSelect, attemptedValue, options) {
        const opts = $.extend({ silent: false, skipEmpty: false }, options);

        updateRegion($regionSelect, '');
        $input.data('woo-check-comuna-valid', false);

        if (!opts.skipEmpty || attemptedValue) {
            $input.val('');
        }

        if (opts.silent) {
            return false;
        }

        if (!opts.skipEmpty || attemptedValue) {
            const suggestion = findClosestComuna(attemptedValue);

            if (suggestion) {
                window.alert(
                    "No encontramos \"" + attemptedValue + "\". ¿Quisiste decir \"" + suggestion + "\"? Selecciónala desde la lista desplegable."
                );
            } else {
                window.alert(
                    "No encontramos \"" + attemptedValue + "\". Selecciona una comuna desde la lista desplegable."
                );
            }
        }

        return false;
    }

    function validateComunaField($input, $regionSelect, options) {
        const opts = $.extend({ silent: false, requireValue: false, skipEmpty: false }, options);
        const value = $.trim($input.val());

        if (!value) {
            $input.data('woo-check-comuna-valid', !opts.requireValue);

            if (!opts.skipEmpty) {
                updateRegion($regionSelect, '');
            }

            if (opts.requireValue && !opts.silent) {
                window.alert('Selecciona una comuna desde la lista desplegable.');
            }

            return !opts.requireValue;
        }

        const normalized = normalizeString(value);

        if (Object.prototype.hasOwnProperty.call(comunaExactMap, normalized)) {
            setValidComuna($input, $regionSelect, comunaExactMap[normalized]);
            return true;
        }

        return invalidateComuna($input, $regionSelect, value, opts);
    }

    function setupComunaField(selectorConfig) {
        const $input = $(selectorConfig.input);
        const $regionSelect = $(selectorConfig.region);

        if (!$input.length || $input.data('wooCheckAdminComunaInit')) {
            return;
        }

        $input.data('wooCheckAdminComunaInit', true);

        $input.autocomplete({
            source: buildAutocompleteSource,
            minLength: 0,
            select: function (event, ui) {
                setValidComuna($input, $regionSelect, ui.item.value);
                return false;
            },
            focus: function (event, ui) {
                event.preventDefault();
                $input.val(ui.item.value);
            },
        });

        $input.on('focus', function () {
            const currentValue = $.trim($input.val());

            if (!currentValue) {
                $input.autocomplete('search', '');
            }
        });

        $input.on('autocompletechange', function () {
            validateComunaField($input, $regionSelect, { silent: true });
        });

        $input.on('blur', function () {
            validateComunaField($input, $regionSelect, { skipEmpty: true });
        });

        const initialValue = $.trim($input.val());

        if (initialValue) {
            const normalized = normalizeString(initialValue);
            const regionName = comunaToRegionMap[normalized];

            if (regionName) {
                $input.data('woo-check-comuna-valid', true);
                updateRegion($regionSelect, regionName);
            } else {
                $input.data('woo-check-comuna-valid', false);
            }
        } else {
            $input.data('woo-check-comuna-valid', false);
        }
    }

    const fieldSelectors = [
        { input: '#_billing_comuna', region: '#_billing_state' },
        { input: '#_shipping_comuna', region: '#_shipping_state' },
    ];

    function initAdminComunaFields() {
        fieldSelectors.forEach(setupComunaField);
    }

    function validateAllFields(options) {
        let allValid = true;

        fieldSelectors.forEach(config => {
            const $input = $(config.input);
            const $regionSelect = $(config.region);

            if (!$input.length) {
                return;
            }

            const isValid = validateComunaField($input, $regionSelect, options);

            if (!isValid) {
                allValid = false;
            }
        });

        return allValid;
    }

    $(function () {
        initAdminComunaFields();

        const orderData = document.getElementById('order_data');

        if (orderData && window.MutationObserver) {
            const observer = new MutationObserver(initAdminComunaFields);
            observer.observe(orderData, { childList: true, subtree: true });
        }

        $(document.body).on('click', '#order_data .edit_address', function () {
            setTimeout(initAdminComunaFields, 50);
        });

        $('#post').on('submit', function (event) {
            if (!validateAllFields({ silent: true, requireValue: true })) {
                event.preventDefault();
                event.stopImmediatePropagation();

                window.alert('Selecciona comunas válidas desde la lista desplegable antes de guardar el pedido.');

                fieldSelectors.some(config => {
                    const $input = $(config.input);

                    if ($input.length && !$input.data('woo-check-comuna-valid')) {
                        $input.focus();
                        return true;
                    }

                    return false;
                });

                return false;
            }

            return true;
        });
    });
})(jQuery);
