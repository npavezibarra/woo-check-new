// woo-check-autocomplete.js

jQuery(document).ready(function ($) {

    // Función para normalizar cadenas
    function normalizeString(str) {
        return str
            .trim() // Elimina espacios al inicio y al final
            .normalize('NFD') // Divide letras y acentos
            .replace(/[\u0300-\u036f]/g, '') // Elimina los acentos
            .replace(/[’']/g, '') // Elimina apóstrofes
            .replace(/[^a-zA-ZñÑ0-9\s]/g, '') // Retiene "ñ" y "Ñ"
            .toLowerCase() // Convierte a minúsculas
            .replace(/\s+/g, ' '); // Normaliza los espacios intermedios
    }

    // Crear el mapa comuna -> región utilizando la normalización
    const comunaToRegionMap = {};
    const regionCodeMap = {
        'arica y parinacota': 'CL-AP',
        'tarapaca': 'CL-TA',
        'antofagasta': 'CL-AN',
        'atacama': 'CL-AT',
        'coquimbo': 'CL-CO',
        'valparaiso': 'CL-VS',
        'metropolitana de santiago': 'CL-RM',
        'region metropolitana': 'CL-RM',
        'libertador general bernardo ohiggins': 'CL-LI',
        'maule': 'CL-ML',
        'nuble': 'CL-NB',
        'biobio': 'CL-BI',
        'araucania': 'CL-AR',
        'los rios': 'CL-LR',
        'los lagos': 'CL-LL',
        'aysen': 'CL-AI',
        'magallanes': 'CL-MA'
    };
    const comunaExactMap = {}; // Mapa para obtener el nombre exacto de la comuna
    comunasChile.forEach(entry => {
        entry.comunas.forEach(comuna => {
            const normalizedComuna = normalizeString(comuna);
            comunaToRegionMap[normalizedComuna] = entry.region; // Mapear al nombre de la región
            comunaExactMap[normalizedComuna] = comuna; // Almacena el nombre exacto
        });
    });

    // Crear la lista de comunas originales para el autocompletado
    const comunaList = [];
    comunasChile.forEach(entry => {
        entry.comunas.forEach(comuna => {
            comunaList.push(comuna);
        });
    });

    // Calcular la distancia de Levenshtein entre dos cadenas
    function levenshteinDistance(a, b) {
        const matrix = [];

        for (let i = 0; i <= b.length; i++) {
            matrix[i] = [i];
        }

        for (let j = 0; j <= a.length; j++) {
            matrix[0][j] = j;
        }

        for (let i = 1; i <= b.length; i++) {
            for (let j = 1; j <= a.length; j++) {
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

        return matrix[b.length][a.length];
    }

    // Buscar la comuna más parecida a un valor ingresado
    function findClosestComuna(value) {
        const normalizedValue = normalizeString(value);

        if (!normalizedValue) {
            return null;
        }

        let closestComuna = null;
        let bestScore = 0;

        comunaList.forEach(comuna => {
            const normalizedComuna = normalizeString(comuna);
            const distance = levenshteinDistance(normalizedValue, normalizedComuna);
            const maxLength = Math.max(normalizedValue.length, normalizedComuna.length) || 1;
            const similarity = 1 - (distance / maxLength);

            if (similarity > bestScore) {
                bestScore = similarity;
                closestComuna = comuna;
            }
        });

        return bestScore >= 0.6 ? closestComuna : null;
    }

    function getSuggestionContainer(comunaInput) {
        let container = comunaInput.siblings('.woo-check-comuna-suggestion');

        if (!container.length) {
            container = $('<div>', {
                class: 'woo-check-comuna-suggestion',
            });
            comunaInput.after(container);
        }

        return container;
    }

    function clearComunaSuggestion(comunaInput) {
        comunaInput.removeClass('woo-check-comuna-input--invalid');
        const container = comunaInput.siblings('.woo-check-comuna-suggestion');
        if (container.length) {
            container.empty().removeClass('woo-check-comuna-suggestion--visible');
        }
    }

    function showComunaSuggestion(comunaInput, suggestion, regionSelect) {
        const container = getSuggestionContainer(comunaInput);
        container.empty();

        if (!suggestion) {
            comunaInput.addClass('woo-check-comuna-input--invalid');
            container
                .removeClass('woo-check-comuna-suggestion--has-option')
                .addClass('woo-check-comuna-suggestion--visible')
                .text('No encontramos una coincidencia para la comuna ingresada.');
            return;
        }

        comunaInput.addClass('woo-check-comuna-input--invalid');
        container
            .addClass('woo-check-comuna-suggestion--visible woo-check-comuna-suggestion--has-option')
            .append($('<span>').text('¿Quisiste decir '));

        const suggestionRegion = comunaToRegionMap[normalizeString(suggestion)];

        const suggestionButton = $('<button>', {
            type: 'button',
            class: 'woo-check-comuna-suggestion__button',
            text: suggestion,
        });

        suggestionButton.on('click', function () {
            comunaInput.val(suggestion);
            clearComunaSuggestion(comunaInput);
            syncRegionWithComuna(comunaInput, regionSelect);
        });

        container.append(suggestionButton);

        if (suggestionRegion) {
            container.append(
                $('<span>').text(` en la región ${suggestionRegion}?`)
            );
        } else {
            container.append($('<span>').text('?'));
        }
    }

    function handleInvalidComuna(comunaInput, regionSelect) {
        const currentValue = comunaInput.val();
        const normalizedCurrentValue = normalizeString(currentValue);

        if (!normalizedCurrentValue) {
            clearComunaSuggestion(comunaInput);
            $(regionSelect).val('').trigger('change');
            return;
        }

        const suggestion = findClosestComuna(currentValue);
        showComunaSuggestion(comunaInput, suggestion, regionSelect);
        $(regionSelect).val('').trigger('change');
    }

    // Sincronizar la región con la comuna seleccionada
    function syncRegionWithComuna(comunaInput, regionSelect) {
        const $comunaInput = $(comunaInput);
        const $regionSelect = $(regionSelect);
        const selectedComunaNormalized = normalizeString($comunaInput.val());
        const associatedRegion = comunaToRegionMap[selectedComunaNormalized];
        console.log("Comuna entered:", $comunaInput.val());
        console.log("Normalized comuna:", selectedComunaNormalized);
        console.log("Associated region:", associatedRegion);

        if (!associatedRegion) {
            console.warn("No associated region found for:", selectedComunaNormalized);
            handleInvalidComuna($comunaInput, regionSelect);
            return;
        }

        const normalizedRegion = normalizeString(associatedRegion);
        const regionCode = regionCodeMap[normalizedRegion] || null;
        console.log("Normalized region:", normalizedRegion, "Code:", regionCode);

        let matched = false;

        $regionSelect.find('option').each(function () {
            const optionText = normalizeString($(this).text());
            const optionValue = $(this).val().toUpperCase();

            if (
                optionText === normalizedRegion ||
                optionText.includes(normalizedRegion) ||
                normalizedRegion.includes(optionText) ||
                (regionCode && optionValue === regionCode)
            ) {
                console.log("Found matching region option:", optionText, "Value:", optionValue);
                $regionSelect.val(optionValue).trigger('change');
                matched = true;
                clearComunaSuggestion($comunaInput);
                return false; // break
            }
        });

        if (!matched && regionCode) {
            console.log("No match found in options, setting manually:", regionCode);
            $regionSelect.val(regionCode).trigger('change');
            matched = true;
        }

        if (matched) {
            console.log("Region successfully set to:", $regionSelect.val());
            console.log("Region field value now:", $regionSelect.val());
            // Force WooCommerce recalculation
            setTimeout(() => {
                console.log("Triggering WooCommerce update_checkout()");
                $('body').trigger('update_checkout');
            }, 500);
        } else {
            console.warn("Failed to match any region for:", associatedRegion);
            handleInvalidComuna($comunaInput, regionSelect);
        }

        // Sync shipping state too
        $('#shipping_state').val($('#billing_state').val()).trigger('change');
    }

    // Inicializar el autocompletado con una función personalizada
    $('#billing_comuna, #shipping_comuna').autocomplete({
        source: function(request, response) {
            const term = request.term;
            // Crear una expresión regular que considere la 'Ñ' y 'ñ'
            const regex = new RegExp("^" + $.ui.autocomplete.escapeRegex(term), "i");
            const matches = comunaList.filter(function(comuna) {
                return regex.test(comuna);
            });
            response(matches);
        },
        minLength: 1,
        select: function (event, ui) {
            const comunaInput = $(this);
            const regionSelect = comunaInput.attr('id') === 'billing_comuna' ? '#billing_state' : '#shipping_state';
            comunaInput.val(ui.item.value); // Establecer el valor exacto
            syncRegionWithComuna(comunaInput, regionSelect);
        },
        change: function(event, ui) {
            const comunaInput = $(this);
            const regionSelect = comunaInput.attr('id') === 'billing_comuna' ? '#billing_state' : '#shipping_state';
            const inputValNormalized = normalizeString(comunaInput.val());

            if (comunaToRegionMap.hasOwnProperty(inputValNormalized)) {
                // Obtener el nombre exacto de la comuna
                const exactComuna = comunaExactMap[inputValNormalized];
                comunaInput.val(exactComuna);
                syncRegionWithComuna(comunaInput, regionSelect);
                return;
            }

            const closestComuna = findClosestComuna(comunaInput.val());
            if (closestComuna) {
                const closestNormalized = normalizeString(closestComuna);
                const associatedRegion = comunaToRegionMap[closestNormalized];

                if (associatedRegion && closestNormalized === inputValNormalized) {
                    comunaInput.val(closestComuna);
                    syncRegionWithComuna(comunaInput, regionSelect);
                    return;
                }
            }

            handleInvalidComuna(comunaInput, regionSelect);
        }
    });

    $('#billing_comuna, #shipping_comuna').on('input', function () {
        clearComunaSuggestion($(this));
    });

    // Estilizar los campos de región para que no sean editables
    function styleRegionFields() {
        $('#billing_state, #shipping_state').css({
            'background-color': '#f9f9f9'
        });
    }

    // Aplicar el estilo al actualizar el checkout
    $(document.body).on('updated_checkout', function () {
        styleRegionFields();
    });

    // Aplicar el estilo inicialmente
    styleRegionFields();

    // Sincronizar la región al perder el foco del campo comuna
    // Asegurarse de que los campos de región estén habilitados al enviar el formulario
    $('form.checkout, form.woocommerce-address-form').on('submit', function () {
        $('#billing_state, #shipping_state').prop('disabled', false);
    });

    $(document.body).on('change', '#billing_state, #shipping_state', function () {
        $('body').trigger('update_checkout');
    });

    function bindCheckoutComunaEvents() {
        const comunaSelectors = [
            { input: '#billing_city', region: '#billing_state', label: 'Billing' },
            { input: '#billing_comuna', region: '#billing_state', label: 'Billing' },
            { input: '#shipping_city', region: '#shipping_state', label: 'Shipping' },
            { input: '#shipping_comuna', region: '#shipping_state', label: 'Shipping' },
        ];

        comunaSelectors.forEach(({ input, region, label }) => {
            const $field = $(input);

            if ($field.length) {
                $field.off('input.wooCheck change.wooCheck blur.wooCheck');
                $field.on('input.wooCheck change.wooCheck blur.wooCheck', function () {
                    console.log(`${label} comuna input changed:`, $(this).val());
                    syncRegionWithComuna($(this), region);
                });
            }
        });
    }

    console.log("WooCheck Autocomplete initialized");
    bindCheckoutComunaEvents();

    $(document.body).on('updated_checkout', function () {
        console.log("WooCommerce checkout updated, re-binding commune events if needed");
        bindCheckoutComunaEvents();
    });
});
