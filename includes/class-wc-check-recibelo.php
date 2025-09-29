<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WooCheck_Recibelo {
    private $endpoint_pattern = 'https://app.recibelo.cl/webhook/%s/woocommerce';
    private $token;

    public function __construct() {
        $this->token = trim( (string) get_option( 'woo_check_recibelo_token', '' ) );
    }

    /**
     * Send the WooCommerce order to Recíbelo.
     *
     * @param WC_Order $order Order to send.
     *
     * @return WP_Error|array|WP_HTTP_Response|false
     */
    public static function send( $order ) {
        $client = new self();

        return $client->dispatch( $order );
    }

    private function dispatch( $order ) {
        if ( ! $order instanceof WC_Order ) {
            return new WP_Error( 'woocheck_recibelo_invalid_order', __( 'Invalid order instance provided.', 'woo-check' ) );
        }

        if ( '' === $this->token ) {
            return new WP_Error( 'woocheck_recibelo_missing_token', __( 'Recíbelo token is missing.', 'woo-check' ) );
        }

        $payload = $this->build_payload( $order );

        $this->log_message( sprintf( 'WooCheck Recibelo: Sending order %d', $order->get_id() ) );
        $this->log_message( 'WooCheck Recibelo: Payload = ' . wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

        $endpoint = apply_filters(
            'woocheck_recibelo_endpoint',
            sprintf( $this->endpoint_pattern, rawurlencode( $this->token ) ),
            $order,
            $payload
        );

        $response = wp_remote_post(
            $endpoint,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body'    => wp_json_encode( $payload ),
                'timeout' => 30,
            ]
        );

        if ( is_wp_error( $response ) ) {
            $error_context = [
                'code'    => $response->get_error_code(),
                'message' => $response->get_error_message(),
                'data'    => $response->get_error_data(),
            ];

            $this->log_message(
                'WooCheck Recibelo: Response = ' . wp_json_encode( $error_context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
            );
            $this->mark_sync_failed( $order );

            return $response;
        }

        $body        = wp_remote_retrieve_body( $response );
        $status_code = (int) wp_remote_retrieve_response_code( $response );
        $decoded     = json_decode( $body, true );

        $this->log_message(
            'WooCheck Recibelo: Response = ' . wp_json_encode(
                [
                    'status_code' => $status_code,
                    'body'        => null !== $decoded ? $decoded : $body,
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        if ( $status_code >= 400 ) {
            $this->mark_sync_failed( $order );

            return new WP_Error(
                'woocheck_recibelo_http_error',
                __( 'Recíbelo request returned an error response.', 'woo-check' ),
                [
                    'status_code' => $status_code,
                    'body'        => $body,
                ]
            );
        }

        $order->update_meta_data( '_recibelo_sync_status', 'synced' );
        $order->delete_meta_data( '_recibelo_sync_failed' );
        $order->save_meta_data();

        return $response;
    }

    private function build_payload( WC_Order $order ) {
        return [
            'id'                    => (int) $order->get_id(),
            'status'                => $order->get_status(),
            'currency'              => $order->get_currency(),
            'total'                 => $this->format_amount( $order->get_total() ),
            'billing'               => $this->format_address( $order, 'billing' ),
            'shipping'              => $this->format_address( $order, 'shipping' ),
            'line_items'            => $this->build_line_items( $order ),
            'shipping_lines'        => $this->build_shipping_lines( $order ),
            'payment_method'        => $order->get_payment_method(),
            'payment_method_title'  => $order->get_payment_method_title(),
        ];
    }

    private function build_line_items( WC_Order $order ) {
        $items = [];

        foreach ( $order->get_items() as $item ) {
            if ( ! $item instanceof WC_Order_Item_Product ) {
                continue;
            }

            $quantity   = max( 1, (int) $item->get_quantity() );
            $line_total = (float) $item->get_total() + (float) $item->get_total_tax();
            $unit_price = $quantity > 0 ? $line_total / $quantity : $line_total;

            $items[] = [
                'id'         => $item->get_id(),
                'name'       => $item->get_name(),
                'product_id' => $item->get_product_id(),
                'quantity'   => $quantity,
                'price'      => $this->format_amount( $unit_price ),
                'total'      => $this->format_amount( $line_total ),
            ];
        }

        return $items;
    }

    private function build_shipping_lines( WC_Order $order ) {
        $lines = [];

        foreach ( $order->get_shipping_methods() as $shipping_item ) {
            $line_total = (float) $shipping_item->get_total() + (float) $shipping_item->get_total_tax();

            $lines[] = [
                'id'           => $shipping_item->get_id(),
                'method_title' => $shipping_item->get_name(),
                'method_id'    => $shipping_item->get_method_id(),
                'total'        => $this->format_amount( $line_total ),
            ];
        }

        return $lines;
    }

    private function format_address( WC_Order $order, $type ) {
        $first_name = $order->{ "get_{$type}_first_name" }();
        $last_name  = $order->{ "get_{$type}_last_name" }();
        $address_1  = $order->{ "get_{$type}_address_1" }();
        $address_2  = $order->{ "get_{$type}_address_2" }();
        $city       = $order->{ "get_{$type}_city" }();
        $state      = $order->{ "get_{$type}_state" }();
        $country    = $order->{ "get_{$type}_country" }();
        $phone      = 'billing' === $type ? $order->get_billing_phone() : $order->get_shipping_phone();

        if ( 'shipping' === $type && '' === trim( (string) $phone ) ) {
            $phone = $order->get_billing_phone();
        }

        if ( '' === trim( (string) $state ) ) {
            $state = 'shipping' === $type ? $order->get_billing_state() : $state;
        }

        $address = [
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'address_1'  => $address_1,
            'address_2'  => $address_2,
            'city'       => $city,
            'state'      => $state,
            'country'    => $country,
            'phone'      => $phone,
        ];

        if ( 'billing' === $type ) {
            $address['email'] = $order->get_billing_email();
        }

        if ( 'shipping' === $type && '' === trim( (string) $address['phone'] ) ) {
            $address['phone'] = $order->get_billing_phone();
        }

        if ( 'shipping' === $type && '' === trim( (string) $address['country'] ) ) {
            $address['country'] = $order->get_billing_country();
        }

        if ( 'shipping' === $type && '' === trim( (string) $address['state'] ) ) {
            $address['state'] = $order->get_billing_state();
        }

        return $address;
    }

    private function format_amount( $amount ) {
        $decimals = wc_get_price_decimals();

        return wc_format_decimal( $amount, $decimals > 0 ? $decimals : 0 );
    }

    private function log_message( $message ) {
        error_log( $message );
    }

    private function mark_sync_failed( WC_Order $order ) {
        $message = sprintf( __( 'WooCheck: Order #%d failed to sync with Recíbelo. See logs.', 'woo-check' ), $order->get_id() );

        if ( 'failed' !== $order->get_meta( '_recibelo_sync_status' ) ) {
            $order->add_order_note( $message );
        }

        $order->update_meta_data( '_recibelo_sync_status', 'failed' );
        $order->update_meta_data( '_recibelo_sync_failed', current_time( 'mysql' ) );
        $order->save_meta_data();
    }
}

if ( ! class_exists( 'WC_Check_Recibelo' ) ) {
    class WC_Check_Recibelo extends WooCheck_Recibelo {}
}
