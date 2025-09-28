<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Check_Recibelo {

    public static function create_shipment( $order ) {
        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $token = get_option( 'woo_check_recibelo_token', '' );

        if ( empty( $token ) ) {
            error_log( 'Recíbelo token is missing. Skipping shipment.' );
            return;
        }

        $endpoint = sprintf( 'https://app.recibelo.cl/webhook/%s/woocommerce', rawurlencode( $token ) );

        $data = [
            'order_reference' => 'WC-' . $order->get_id(),
            'name'            => $order->get_formatted_shipping_full_name(),
            'address'         => $order->get_shipping_address_1(),
            'comuna'          => get_post_meta( $order->get_id(), 'shipping_comuna', true ),
            'region'          => $order->get_shipping_state(),
            'phone'           => $order->get_shipping_phone(),
            'email'           => $order->get_billing_email(),
        ];

        $response = wp_remote_post(
            $endpoint,
            [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( $data ),
            ]
        );

        error_log( 'Recibelo Request: ' . wp_json_encode( $data ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'Recibelo Response: ' . $response->get_error_message() );
            error_log( 'Recíbelo error: ' . $response->get_error_message() );
            return;
        }

        error_log( 'Recibelo Response: ' . wp_remote_retrieve_body( $response ) );

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['tracking_id'] ) ) {
            update_post_meta(
                $order->get_id(),
                '_recibelo_tracking',
                sanitize_text_field( $body['tracking_id'] )
            );
        }
    }
}
