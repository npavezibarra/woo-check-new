<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Check_Shipit {

    /**
     * Create a Shipit shipment for the provided WooCommerce order.
     *
     * @param WC_Order $order Order being fulfilled.
     *
     * @return bool Whether the shipment was created successfully.
     */
    public function create_shipment( $order ) {
        if ( ! $order instanceof WC_Order ) {
            return false;
        }

        $token = get_option( 'woo_check_shipit_token', '' );

        if ( empty( $token ) ) {
            error_log( 'Shipit token is missing. Skipping shipment.' );
            return false;
        }

        $comuna     = get_post_meta( $order->get_id(), 'shipping_comuna', true );
        $commune_id = $this->map_commune_to_id( $comuna );
        $items      = array_map(
            static function ( $item ) {
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

        $base_url = 'https://api.shipit.cl/v';
        $endpoint = rtrim( $base_url, '/' ) . '/shipments';

        $response = wp_remote_post(
            $endpoint,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( $data ),
                'timeout' => 30,
            ]
        );

        error_log( 'Shipit Request: ' . wp_json_encode( $data ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'Shipit HTTP: error ' . $response->get_error_code() );
            error_log( 'Shipit Response: ' . $response->get_error_message() );
            error_log( 'Shipit error: ' . $response->get_error_message() );
            return false;
        }

        $response_code    = wp_remote_retrieve_response_code( $response );
        $response_message = wp_remote_retrieve_response_message( $response );

        error_log( 'Shipit HTTP: ' . $response_code . ' ' . $response_message );

        $response_body = wp_remote_retrieve_body( $response );
        error_log( 'Shipit Response: ' . $response_body );

        $body = json_decode( $response_body, true );

        if ( isset( $body['tracking_number'] ) ) {
            update_post_meta(
                $order->get_id(),
                '_shipit_tracking',
                sanitize_text_field( $body['tracking_number'] )
            );
        }

        return true;
    }

    private function map_commune_to_id( $comuna ) {
        // TODO: build proper mapping table for Shipit
        return 308;
    }
}
