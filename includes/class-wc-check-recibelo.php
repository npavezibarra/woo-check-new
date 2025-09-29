<?php
/**
 * WooCheck Recíbelo Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WooCheck_Recibelo {

    /**
     * Send a WooCommerce order to Recíbelo.
     *
     * @param int|WC_Order $order Order instance or ID.
     *
     * @return WP_Error|array|WP_HTTP_Response
     */
    public static function send( $order ) {
        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }

        if ( ! $order instanceof WC_Order ) {
            return new WP_Error( 'woocheck_recibelo_invalid_order', __( 'Invalid order provided.', 'woo-check' ) );
        }

        $token = self::get_token();

        if ( '' === $token ) {
            return new WP_Error( 'woocheck_recibelo_missing_token', __( 'Recíbelo token is missing.', 'woo-check' ) );
        }

        if ( ! self::is_metropolitana_destination( $order ) ) {
            return new WP_Error( 'woocheck_recibelo_invalid_region', __( 'Order destination is outside Región Metropolitana.', 'woo-check' ) );
        }

        $payload = self::build_payload( $order );

        $url = sprintf( 'https://app.recibelo.cl/webhook/%s/woocommerce', rawurlencode( $token ) );

        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        ];

        error_log( sprintf( 'WooCheck Recibelo: Sending order %d', $order->get_id() ) );
        error_log( 'WooCheck Recibelo: Payload = ' . wp_json_encode( $payload ) );

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( sprintf( 'WooCheck Recibelo: Error sending order %d - %s', $order->get_id(), $response->get_error_message() ) );

            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        error_log( sprintf( 'WooCheck Recibelo: Sent order %d. Response: %s', $order->get_id(), $body ) );

        return $response;
    }

    /**
     * Get Recíbelo API token from settings.
     *
     * @return string
     */
    protected static function get_token() {
        $token = get_option( 'woocheck_recibelo_token', '' );

        if ( '' === $token ) {
            $token = get_option( 'woo_check_recibelo_token', '' );
        }

        return trim( (string) $token );
    }

    /**
     * Determine if the order destination is Región Metropolitana.
     *
     * @param WC_Order $order Order instance.
     *
     * @return bool
     */
    protected static function is_metropolitana_destination( WC_Order $order ) {
        $shipping_state = strtoupper( (string) $order->get_shipping_state() );
        $billing_state  = strtoupper( (string) $order->get_billing_state() );

        return 'CL-RM' === $shipping_state || 'CL-RM' === $billing_state;
    }

    /**
     * Build the payload expected by Recíbelo.
     *
     * @param WC_Order $order Order instance.
     *
     * @return array
     */
    protected static function build_payload( WC_Order $order ) {
        $billing  = $order->get_address( 'billing' );
        $shipping = self::prepare_shipping_address( $order );

        $line_items = [];

        foreach ( $order->get_items() as $item_id => $item ) {
            if ( ! $item instanceof WC_Order_Item_Product ) {
                continue;
            }

            $line_items[] = [
                'id'         => $item_id,
                'name'       => $item->get_name(),
                'product_id' => $item->get_product_id(),
                'quantity'   => $item->get_quantity(),
                'price'      => (string) $item->get_total(),
            ];
        }

        $shipping_lines = [];

        foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
            $shipping_lines[] = [
                'id'           => $item_id,
                'method_title' => $item->get_name(),
                'method_id'    => $item->get_method_id(),
                'total'        => (string) $item->get_total(),
            ];
        }

        return [
            'id'                   => $order->get_id(),
            'status'               => $order->get_status(),
            'currency'             => $order->get_currency(),
            'total'                => (string) $order->get_total(),
            'billing'              => [
                'first_name' => $billing['first_name'] ?? '',
                'last_name'  => $billing['last_name'] ?? '',
                'address_1'  => $billing['address_1'] ?? '',
                'address_2'  => $billing['address_2'] ?? '',
                'city'       => $billing['city'] ?? '',
                'state'      => $billing['state'] ?? '',
                'country'    => $billing['country'] ?? '',
                'email'      => $billing['email'] ?? '',
                'phone'      => $billing['phone'] ?? '',
            ],
            'shipping'             => $shipping,
            'line_items'           => $line_items,
            'shipping_lines'       => $shipping_lines,
            'payment_method'       => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
        ];
    }

    /**
     * Prepare the shipping address block.
     *
     * @param WC_Order $order Order instance.
     *
     * @return array
     */
    protected static function prepare_shipping_address( WC_Order $order ) {
        $shipping = $order->get_address( 'shipping' );

        if ( empty( $shipping['first_name'] ) ) {
            $shipping['first_name'] = $order->get_billing_first_name();
        }

        if ( empty( $shipping['last_name'] ) ) {
            $shipping['last_name'] = $order->get_billing_last_name();
        }

        if ( empty( $shipping['phone'] ) ) {
            $shipping['phone'] = $order->get_billing_phone();
        }

        if ( empty( $shipping['state'] ) ) {
            $shipping['state'] = $order->get_billing_state();
        }

        if ( empty( $shipping['country'] ) ) {
            $shipping['country'] = $order->get_billing_country();
        }

        if ( empty( $shipping['address_1'] ) ) {
            $shipping['address_1'] = $order->get_billing_address_1();
        }

        if ( empty( $shipping['city'] ) ) {
            $shipping['city'] = $order->get_billing_city();
        }

        return [
            'first_name' => $shipping['first_name'] ?? '',
            'last_name'  => $shipping['last_name'] ?? '',
            'address_1'  => $shipping['address_1'] ?? '',
            'address_2'  => $shipping['address_2'] ?? '',
            'city'       => $shipping['city'] ?? '',
            'state'      => $shipping['state'] ?? '',
            'country'    => $shipping['country'] ?? '',
            'phone'      => $shipping['phone'] ?? '',
        ];
    }
}
