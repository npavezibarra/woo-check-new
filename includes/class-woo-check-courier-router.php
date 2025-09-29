<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WooCheck_Courier_Router {
    /**
     * Decide which courier should be used for the provided location metadata.
     *
     * @param mixed $commune_id Commune identifier.
     * @param mixed $region_id  Region identifier.
     *
     * @return string Either 'recibelo' or 'shipit'.
     */
    public static function decide( $commune_id, $region_id ) {
        if ( intval( $region_id ) === 7 ) {
            return 'recibelo';
        }

        return 'shipit';
    }
}
