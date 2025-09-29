<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WooCheck_Recibelo {
    private $endpoint_pattern = 'https://app.recibelo.cl/webhook/%s/woocommerce';
    private $api_key;
    private $log_file;

    public function __construct() {
        $this->api_key  = trim( (string) get_option( 'woo_check_recibelo_token', '' ) );
        $this->log_file = dirname( __DIR__ ) . '/logs/woocheck-recibelo.log';
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

        $this->log(
            'WooCheck Recibelo: Preparing shipment',
            [
                'order_id'   => $order->get_id(),
                'order_data' => $this->normalize_for_log( $order->get_data() ),
            ]
        );

        if ( '' === $this->api_key ) {
            $error = new WP_Error( 'woocheck_recibelo_missing_credentials', __( 'Recíbelo credentials are missing.', 'woo-check' ) );
            $this->log( 'WooCheck Recibelo: Missing credentials', $error );
            return $error;
        }

        $payload = $this->build_payload( $order );

        if ( empty( $payload ) ) {
            $error = new WP_Error( 'woocheck_recibelo_invalid_payload', __( 'Unable to build Recíbelo payload.', 'woo-check' ) );
            $this->log( 'WooCheck Recibelo: Invalid payload', $error );
            return $error;
        }

        $endpoint = apply_filters(
            'woocheck_recibelo_endpoint',
            sprintf( $this->endpoint_pattern, rawurlencode( $this->api_key ) ),
            $order,
            $payload
        );

        $response = wp_remote_post(
            $endpoint,
            [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $this->api_key,
                ],
                'body'    => wp_json_encode( $payload ),
                'timeout' => 30,
            ]
        );

        $this->log_api( $payload, $response );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( is_array( $body ) ) {
            $tracking = $body['tracking_id'] ?? $body['tracking_number'] ?? null;

            if ( $tracking ) {
                $order->update_meta_data( '_recibelo_tracking', sanitize_text_field( $tracking ) );
                $order->save_meta_data();
            }
        }

        return $response;
    }

    private function build_payload( WC_Order $order ) {
        $location = wc_check_determine_commune_region_data( $order );

        if ( empty( $location['commune_id'] ) ) {
            $location['commune_id'] = 308;
        }

        if ( empty( $location['commune_name'] ) ) {
            $location['commune_name'] = 'SANTIAGO';
        }

        if ( empty( $location['region_id'] ) ) {
            $location['region_id'] = 7;
        }

        if ( empty( $location['region_name'] ) ) {
            $location['region_name'] = 'Metropolitana';
        }

        $full_name = $this->build_full_name( $order );
        $phone     = $this->normalize_phone( $order );
        $email     = $this->normalize_email( $order );

        list( $street, $number, $complement ) = WooCheck_Shipit_Validator::normalize_address(
            $order->get_billing_address_1(),
            $order->get_billing_address_2()
        );

        if ( 'Direccion' === $street ) {
            list( $street, $number, $complement ) = WooCheck_Shipit_Validator::normalize_address(
                $order->get_shipping_address_1(),
                $order->get_shipping_address_2()
            );
        }

        $items = [];

        foreach ( $order->get_items() as $item ) {
            if ( ! $item instanceof WC_Order_Item_Product ) {
                continue;
            }

            $items[] = [
                'name'     => $item->get_name(),
                'sku'      => $item->get_product() instanceof WC_Product ? $item->get_product()->get_sku() : '',
                'quantity' => max( 1, (int) $item->get_quantity() ),
                'price'    => (float) $item->get_total(),
            ];
        }

        if ( empty( $items ) ) {
            $items[] = [
                'name'     => __( 'Pedido WooCommerce', 'woo-check' ),
                'sku'      => '',
                'quantity' => 1,
                'price'    => (float) $order->get_total(),
            ];
        }

        return [
            'order_reference' => 'WC-' . $order->get_id(),
            'full_name'       => $full_name,
            'email'           => $email,
            'phone'           => $phone,
            'address'         => [
                'street'       => $street,
                'number'       => $number,
                'complement'   => $complement,
                'commune_id'   => $location['commune_id'],
                'commune_name' => $location['commune_name'],
                'region_id'    => $location['region_id'],
                'region_name'  => $location['region_name'],
            ],
            'products'        => $items,
        ];
    }

    private function build_full_name( WC_Order $order ) {
        $billing_first = $order->get_billing_first_name();
        $billing_last  = $order->get_billing_last_name();

        if ( '' === trim( (string) $billing_first ) ) {
            $billing_first = $order->get_shipping_first_name();
        }

        if ( '' === trim( (string) $billing_last ) ) {
            $billing_last = $order->get_shipping_last_name();
        }

        list( $first_name, $last_name ) = WooCheck_Shipit_Validator::normalize_name( $billing_first, $billing_last );

        $full_name = trim( $first_name . ' ' . $last_name );

        if ( function_exists( 'remove_accents' ) ) {
            $full_name = remove_accents( $full_name );
        }

        return $full_name;
    }

    private function normalize_phone( WC_Order $order ) {
        $phone = WooCheck_Shipit_Validator::normalize_phone( $order->get_billing_phone() );

        if ( '' === $phone ) {
            $phone = WooCheck_Shipit_Validator::normalize_phone( $order->get_shipping_phone() );
        }

        if ( '' === $phone ) {
            $phone = '+56900000000';
        }

        return $phone;
    }

    private function normalize_email( WC_Order $order ) {
        $email = $order->get_billing_email();

        if ( '' === trim( (string) $email ) ) {
            $user = $order->get_customer_id() ? get_userdata( $order->get_customer_id() ) : null;
            $email = $user ? $user->user_email : 'no-reply@yourdomain.cl';
        }

        if ( '' === trim( (string) $email ) ) {
            $email = 'no-reply@yourdomain.cl';
        }

        return $email;
    }

    private function log_api( $payload, $response ) {
        $this->log( 'WooCheck Recibelo: API request', $payload );

        if ( is_wp_error( $response ) ) {
            $this->log(
                'WooCheck Recibelo: API error',
                [
                    'code'    => $response->get_error_code(),
                    'message' => $response->get_error_message(),
                    'data'    => $this->normalize_for_log( $response->get_error_data() ),
                ]
            );
            return;
        }

        $response_code    = wp_remote_retrieve_response_code( $response );
        $response_message = wp_remote_retrieve_response_message( $response );
        $response_body    = wp_remote_retrieve_body( $response );
        $decoded_body     = json_decode( $response_body, true );

        $this->log(
            'WooCheck Recibelo: API response',
            [
                'status_code'    => $response_code,
                'status_message' => $response_message,
                'body'           => null !== $decoded_body ? $decoded_body : $response_body,
            ]
        );
    }

    private function log( $message, $context = null ) {
        $this->ensure_log_directory();

        $line = sprintf( '[%s] %s', gmdate( 'c' ), (string) $message );

        if ( null !== $context ) {
            $line .= ' ' . wp_json_encode(
                $this->normalize_for_log( $context ),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        $line .= PHP_EOL;

        error_log( $line, 3, $this->log_file );
    }

    private function ensure_log_directory() {
        $directory = dirname( $this->log_file );

        if ( ! is_dir( $directory ) ) {
            wp_mkdir_p( $directory );
        }
    }

    private function normalize_for_log( $data ) {
        if ( $data instanceof WP_Error ) {
            return [
                'code'    => $data->get_error_code(),
                'message' => $data->get_error_message(),
                'data'    => $this->normalize_for_log( $data->get_error_data() ),
            ];
        }

        if ( $data instanceof WC_Order ) {
            return $this->normalize_for_log( $data->get_data() );
        }

        if ( $data instanceof WC_Order_Item_Product ) {
            return $this->normalize_for_log( $data->get_data() );
        }

        if ( $data instanceof DateTimeInterface ) {
            return $data->format( DATE_ATOM );
        }

        if ( is_array( $data ) ) {
            foreach ( $data as $key => $value ) {
                $data[ $key ] = $this->normalize_for_log( $value );
            }

            return $data;
        }

        if ( is_object( $data ) ) {
            if ( method_exists( $data, 'get_data' ) ) {
                return $this->normalize_for_log( $data->get_data() );
            }

            return $this->normalize_for_log( get_object_vars( $data ) );
        }

        return $data;
    }
}

if ( ! class_exists( 'WC_Check_Recibelo' ) ) {
    class WC_Check_Recibelo extends WooCheck_Recibelo {}
}
