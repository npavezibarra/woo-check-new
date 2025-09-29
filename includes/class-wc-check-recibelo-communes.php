<?php
/**
 * WooCheck Recíbelo commune mapper.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WooCheck_Recibelo_CommuneMapper {

    /**
     * Recíbelo commune catalog keyed by ID.
     *
     * @var array<int, string>
     */
    protected static $communes = [
        84  => 'Colina',
        85  => 'Lampa',
        86  => 'Tiltil',
        87  => 'Santiago centro',
        88  => 'Vitacura',
        89  => 'San Ramón',
        90  => 'San Miguel',
        91  => 'San Joaquín',
        92  => 'Renca',
        93  => 'Recoleta',
        94  => 'Quinta Normal',
        95  => 'Quilicura',
        96  => 'Pudahuel',
        97  => 'Providencia',
        98  => 'Peñalolén',
        99  => 'Pedro Aguirre Cerda',
        100 => 'Ñuñoa',
        101 => 'Maipú',
        102 => 'Macul',
        103 => 'Lo Prado',
        104 => 'Lo Espejo',
        105 => 'Lo Barnechea',
        106 => 'Las Condes',
        107 => 'La Reina',
        108 => 'La Pintana',
        109 => 'La Granja',
        110 => 'La Florida',
        111 => 'La Cisterna',
        112 => 'Independencia',
        113 => 'Huechuraba',
        114 => 'Estación Central',
        115 => 'El Bosque',
        116 => 'Conchalí',
        117 => 'Cerro Navia',
        118 => 'Cerrillos',
        119 => 'Puente Alto',
        120 => 'San José de Maipo',
        121 => 'Pirque',
        122 => 'San Bernardo',
        123 => 'Buin',
        124 => 'Paine',
        125 => 'Calera de Tango',
        126 => 'Melipilla',
        128 => 'Curacaví',
        129 => 'María Pinto',
        131 => 'Isla de Maipo',
        132 => 'El Monte',
        133 => 'Padre Hurtado',
        134 => 'Peñaflor',
        135 => 'Talagante',
    ];

    /**
     * Resolve a Recíbelo commune ID from a WooCommerce commune name.
     *
     * @param string $name Commune name.
     *
     * @return int|null
     */
    public static function get_commune_id( $name ) {
        if ( empty( $name ) ) {
            return null;
        }

        $normalized_input = self::normalize( $name );

        foreach ( self::$communes as $id => $commune_name ) {
            if ( self::normalize( $commune_name ) === $normalized_input ) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * Retrieve the canonical commune name for a given ID.
     *
     * @param int $id Commune identifier.
     *
     * @return string|null
     */
    public static function get_commune_name( $id ) {
        if ( isset( self::$communes[ $id ] ) ) {
            return self::$communes[ $id ];
        }

        return null;
    }

    /**
     * Alias for get_commune_name to match helper naming conventions.
     *
     * @param int $id Commune identifier.
     *
     * @return string|null
     */
    public static function get_name( $id ) {
        return self::get_commune_name( $id );
    }

    /**
     * Normalize a string for comparison.
     *
     * @param string $string Raw string.
     *
     * @return string
     */
    protected static function normalize( $string ) {
        $string = trim( (string) $string );

        if ( function_exists( 'mb_strtolower' ) ) {
            $string = mb_strtolower( $string, 'UTF-8' );
        } else {
            $string = strtolower( $string );
        }

        return str_replace(
            [ 'ñ', 'á', 'é', 'í', 'ó', 'ú' ],
            [ 'n', 'a', 'e', 'i', 'o', 'u' ],
            $string
        );
    }
}
