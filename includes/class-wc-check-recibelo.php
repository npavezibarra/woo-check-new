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

        $commune_name = $order->get_meta( '_shipping_comuna', true );
        error_log( sprintf( "WooCheck Recibelo [Order %d] Raw Woo comuna = '%s'", $order->get_id(), (string) $commune_name ) );

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

        $body        = wp_remote_retrieve_body( $response );
        $order_id    = $order->get_id();
        $status_code = (int) wp_remote_retrieve_response_code( $response );

        error_log( sprintf( 'WooCheck Recibelo: Sent order %d. Response: %s', $order_id, $body ) );

        if ( $status_code < 200 || $status_code >= 300 ) {
            $order->update_meta_data( '_recibelo_sync_failed', current_time( 'timestamp' ) );
            $order->save_meta_data();

            return $response;
        }

        $order->update_meta_data( '_recibelo_sync_status', 'synced' );
        $order->delete_meta_data( '_recibelo_sync_failed' );
        $order->save_meta_data();

        update_post_meta( $order_id, '_tracking_provider', 'recibelo' );

        $decoded_body = json_decode( $body, true );
        $tracking     = self::extract_tracking_from_response( $decoded_body );

        if ( ! empty( $tracking ) ) {
            $internal_id = sanitize_text_field( $tracking['number'] );

            update_post_meta( $order_id, '_tracking_number', $internal_id );
            update_post_meta( $order_id, '_tracking_provider', $tracking['provider'] );
            update_post_meta( $order_id, '_recibelo_internal_id', $internal_id );
            update_post_meta( $order_id, '_tracking_provider', 'recibelo' );
        } else {
            update_post_meta( $order_id, '_tracking_number', '' );
        }

        return $response;
    }

    /**
     * Attempt to extract the tracking data from the Recíbelo response payload.
     *
     * @param mixed $decoded_body JSON decoded response body.
     *
     * @return array{number:string,provider:string}|null
     */
    protected static function extract_tracking_from_response( $decoded_body ) {
        if ( empty( $decoded_body ) ) {
            return null;
        }

        if ( isset( $decoded_body['internal_id'] ) && '' !== trim( (string) $decoded_body['internal_id'] ) ) {
            return [
                'number'   => (string) $decoded_body['internal_id'],
                'provider' => 'recibelo',
            ];
        }

        $paths = [
            [ 'data', 'internal_id' ],
            [ 'order', 'internal_id' ],
            [ 'data', 'order', 'internal_id' ],
            [ 'internalId' ],
            [ 'data', 'internalId' ],
            [ 'order', 'internalId' ],
            [ 'data', 'order', 'internalId' ],
        ];

        foreach ( $paths as $path ) {
            $value = self::dig_value( $decoded_body, $path );

            if ( null !== $value && '' !== trim( (string) $value ) ) {
                return [
                    'number'   => (string) $value,
                    'provider' => 'recibelo',
                ];
            }
        }

        return null;
    }

    /**
     * Retrieve a nested array value using the provided path.
     *
     * @param mixed $data Source array.
     * @param array $path Keys describing the location of the desired value.
     *
     * @return mixed|null
     */
    protected static function dig_value( $data, array $path ) {
        if ( ! is_array( $data ) ) {
            return null;
        }

        $current = $data;

        foreach ( $path as $segment ) {
            if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
                return null;
            }

            $current = $current[ $segment ];
        }

        return $current;
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

        $commune_meta  = trim( (string) $order->get_meta( '_shipping_comuna', true ) );
        $shipping_city = trim( (string) ( $shipping['city'] ?? '' ) );
        $lookup_value  = '' !== $commune_meta ? $commune_meta : $shipping_city;

        $commune_id = WooCheck_Recibelo_CommuneMapper::get_commune_id( $lookup_value );

        if ( null === $commune_id && '' !== $shipping_city && $shipping_city !== $lookup_value ) {
            $lookup_value = $shipping_city;
            $commune_id   = WooCheck_Recibelo_CommuneMapper::get_commune_id( $lookup_value );
        }

        $final_commune = $lookup_value;

        if ( null !== $commune_id ) {
            $canonical_name = WooCheck_Recibelo_CommuneMapper::get_name( $commune_id );

            if ( null !== $canonical_name ) {
                $final_commune = $canonical_name;
            }
        } else {
            error_log( sprintf( 'WooCheck Recibelo: Commune not mapped for order %d - input: %s', $order->get_id(), $lookup_value ) );
        }

        $final_commune = (string) $final_commune;

        // ⚠️ Important:
        // Recíbelo expects comuna as string in "city".
        // Shipit expects comuna as numeric "commune_id".
        $shipping['city'] = $final_commune;
        $billing['city']  = $final_commune;

        error_log( sprintf( 'WooCheck Recibelo: Final commune for order #%d = %s', $order->get_id(), $final_commune ) );

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

