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
        84  => 'COLINA',
        85  => 'LAMPA',
        86  => 'TILTIL',
        87  => 'SANTIAGO CENTRO',
        88  => 'VITACURA',
        89  => 'SAN RAMÓN',
        90  => 'SAN MIGUEL',
        91  => 'SAN JOAQUÍN',
        92  => 'RENCA',
        93  => 'RECOLETA',
        94  => 'QUINTA NORMAL',
        95  => 'QUILICURA',
        96  => 'PUDAHUEL',
        97  => 'PROVIDENCIA',
        98  => 'PEÑALOLÉN',
        99  => 'PEDRO AGUIRRE CERDA',
        100 => 'ÑUÑOA',
        101 => 'MAIPÚ',
        102 => 'MACUL',
        103 => 'LO PRADO',
        104 => 'LO ESPEJO',
        105 => 'LO BARNECHEA',
        106 => 'LAS CONDES',
        107 => 'LA REINA',
        108 => 'LA PINTANA',
        109 => 'LA GRANJA',
        110 => 'LA FLORIDA',
        111 => 'LA CISTERNA',
        112 => 'INDEPENDENCIA',
        113 => 'HUECHURABA',
        114 => 'ESTACIÓN CENTRAL',
        115 => 'EL BOSQUE',
        116 => 'CONCHALÍ',
        117 => 'CERRO NAVIA',
        118 => 'CERRILLOS',
        119 => 'PUENTE ALTO',
        120 => 'SAN JOSÉ DE MAIPO',
        121 => 'PIRQUE',
        122 => 'SAN BERNARDO',
        123 => 'BUIN',
        124 => 'PAINE',
        125 => 'CALERA DE TANGO',
        126 => 'MELIPILLA',
        128 => 'CURACAVÍ',
        129 => 'MARÍA PINTO',
        131 => 'ISLA DE MAIPO',
        132 => 'EL MONTE',
        133 => 'PADRE HURTADO',
        134 => 'PEÑAFLOR',
        135 => 'TALAGANTE',
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

        $normalized = self::normalize( $name );
        error_log( sprintf( "WooCheck Recibelo: Normalize input '%s' => '%s'", (string) $name, $normalized ) );

        foreach ( self::$communes as $id => $commune_name ) {
            $norm_ref = self::normalize( $commune_name );
            error_log( sprintf( "WooCheck Recibelo: Compare '%s' with ref '%s' (ID %d)", $normalized, $norm_ref, (int) $id ) );

            if ( $normalized === $norm_ref ) {
                error_log( sprintf( 'WooCheck Recibelo: Matched %s to ID %d', (string) $name, (int) $id ) );

                return (int) $id;
            }
        }

        error_log( sprintf( "WooCheck Recibelo: No match found for '%s'", (string) $name ) );

        return null;
    }

    /**
     * Normalize a string for comparison.
     *
     * @param string $string Raw string.
     *
     * @return string
     */
    protected static function normalize( $string ) {
        $string = (string) $string;

        if ( function_exists( 'mb_strtoupper' ) ) {
            $string = mb_strtoupper( $string, 'UTF-8' );
        } else {
            $string = strtoupper( $string );
        }

        $string = str_replace( [ 'Ñ' ], 'N', $string );

        if ( function_exists( 'iconv' ) ) {
            $converted = @iconv( 'UTF-8', 'ASCII//TRANSLIT', $string );

            if ( false !== $converted ) {
                $string = $converted;
            }
        }

        $string = preg_replace( '/[^A-Z0-9 ]/', '', $string );

        return trim( (string) $string );
    }
}
