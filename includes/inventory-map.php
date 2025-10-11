<?php
/**
 * Pack → Books mapping using verified WooCommerce product IDs
 * Each key (pack ID) maps to an array of book IDs.
 */

return [
    // --- Multi-book packs ---
    8234 => [5863, 39, 58], // Pack "Tiempo, Imperios y Misterios"
    7302 => [5863, 56, 59], // OVNi + Insurrección + La Torre
    5398 => [54, 56, 64, 2313], // Tsunami + Insurrección + Revolución + Debut
    4635 => [2313, 822], // Debut & Despedida + Momentos Musicales
    2315 => [2313, 64, 56], // Debut & Despedida + Revolución + Insurrección
    2084 => [59, 39, 58, 822], // 4 libros de NO política
    1573 => [822, 39], // Momentos Musicales + Julio César
    832  => [822, 59], // Momentos Musicales + La Torre
    828  => [822, 58], // Momentos Musicales + Envejezca
    65   => [64, 56, 54], // Revolución + Insurrección + Tsunami
    66   => [64, 56], // Revolución + Insurrección
    68   => [64, 59], // Revolución + La Torre
    70   => [39, 59], // Julio César + La Torre
    61   => [54, 56], // Tsunami + Insurrección
    62   => [39, 59, 58], // Julio César + La Torre + Envejezca
    63   => [58, 59], // Envejezca + La Torre

    // --- Complex “bundle” products ---
    8231 => [9249, 5863, 2313, 822, 64, 56, 58, 59, 39], // El Noveno Círculo (9 libros, no Identidad Animal)
    2930 => [9249, 5863, 7336, 2313, 822, 64, 56, 58, 59, 39], // La Torre Pack (10 libros)

    // --- Pack Librerías variants ---
    9455 => [9249], // Para No Tirarse por la Ventana - Pack Librerías
    5618 => [56],   // Insurrección (Pack Librerías)
    2753 => [2313], // Debut & Despedida (Pack Librerías)
];