if ( ! class_exists( 'WC_Check_Recibelo' ) ) {

    /**
     * Lightweight helper utilities for Recíbelo tracking lookups.
     */
    class WC_Check_Recibelo {

        /**
         * Query Recíbelo API for tracking status.
         *
         * @param int|string $internal_id   Recíbelo shipment identifier.
         * @param string     $customer_name Billing customer full name.
         *
         * @return string Friendly tracking status or default fallback message.
         */
        public static function get_tracking_status( $internal_id, $customer_name ) {
            $default_message = __( 'Estamos consultando el estado de este envío...', 'woo-check' );

            $internal_id   = trim( (string) $internal_id );
            $customer_name = trim( (string) $customer_name );

            if ( '' === $internal_id || '' === $customer_name ) {
                return $default_message;
            }

            $url = add_query_arg(
                [ 'internal_ids[]' => $internal_id ],
                'https://app.recibelo.cl/api/check-package-internal-id'
            );

            $response = wp_remote_get(
                $url,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                    'timeout' => 15,
                ]
            );

            if ( is_wp_error( $response ) ) {
                return $default_message;
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( ! is_array( $data ) ) {
                return $default_message;
            }

            $packages = self::extract_packages( $data );

            if ( empty( $packages ) ) {
                return $default_message;
            }

            $normalized_target = self::normalize_name( $customer_name );

            foreach ( $packages as $package ) {
                if ( ! is_array( $package ) ) {
                    continue;
                }

                $package_name = '';

                foreach ( [ 'contact_full_name', 'contactFullName', 'customer_name', 'customerName' ] as $name_key ) {
                    if ( isset( $package[ $name_key ] ) && '' !== trim( (string) $package[ $name_key ] ) ) {
                        $package_name = (string) $package[ $name_key ];
                        break;
                    }
                }

                if ( '' === $package_name ) {
                    continue;
                }

                if ( $normalized_target !== self::normalize_name( $package_name ) ) {
                    continue;
                }

                $status = '';

                foreach ( [ 'current_status', 'currentStatus' ] as $status_key ) {
                    if ( isset( $package[ $status_key ] ) && '' !== trim( (string) $package[ $status_key ] ) ) {
                        $status = (string) $package[ $status_key ];
                        break;
                    }
                }

                if ( '' === $status ) {
                    return $default_message;
                }

                if ( function_exists( 'woocheck_recibelo_status_label' ) ) {
                    $label = woocheck_recibelo_status_label( $status );
                } else {
                    $label = self::map_status( $status );
                }

                return '' !== $label ? $label : $status;
            }

            return $default_message;
        }

        public static function ajax_get_tracking_status() {
            if ( ! isset( $_POST['order_id'] ) ) {
                wp_send_json_error( [ 'message' => __( 'Missing order_id', 'woo-check' ) ] );
            }

            $order_id = absint( wp_unslash( $_POST['order_id'] ) );

            if ( ! $order_id ) {
                wp_send_json_error( [ 'message' => __( 'Invalid order_id', 'woo-check' ) ] );
            }

            $order = wc_get_order( $order_id );

            if ( ! $order ) {
                wp_send_json_error( [ 'message' => __( 'Order not found', 'woo-check' ) ] );
            }

            $internal_id   = $order->get_meta( '_recibelo_internal_id', true );
            $customer_name = $order->get_formatted_billing_full_name();

            $status = self::get_tracking_status( $internal_id, $customer_name );

            wp_send_json_success( [
                'status'          => $status,
                'message'         => $status,
                'courier'         => 'Recíbelo',
                'tracking_number' => $internal_id,
                'tracking_url'    => '',
            ] );
        }

        /**
         * Extract Recíbelo packages from an API payload.
         *
         * @param array $payload API response payload.
         *
         * @return array<int, array<string, mixed>>
         */
        protected static function extract_packages( array $payload ) {
            if ( function_exists( 'woocheck_recibelo_extract_packages' ) ) {
                return woocheck_recibelo_extract_packages( $payload );
            }

            if ( isset( $payload[0] ) ) {
                return array_values( $payload );
            }

            $paths = [
                [ 'data', 'packages' ],
                [ 'data', 'items' ],
                [ 'data', 'results' ],
                [ 'data' ],
                [ 'packages' ],
                [ 'results' ],
                [ 'items' ],
            ];

            foreach ( $paths as $path ) {
                $value = self::dig_value( $payload, $path );

                if ( is_array( $value ) ) {
                    return array_values( $value );
                }
            }

            return [];
        }

        /**
         * Retrieve a nested value using the provided key path.
         *
         * @param array $data Source data.
         * @param array $path Keys leading to the desired value.
         *
         * @return mixed|null
         */
        protected static function dig_value( array $data, array $path ) {
            $current = $data;

            foreach ( $path as $segment ) {
                if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
                    return null;
                }

                $current = $current[ $segment ];
            }

            return $current;
        }

        /**
         * Normalize a full name to improve comparisons.
         *
         * @param string $value Raw name value.
         *
         * @return string
         */
        protected static function normalize_name( $value ) {
            $value = strtolower( trim( (string) $value ) );

            if ( '' === $value ) {
                return '';
            }

            if ( function_exists( 'remove_accents' ) ) {
                $value = remove_accents( $value );
            }

            $normalized = preg_replace( '/\s+/u', ' ', $value );

            return is_string( $normalized ) ? $normalized : '';
        }

        /**
         * Map a Recíbelo raw status to a friendly label.
         *
         * @param string $status Raw status.
         *
         * @return string
         */
        protected static function map_status( $status ) {
            $normalized = strtolower( trim( (string) $status ) );

            if ( '' === $normalized ) {
                return '';
            }

            $map = [
                'creado'           => __( 'Preparando envío', 'woo-check' ),
                'etiqueta impresa' => __( 'Preparando envío', 'woo-check' ),
                'preparado'        => __( 'Preparando envío', 'woo-check' ),
                'en deposito'      => __( 'En tránsito', 'woo-check' ),
                'retirado'         => __( 'En tránsito', 'woo-check' ),
                'en ruta'          => __( 'En tránsito', 'woo-check' ),
                'completado'       => __( 'Finalizado', 'woo-check' ),
                'no aceptado'      => __( 'Error/Rechazo', 'woo-check' ),
            ];

            return $map[ $normalized ] ?? $status;
        }
    }
}

add_action( 'wp_ajax_woocheck_recibelo_status', [ 'WC_Check_Recibelo', 'ajax_get_tracking_status' ] );
add_action( 'wp_ajax_nopriv_woocheck_recibelo_status', [ 'WC_Check_Recibelo', 'ajax_get_tracking_status' ] );
