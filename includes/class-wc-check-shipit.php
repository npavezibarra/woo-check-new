<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Check_Shipit {

    public static function create_shipment( $order ) {
        if ( ! $order instanceof WC_Order ) {
            return false;
        }

        $token = get_option( 'woo_check_shipit_token', '' );

        if ( empty( $token ) ) {
            error_log( 'Shipit token is missing. Skipping shipment.' );
            return false;
        }

        $comuna      = get_post_meta( $order->get_id(), 'shipping_comuna', true );
        $commune_id  = self::map_commune_to_id( $comuna );
        $items       = array_map(
            function ( $item ) {
                return [
                    'name'  => $item->get_name(),
                    'qty'   => $item->get_quantity(),
                    'price' => $item->get_total(),
                ];
            },
            $order->get_items()
        );

        $data = [
            'shipment' => [
                'reference' => 'WC-' . $order->get_id(),
                'items'     => $items,
                'sizes'     => [
                    'width'  => 10,
                    'height' => 10,
                    'length' => 10,
                    'weight' => 1,
                ],
                'destiny'   => [
                    'full_name'    => $order->get_formatted_shipping_full_name(),
                    'email'        => $order->get_billing_email(),
                    'phone'        => $order->get_shipping_phone(),
                    'street'       => $order->get_shipping_address_1(),
                    'commune_id'   => $commune_id,
                    'commune_name' => get_post_meta( $order->get_id(), 'billing_comuna', true ),
                    'kind'         => 'home_delivery',
                ],
            ],
        ];

        $response = wp_remote_post(
            'https://api.shipit.cl/v/shipments',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( $data ),
            ]
        );

        error_log( 'Shipit Request: ' . wp_json_encode( $data ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'Shipit Response: ' . $response->get_error_message() );
            error_log( 'Shipit error: ' . $response->get_error_message() );
            return false;
        }

        error_log( 'Shipit Response: ' . wp_remote_retrieve_body( $response ) );

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['tracking_number'] ) ) {
            update_post_meta(
                $order->get_id(),
                '_shipit_tracking',
                sanitize_text_field( $body['tracking_number'] )
            );
        }

        return true;
    }

    private static function map_commune_to_id( $comuna ) {
        // TODO: build proper mapping table for Shipit
        return 308;
    }
}
