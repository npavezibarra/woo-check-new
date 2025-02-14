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
            .toLowerCase(); // Convierte a minúsculas
    }

    // Crear el mapa comuna -> región utilizando la normalización
    const comunaToRegionMap = {};
    const comunaExactMap = {}; // Mapa para obtener el nombre exacto de la comuna
    comunasChile.forEach(entry => {
        entry.comunas.forEach(comuna => {
            const normalizedComuna = normalizeString(comuna);
            comunaToRegionMap[normalizedComuna] = entry.region; // Mapear al nombre de la región
            comunaExactMap[normalizedComuna] = comuna; // Almacena el nombre exacto
        });
    });

    console.log("Comuna to Region Map:", comunaToRegionMap);

    // Crear la lista de comunas originales para el autocompletado
    const comunaList = [];
    comunasChile.forEach(entry => {
        entry.comunas.forEach(comuna => {
            comunaList.push(comuna);
        });
    });

    // Función para registrar todas las opciones de región
    function logRegionOptions(regionSelect) {
        const options = [];
        $(`${regionSelect} option`).each(function () {
            options.push($(this).text());
        });
        console.log(`Region options for ${regionSelect}:`, options);
    }

    // Llamar a la función para ambas regiones
    logRegionOptions('#billing_state');
    logRegionOptions('#shipping_state');

    // Sincronizar la región con la comuna seleccionada
    function syncRegionWithComuna(comunaInput, regionSelect) {
        const selectedComunaNormalized = normalizeString($(comunaInput).val());
        console.log("Selected Comuna (normalized):", selectedComunaNormalized);

        const associatedRegion = comunaToRegionMap[selectedComunaNormalized];
        console.log("Associated Region:", associatedRegion);

        if (associatedRegion) {
            const normalizedAssociatedRegion = normalizeString(associatedRegion);
            console.log("Normalized Associated Region:", normalizedAssociatedRegion);

            const regionOption = $(`${regionSelect} option`).filter(function () {
                return normalizeString($(this).text()) === normalizedAssociatedRegion;
            });

            console.log("Matched Region Option:", regionOption);

            if (regionOption.length > 0) {
                const regionValue = regionOption.val();
                console.log("Region Value Found:", regionValue);
                $(regionSelect).val(regionValue).trigger('change');
                $('body').trigger('update_checkout');
            } else {
                console.error("No se encontró una opción para la región:", associatedRegion);
                alert("No se encontró la región correspondiente a la comuna seleccionada.");
            }
        } else {
            alert("Esta comuna no es válida. Por favor, selecciona una comuna de Chile.");
            $(comunaInput).val('');
            const regionSelectId = comunaInput.attr('id') === 'billing_comuna' ? '#billing_state' : '#shipping_state';
            $(regionSelectId).val('').trigger('change');
        }
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
            } else {
                alert("Esta comuna no es válida. Por favor, selecciona una comuna de Chile.");
                comunaInput.val('');
                // Opcional: Limpiar la región asociada
                $(regionSelect).val('').trigger('change');
            }
        }
    });

    // Estilizar los campos de región para que no sean editables
    function styleRegionFields() {
        $('#billing_state, #shipping_state').css({
            'background-color': '#f9f9f9',
            'pointer-events': 'none',
            'cursor': 'not-allowed'
        });
    }

    // Aplicar el estilo al actualizar el checkout
    $(document.body).on('updated_checkout', function () {
        styleRegionFields();
    });

    // Aplicar el estilo inicialmente
    styleRegionFields();

    // Sincronizar la región al perder el foco del campo comuna
    $('#billing_comuna, #shipping_comuna').on('blur', function () {
        const comunaInput = $(this);
        const regionSelect = comunaInput.attr('id') === 'billing_comuna' ? '#billing_state' : '#shipping_state';
        syncRegionWithComuna(comunaInput, regionSelect);
    });

    // Asegurarse de que los campos de región estén habilitados al enviar el formulario
    $('form.checkout, form.woocommerce-address-form').on('submit', function () {
        $('#billing_state, #shipping_state').prop('disabled', false);
    });
});
