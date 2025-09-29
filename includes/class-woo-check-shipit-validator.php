<?php
defined( 'ABSPATH' ) || exit;

/**
 * WooCheck Shipit Validator & Normalizer
 */
class WooCheck_Shipit_Validator {

    public static function normalize_name( $first_name, $last_name ) {
        $first_name = trim( (string) $first_name );
        $last_name  = trim( (string) $last_name );

        // Split if last_name missing
        if ( '' === $last_name && strpos( $first_name, ' ' ) !== false ) {
            $parts      = preg_split( '/\s+/', $first_name, 2 );
            $first_name = $parts[0];
            $last_name  = isset( $parts[1] ) ? $parts[1] : 'Customer';
        }

        if ( '' === $first_name ) {
            $first_name = 'Customer';
        }
        if ( '' === $last_name ) {
            $last_name = 'Anon';
        }

        return [ $first_name, $last_name ];
    }

    public static function normalize_phone( $phone ) {
        $phone = preg_replace( '/\D+/', '', (string) $phone );

        if ( 9 === strlen( $phone ) && '9' === substr( $phone, 0, 1 ) ) {
            $phone = '+56' . $phone;
        } elseif ( 8 === strlen( $phone ) ) {
            $phone = '+569' . $phone;
        } elseif ( strpos( $phone, '56' ) !== 0 ) {
            $phone = '+56' . $phone;
        } else {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    public static function normalize_address( $address_1, $address_2 ) {
        $address_1 = (string) $address_1;
        $address_2 = (string) $address_2;

        $street     = trim( $address_1 );
        $number     = '';
        $complement = $address_2;

        if ( preg_match( '/^([\p{L}\s]+)\s+(\d+.*)$/u', $address_1, $matches ) ) {
            $street = trim( $matches[1] );
            $number = trim( $matches[2] );
        }

        if ( '' === $street ) {
            $street = 'Direccion';
        }
        if ( '' === $number ) {
            $number = 'S/N';
        }

        return [ $street, $number, $complement ];
    }

    public static function normalize_commune( $comuna ) {
        $comuna = remove_accents( (string) $comuna );
        $comuna = strtoupper( trim( $comuna ) );

        if ( '' === $comuna ) {
            $comuna = 'SANTIAGO';
        }

        return $comuna;
    }
}
