<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Check_Recibelo {

    /**
     * Create a Recíbelo shipment for the provided WooCommerce order.
     *
     * @param WC_Order $order Order being fulfilled.
     *
     * @return bool Whether the shipment was created successfully.
     */
    public function create_shipment( $order ) {
        if ( ! $order instanceof WC_Order ) {
            return false;
        }

        $token = get_option( 'woo_check_recibelo_token', '' );

        if ( empty( $token ) ) {
            error_log( 'Recíbelo token is missing. Skipping shipment.' );
            return false;
        }

        $endpoint = sprintf( 'https://app.recibelo.cl/webhook/%s/woocommerce', rawurlencode( $token ) );

        $items = array_map(
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
            'order_reference' => 'WC-' . $order->get_id(),
            'name'            => $order->get_formatted_shipping_full_name(),
            'address'         => $order->get_shipping_address_1(),
            'comuna'          => get_post_meta( $order->get_id(), 'billing_comuna', true ),
            'region'          => $order->get_shipping_state(),
            'phone'           => $order->get_shipping_phone(),
            'email'           => $order->get_billing_email(),
            'items'           => $items,
        ];

        $response = wp_remote_post(
            $endpoint,
            [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( $data ),
                'timeout' => 30,
            ]
        );

        error_log( 'Recibelo Request: ' . wp_json_encode( $data ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'Recibelo HTTP: error ' . $response->get_error_code() );
            error_log( 'Recibelo Response: ' . $response->get_error_message() );
            error_log( 'Recíbelo error: ' . $response->get_error_message() );
            return false;
        }

        $response_code    = wp_remote_retrieve_response_code( $response );
        $response_message = wp_remote_retrieve_response_message( $response );

        error_log( 'Recibelo HTTP: ' . $response_code . ' ' . $response_message );

        $response_body = wp_remote_retrieve_body( $response );
        error_log( 'Recibelo Response: ' . $response_body );

        $body = json_decode( $response_body, true );

        if ( isset( $body['tracking_id'] ) ) {
            update_post_meta(
                $order->get_id(),
                '_recibelo_tracking',
                sanitize_text_field( $body['tracking_id'] )
            );
        }

        return true;
    }
}
