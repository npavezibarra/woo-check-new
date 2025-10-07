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
        const normalized = normalizeString($(comunaInput).val());
        const region = comunaToRegionMap[normalized];

        console.log("syncRegionWithComuna called for:", $(comunaInput).val(), "→ region:", region);

        if (!region) {
            console.warn("No region found for:", normalized);
            return;
        }

        const normalizedRegion = normalizeString(region);
        const option = $(`${regionSelect} option`).filter(function () {
            return normalizeString($(this).text()) === normalizedRegion;
        });

        if (option.length > 0) {
            const regionValue = option.val();
            $(regionSelect).val(regionValue).trigger('change');
            console.log("✅ Region set to:", regionValue);
            $('body').trigger('update_checkout');
        } else {
            console.warn("⚠️ No matching region option found in select for:", normalizedRegion);
        }
    }

    // --- AUTOCOMPLETE INITIALIZATION ---
    $('#billing_city, #shipping_city').autocomplete({
        source: function(request, response) {
            const term = request.term;
            const regex = new RegExp("^" + $.ui.autocomplete.escapeRegex(term), "i");
            const matches = comunaList.filter(c => regex.test(c));
            response(matches);
        },
        minLength: 1,
        select: function (event, ui) {
            const input = $(this);
            const regionSelect = input.attr('id') === 'billing_city' ? '#billing_state' : '#shipping_state';
            input.val(ui.item.value);
            syncRegionWithComuna(input, regionSelect);
        },
        change: function(event, ui) {
            const input = $(this);
            const regionSelect = input.attr('id') === 'billing_city' ? '#billing_state' : '#shipping_state';
            const normalized = normalizeString(input.val());

            if (comunaToRegionMap.hasOwnProperty(normalized)) {
                const exact = comunaExactMap[normalized];
                input.val(exact);
                syncRegionWithComuna(input, regionSelect);
            } else {
                const closest = findClosestComuna(input.val());
                if (closest) {
                    input.val(closest);
                    syncRegionWithComuna(input, regionSelect);
                }
            }
        }
    });

    // --- REGION SYNC ON BLUR ---
    $('#billing_city, #shipping_city').on('blur', function () {
        const input = $(this);
        const regionSelect = input.attr('id') === 'billing_city' ? '#billing_state' : '#shipping_state';
        syncRegionWithComuna(input, regionSelect);
    });

    // --- ENSURE STATE IS ENABLED ON SUBMIT ---
    $('form.checkout, form.woocommerce-address-form').on('submit', function () {
        $('#billing_state, #shipping_state').prop('disabled', false);
    });

    // --- STYLE REGION FIELDS ---
    $('#billing_state, #shipping_state').css({
        'background-color': '#f9f9f9',
        'pointer-events': 'none',
        'cursor': 'not-allowed'
    });

    console.log("✅ WooCheck autocomplete fully initialized.");
});
