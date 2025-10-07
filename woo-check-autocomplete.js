// woo-check-autocomplete.js

console.log("✅ WooCheck JS file LOADED at top of script");

jQuery(document).ready(function ($) {
    console.log("✅ jQuery(document).ready() is running");
    console.log("jQuery version:", $.fn.jquery);
    console.log("billing_city exists?", $('#billing_city').length > 0);

    // --- SAFETY CHECK FOR comunasChile ---
    if (typeof comunasChile === "undefined") {
        console.error("❌ comunasChile is NOT defined. Autocomplete cannot run.");
        return;
    } else {
        console.log("✅ comunasChile is loaded, proceeding with autocomplete.");
    }

    // --- NORMALIZATION FUNCTION ---
    function normalizeString(str) {
        return str
            .trim()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[’']/g, '')
            .replace(/[^a-zA-ZñÑ0-9\s]/g, '')
            .toLowerCase()
            .replace(/\s+/g, ' ');
    }

    // --- CREATE COMUNA → REGION MAPS ---
    const comunaToRegionMap = {};
    const comunaExactMap = {};
    comunasChile.forEach(entry => {
        entry.comunas.forEach(comuna => {
            const normalized = normalizeString(comuna);
            comunaToRegionMap[normalized] = entry.region;
            comunaExactMap[normalized] = comuna;
        });
    });

    // --- CREATE FULL COMUNA LIST ---
    const comunaList = [];
    comunasChile.forEach(entry => {
        entry.comunas.forEach(comuna => comunaList.push(comuna));
    });

    // --- REGION CODE MAP ---
    const regionCodeMap = {
        'arica y parinacota': 'CL-AP',
        'tarapaca': 'CL-TA',
        'antofagasta': 'CL-AN',
        'atacama': 'CL-AT',
        'coquimbo': 'CL-CO',
        'valparaiso': 'CL-VS',
        'metropolitana de santiago': 'CL-RM',
        'region metropolitana': 'CL-RM',
        'región metropolitana': 'CL-RM',
        'libertador general bernardo ohiggins': 'CL-LI',
        'libertador general bernardo o higgins': 'CL-LI',
        'maule': 'CL-ML',
        'nuble': 'CL-NB',
        'ñuble': 'CL-NB',
        'biobio': 'CL-BI',
        'bío-bío': 'CL-BI',
        'araucania': 'CL-AR',
        'la araucania': 'CL-AR',
        'los rios': 'CL-LR',
        'los lagos': 'CL-LL',
        'aysen': 'CL-AI',
        'aysén': 'CL-AI',
        'magallanes': 'CL-MA'
    };

    function handleInvalidComuna(comunaInput, regionSelect) {
        const $input = comunaInput instanceof jQuery ? comunaInput : $(comunaInput);
        const $region = regionSelect instanceof jQuery ? regionSelect : $(regionSelect);

        console.warn('⚠️ handleInvalidComuna invoked for:', $input.val());
        $region.val('').trigger('change');
        setTimeout(() => {
            $('body').trigger('update_checkout');
        }, 300);
    }

    // --- LEVENSHTEIN DISTANCE FUNCTION ---
    function levenshteinDistance(a, b) {
        const matrix = [];
        for (let i = 0; i <= b.length; i++) matrix[i] = [i];
        for (let j = 0; j <= a.length; j++) matrix[0][j] = j;

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

    // --- FIND CLOSEST MATCH ---
    function findClosestComuna(value) {
        const normalizedValue = normalizeString(value);
        if (!normalizedValue) return null;

        let closest = null;
        let bestScore = 0;

        comunaList.forEach(comuna => {
            const normalizedComuna = normalizeString(comuna);
            const distance = levenshteinDistance(normalizedValue, normalizedComuna);
            const maxLength = Math.max(normalizedValue.length, normalizedComuna.length) || 1;
            const similarity = 1 - (distance / maxLength);

            if (similarity > bestScore) {
                bestScore = similarity;
                closest = comuna;
            }
        });

        return bestScore >= 0.6 ? closest : null;
    }

    // --- REGION SYNC ---
    function syncRegionWithComuna(comunaInput, regionSelect) {
        const $comunaInput = comunaInput instanceof jQuery ? comunaInput : $(comunaInput);
        const $regionSelect = regionSelect instanceof jQuery ? regionSelect : $(regionSelect);
        const normalized = normalizeString($comunaInput.val());
        const associatedRegion = comunaToRegionMap[normalized];

        console.log("📍 syncRegionWithComuna called for:", $comunaInput.val(), "→ Region:", associatedRegion);

        if (!associatedRegion) {
            console.warn("⚠️ No region found for:", normalized);
            handleInvalidComuna($comunaInput, $regionSelect);
            return;
        }

        const normalizedRegion = normalizeString(associatedRegion);
        const regionOption = $regionSelect.find('option').filter(function () {
            const optionText = normalizeString($(this).text());
            return optionText === normalizedRegion || optionText.includes(normalizedRegion) || normalizedRegion.includes(optionText);
        });

        let regionValue = null;

        if (regionOption.length > 0) {
            regionValue = regionOption.first().val();
        } else if (regionCodeMap[normalizedRegion]) {
            regionValue = regionCodeMap[normalizedRegion];
        }

        if (regionValue) {
            $regionSelect.val(regionValue).trigger('change');
            console.log("✅ Region set to:", regionValue);

            if ($regionSelect.attr('id') === 'billing_state') {
                $('#shipping_state').val(regionValue).trigger('change');
            }

            setTimeout(() => {
                console.log("🔁 Triggering WooCommerce update_checkout from syncRegionWithComuna");
                $('body').trigger('update_checkout');
            }, 300);
        } else {
            console.warn("⚠️ No matching region option found for:", normalizedRegion);
            handleInvalidComuna($comunaInput, $regionSelect);
        }
    }

    const comunaFieldSelector = '#billing_comuna, #shipping_comuna, #billing_city, #shipping_city';

    // --- AUTOCOMPLETE INITIALIZATION ---
    $(comunaFieldSelector).autocomplete({
        source: function(request, response) {
            const term = request.term;
            const regex = new RegExp("^" + $.ui.autocomplete.escapeRegex(term), "i");
            const matches = comunaList.filter(function(comuna) {
                return regex.test(comuna);
            });
            response(matches);
        },
        minLength: 1,
        select: function (event, ui) {
            const comunaInput = $(this);
            const isBillingField = comunaInput.attr('id') === 'billing_comuna' || comunaInput.attr('id') === 'billing_city';
            const regionSelect = isBillingField ? '#billing_state' : '#shipping_state';
            const selectedComuna = ui.item.value;

            console.log("🟢 Commune selected:", selectedComuna);

            comunaInput.val(selectedComuna);
            comunaInput.trigger('change').trigger('input');
            if (comunaInput[0]) {
                comunaInput[0].dispatchEvent(new Event('change', { bubbles: true }));
            }

            syncRegionWithComuna(comunaInput, regionSelect);

            setTimeout(() => {
                console.log("🔄 Triggering WooCommerce update_checkout");
                $('body').trigger('update_checkout');
            }, 500);
        },
        change: function(event, ui) {
            const comunaInput = $(this);
            const isBillingField = comunaInput.attr('id') === 'billing_comuna' || comunaInput.attr('id') === 'billing_city';
            const regionSelect = isBillingField ? '#billing_state' : '#shipping_state';
            const normalized = normalizeString(comunaInput.val());
            const associatedRegion = comunaToRegionMap[normalized];

            if (associatedRegion) {
                console.log("🟢 Commune recognized:", comunaInput.val(), "→ Region:", associatedRegion);
                const exact = comunaExactMap[normalized] || comunaInput.val();
                comunaInput.val(exact);
                syncRegionWithComuna(comunaInput, regionSelect);
            } else {
                const closest = findClosestComuna(comunaInput.val());

                if (closest) {
                    console.log("ℹ️ Using closest comuna match:", closest, "for input:", comunaInput.val());
                    comunaInput.val(closest);
                    syncRegionWithComuna(comunaInput, regionSelect);
                } else {
                    console.warn("⚠️ Unknown comuna:", comunaInput.val());
                    handleInvalidComuna(comunaInput, regionSelect);
                }
            }
        }
    });

    // --- REGION SYNC ON BLUR ---
    $(comunaFieldSelector).on('blur', function () {
        const input = $(this);
        const isBillingField = input.attr('id') === 'billing_comuna' || input.attr('id') === 'billing_city';
        const regionSelect = isBillingField ? '#billing_state' : '#shipping_state';
        syncRegionWithComuna(input, regionSelect);
    });

    // --- ENSURE STATE IS ENABLED ON SUBMIT ---
    $('form.checkout, form.woocommerce-address-form').on('submit', function () {
        $('#billing_state, #shipping_state').prop('disabled', false);
    });

    // --- STYLE REGION FIELDS ---
    $('#billing_state, #shipping_state').css({
        'background-color': '#f9f9f9'
    });
});

console.log("✅ WooCheck autocomplete fully initialized and listening for commune input.");
