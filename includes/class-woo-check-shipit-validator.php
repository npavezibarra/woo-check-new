<?php
defined( 'ABSPATH' ) || exit;

/**
 * Validator/Normalizer for Woo-Check → Shipit payload.
 */
class WooCheck_Shipit_Validator {

    /**
     * Ensure names are split correctly.
     */
    public static function normalize_name( $first_name, $last_name ) {
        $first_name = trim( (string) $first_name );
        $last_name  = trim( (string) $last_name );

        // Fallback: if last name is missing, split full name.
        if ( '' === $last_name && strpos( $first_name, ' ' ) !== false ) {
            $parts      = preg_split( '/\s+/', $first_name, 2 );
            $first_name = $parts[0];
            $last_name  = isset( $parts[1] ) ? $parts[1] : 'Cliente';
        }

        if ( '' === $first_name ) {
            $first_name = 'Cliente';
        }

        if ( '' === $last_name ) {
            $last_name = 'Anonimo';
        }

        return [ $first_name, $last_name ];
    }

    /**
     * Ensure phone is in +56 9 format.
     */
    public static function normalize_phone( $phone ) {
        $phone = preg_replace( '/\D+/', '', (string) $phone );

        if ( 9 === strlen( $phone ) && '9' === substr( $phone, 0, 1 ) ) {
            $phone = '+56' . $phone;
        } elseif ( 8 === strlen( $phone ) ) {
            $phone = '+569' . $phone;
        } elseif ( strncmp( $phone, '56', 2 ) !== 0 ) {
            $phone = '+56' . $phone;
        } else {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    /**
     * Ensure address has street + number + complement.
     */
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

    /**
     * Normalize commune (must always match JSON).
     */
    public static function normalize_commune( $comuna ) {
        $comuna = strtoupper( trim( (string) $comuna ) );

        if ( function_exists( 'remove_accents' ) ) {
            $comuna = remove_accents( $comuna );
        }

        if ( '' === $comuna ) {
            $comuna = 'SANTIAGO';
        }

        return $comuna;
    }
}
