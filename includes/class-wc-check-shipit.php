<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Check_Shipit {
    private $endpoint = 'https://api.shipit.cl/v/shipments';
    private $email;
    private $token;
    private $log_file;

    public function __construct() {
        $this->email = get_option( 'wc_check_shipit_email' );
        if ( empty( $this->email ) ) {
            $this->email = get_option( 'woo_check_shipit_email' );
        }

        $this->token = get_option( 'wc_check_shipit_token' );
        if ( empty( $this->token ) ) {
            $this->token = get_option( 'woo_check_shipit_token' );
        }

        $this->log_file = dirname( __DIR__ ) . '/logs/woocheck-shipit.log';
    }

    public function create_shipment( $order ) {
        if ( ! $order instanceof WC_Order ) {
            return new WP_Error( 'wc_check_shipit_invalid_order', __( 'Invalid order instance provided.', 'woo-check' ) );
        }

        $this->log(
            'WooCheck Shipit: Preparing shipment',
            [
                'order_id'   => $order->get_id(),
                'order_data' => $this->normalize_for_log( $order->get_data() ),
            ]
        );

        $data = $this->build_payload( $order );

        if ( empty( $data ) ) {
            $error = new WP_Error( 'wc_check_shipit_missing_commune', __( 'Unable to determine commune for Shipit shipment.', 'woo-check' ) );
            $this->log( 'WooCheck Shipit: Missing commune data', $error );
            return $error;
        }

        if ( empty( $this->email ) || empty( $this->token ) ) {
            $error = new WP_Error( 'wc_check_shipit_missing_credentials', __( 'Shipit credentials are missing.', 'woo-check' ) );
            $this->log_api( $data, $error );
            return $error;
        }

        $this->log( 'WooCheck Shipit: Payload', $data );

        $response = wp_remote_post(
            $this->endpoint,
            [
                'headers' => [
                    'Content-Type'          => 'application/json',
                    'Accept'                => 'application/vnd.shipit.v4',
                    'X-Shipit-Email'        => $this->email,
                    'X-Shipit-Access-Token' => $this->token,
                ],
                'body'    => wp_json_encode( $data ),
                'timeout' => 30,
            ]
        );

        $this->log_api( $data, $response );

        if ( ! is_wp_error( $response ) ) {
            $body        = json_decode( wp_remote_retrieve_body( $response ), true );
            $order_id    = $order->get_id();
            $tracking_no = '';

            if ( isset( $body['tracking_number'] ) ) {
                $order->update_meta_data( '_shipit_tracking', sanitize_text_field( $body['tracking_number'] ) );
                $order->save_meta_data();
            }

            $status_code = (int) wp_remote_retrieve_response_code( $response );

            if ( $status_code >= 200 && $status_code < 300 ) {
                $tracking_no = $order_id . 'N';
            }

            if ( ! empty( $tracking_no ) ) {
                update_post_meta( $order_id, '_tracking_number', sanitize_text_field( $tracking_no ) );
                update_post_meta( $order_id, '_tracking_provider', 'shipit' );
            }
        }

        return $response;
    }

    private function build_payload( $order ) {
        $item_count = 0;
        $products   = [];

        $default_warehouse = (int) apply_filters( 'wc_check_shipit_default_warehouse_id', 1, $order );

        $dimensions = [
            'width'  => 0,
            'height' => 0,
            'length' => 0,
            'weight' => 0,
        ];

        foreach ( $order->get_items() as $item ) {
            if ( ! $item instanceof WC_Order_Item_Product ) {
                continue;
            }

            $quantity = max( 1, (int) $item->get_quantity() );
            $item_count += $quantity;

            $product = $item->get_product();

            if ( $product instanceof WC_Product ) {
                $product_weight = (float) $product->get_weight();
                $product_length = (float) $product->get_length();
                $product_width  = (float) $product->get_width();
                $product_height = (float) $product->get_height();

                if ( $product_weight > 0 ) {
                    $dimensions['weight'] += $product_weight * $quantity;
                }

                if ( $product_length > 0 ) {
                    $dimensions['length'] = max( $dimensions['length'], $product_length );
                }

                if ( $product_width > 0 ) {
                    $dimensions['width'] = max( $dimensions['width'], $product_width );
                }

                if ( $product_height > 0 ) {
                    $dimensions['height'] = max( $dimensions['height'], $product_height );
                }
            }

            $products[] = [
                'sku_id'       => $item->get_product_id(),
                'amount'       => $quantity,
                'warehouse_id' => (int) apply_filters( 'wc_check_shipit_product_warehouse_id', $default_warehouse, $item, $order ),
            ];
        }

        $defaults = [
            'width'  => 10,
            'height' => 10,
            'length' => 10,
            'weight' => 1,
        ];

        foreach ( $dimensions as $dimension => $value ) {
            if ( $value <= 0 ) {
                $dimensions[ $dimension ] = $defaults[ $dimension ];
            }
        }

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
            $first_name = remove_accents( $first_name );
            $last_name  = remove_accents( $last_name );
            $full_name  = remove_accents( $full_name );
        }

        $phone = WooCheck_Shipit_Validator::normalize_phone( $order->get_billing_phone() );
        if ( '' === trim( (string) $phone ) ) {
            $phone = WooCheck_Shipit_Validator::normalize_phone( $order->get_shipping_phone() );
        }
        if ( '' === trim( (string) $phone ) ) {
            $phone = '+56900000000';
        }

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

        $commune_raw = $order->get_meta( '_shipping_comuna', true );
        if ( '' === trim( (string) $commune_raw ) ) {
            $commune_raw = $order->get_meta( '_billing_comuna', true );
        }
        if ( '' === trim( (string) $commune_raw ) ) {
            $commune_raw = $order->get_shipping_city();
        }
        if ( '' === trim( (string) $commune_raw ) ) {
            $commune_raw = $order->get_billing_city();
        }

        $commune_name = WooCheck_Shipit_Validator::normalize_commune( $commune_raw );

        $this->log(
            'WooCheck Shipit: Using commune',
            [
                'order_id'     => $order->get_id(),
                'commune_name' => $commune_name,
            ]
        );

        $commune_id = $this->map_commune_to_id( $commune_name );

        if ( ! $commune_id ) {
            $this->log(
                'WooCheck Shipit: Commune not found, defaulting to Santiago',
                [
                    'order_id'     => $order->get_id(),
                    'commune_name' => $commune_name,
                ]
            );
            $commune_id   = 308;
            $commune_name = 'SANTIAGO';
        }

        $email = $order->get_billing_email();
        if ( '' === trim( (string) $email ) ) {
            $email = 'no-reply@yourdomain.cl';
        }

        $payload = [
            'shipment' => [
                'platform'  => 2,
                'reference' => $order->get_id() . 'N',
                'items'     => max( 1, $item_count ),
                'sizes'     => $dimensions,
                'courier'   => [
                    'id'              => 1,
                    'algorithm'       => 1,
                    'without_courier' => false,
                ],
                'destiny'   => [
                    'street'                   => $street,
                    'number'                   => $number,
                    'complement'               => $complement,
                    'commune_id'               => $commune_id,
                    'commune_name'             => $commune_name,
                    'full_name'                => $full_name, // REQUIRED
                    'email'                    => $email,
                    'phone'                    => $phone,
                    'kind'                     => 'home_delivery',
                    'courier_destiny_id'       => null,
                    'courier_branch_office_id' => null,
                ],
                'products'  => $products,
            ],
        ];

        if ( apply_filters( 'wc_check_shipit_include_insurance', false, $order ) ) {
            $payload['shipment']['insurance'] = [
                'ticket_amount' => (int) $order->get_total(),
                'ticket_number' => (int) $order->get_id(),
                'detail'        => 'Pedido WooCommerce',
                'extra'         => false,
            ];
        }

        return $payload;
    }

    private function log_api( $data, $response ) {
        $this->log( 'WooCheck Shipit: API request', $data );

        if ( is_wp_error( $response ) ) {
            $this->log(
                'WooCheck Shipit: API error',
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
            'WooCheck Shipit: API response',
            [
                'status_code'    => $response_code,
                'status_message' => $response_message,
                'body'           => null !== $decoded_body ? $decoded_body : $response_body,
            ]
        );
    }

    /**
     * Map a normalized commune name to Shipit communes.json.
     */
    private function map_commune_to_id( $commune_name ) {
        $file = __DIR__ . '/communes.json';

        if ( ! file_exists( $file ) ) {
            $this->log(
                'WooCheck Shipit: communes.json not found',
                [ 'path' => $file ]
            );
            return null;
        }

        $communes = json_decode( file_get_contents( $file ), true );

        if ( ! is_array( $communes ) ) {
            $this->log( 'WooCheck Shipit: Unable to decode communes.json.' );
            return null;
        }

        $normalized_target = WooCheck_Shipit_Validator::normalize_commune( $commune_name );

        foreach ( $communes as $commune ) {
            if ( ! isset( $commune['name'], $commune['id'] ) ) {
                continue;
            }

            $normalized_json = WooCheck_Shipit_Validator::normalize_commune( $commune['name'] );

            if ( $normalized_target === $normalized_json ) {
                return $commune['id'];
            }
        }

        $similar = [];

        foreach ( $communes as $commune ) {
            if ( ! isset( $commune['name'] ) ) {
                continue;
            }

            $normalized_json = WooCheck_Shipit_Validator::normalize_commune( $commune['name'] );
            $distance        = levenshtein( $normalized_target, $normalized_json );

            if ( $distance < 3 ) {
                $similar[] = $commune['name'];
            }
        }

        $suggestion = empty( $similar ) ? 'none' : implode( ', ', $similar );
        $this->log(
            'WooCheck Shipit: Commune not found',
            [
                'requested_commune' => $commune_name,
                'suggestion'        => $suggestion,
            ]
        );

        return null;
    }

    /**
     * Retrieve a human friendly tracking status for a Shipit order.
     *
     * @param int $order_id WooCommerce order identifier.
     *
     * @return array{
     *     status:string,
     *     eta:string,
     *     message:string,
     * }
     */
    public static function get_tracking_status_by_order( $order_id ) {
        $default_message = __( 'Estamos consultando el estado de este envío...', 'woo-check' );
        $result          = [
            'status'  => '',
            'eta'     => '',
            'message' => $default_message,
        ];

        $order_id = absint( $order_id );

        if ( ! $order_id ) {
            return $result;
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return $result;
        }

        $tracking_reference = trim( (string) $order->get_meta( '_shipit_tracking', true ) );

        if ( '' === $tracking_reference ) {
            $tracking_reference = trim( (string) $order->get_meta( '_tracking_number', true ) );
        }

        if ( '' === $tracking_reference ) {
            return $result;
        }

        $client = new self();

        if ( empty( $client->email ) || empty( $client->token ) ) {
            return $result;
        }

        $endpoint = trailingslashit( $client->endpoint ) . rawurlencode( $tracking_reference );

        $response = wp_remote_get(
            $endpoint,
            [
                'headers' => [
                    'Accept'                => 'application/vnd.shipit.v4',
                    'Content-Type'          => 'application/json',
                    'X-Shipit-Email'        => $client->email,
                    'X-Shipit-Access-Token' => $client->token,
                ],
                'timeout' => 20,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $result;
        }

        $status_code = (int) wp_remote_retrieve_response_code( $response );

        if ( $status_code < 200 || $status_code >= 300 ) {
            return $result;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            return $result;
        }

        $status_candidates = [
            [ 'shipment', 'status_display' ],
            [ 'shipment', 'status' ],
            [ 'shipment', 'tracking_status' ],
            [ 'shipment', 'state' ],
            [ 'data', 'status_display' ],
            [ 'data', 'status' ],
            [ 'tracking', 'status' ],
            [ 'tracking', 'current_status', 'name' ],
            [ 'tracking', 'current_status', 'status' ],
            [ 'status' ],
        ];

        $eta_candidates = [
            [ 'shipment', 'estimated_delivery_at' ],
            [ 'shipment', 'estimated_delivery_date' ],
            [ 'data', 'estimated_delivery_at' ],
            [ 'data', 'estimated_delivery_date' ],
            [ 'tracking', 'estimated_delivery_at' ],
            [ 'tracking', 'estimated_delivery_date' ],
            [ 'tracking', 'estimated_delivery' ],
        ];

        $status = '';

        foreach ( $status_candidates as $path ) {
            $value = self::extract_value( $data, $path );

            if ( is_string( $value ) && '' !== trim( $value ) ) {
                $status = self::format_status_label( $value );
                break;
            }
        }

        if ( '' === $status && isset( $data['tracking']['events'] ) && is_array( $data['tracking']['events'] ) ) {
            $events = array_filter(
                $data['tracking']['events'],
                static function ( $event ) {
                    return is_array( $event );
                }
            );

            if ( ! empty( $events ) ) {
                $last_event = end( $events );

                foreach ( [ 'status', 'description', 'name' ] as $event_key ) {
                    if ( isset( $last_event[ $event_key ] ) && '' !== trim( (string) $last_event[ $event_key ] ) ) {
                        $status = self::format_status_label( $last_event[ $event_key ] );
                        break;
                    }
                }
            }
        }

        $eta = '';

        foreach ( $eta_candidates as $path ) {
            $value = self::extract_value( $data, $path );

            if ( is_string( $value ) && '' !== trim( $value ) ) {
                $eta = self::format_eta( $value );
                break;
            }
        }

        if ( '' !== $status ) {
            $result['status'] = $status;
            $result['message'] = $status;

            if ( '' !== $eta ) {
                $result['eta']     = $eta;
                $result['message'] = sprintf(
                    /* translators: 1: Tracking status label. 2: Estimated delivery date. */
                    __( '%1$s (Entrega estimada: %2$s)', 'woo-check' ),
                    $status,
                    $eta
                );
            }
        }

        return $result;
    }

    /**
     * AJAX handler to retrieve Shipit tracking status.
     */
    public static function ajax_get_tracking_status() {
        if ( ! isset( $_POST['order_id'] ) ) {
            wp_send_json_error( [ 'message' => 'Missing order_id' ] );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;

        $status_data = self::get_tracking_status_by_order( $order_id );

        wp_send_json_success( $status_data );
    }

    /**
     * Extract a nested value from an array using a path definition.
     *
     * @param array $data Source data.
     * @param array $path Keys to traverse.
     *
     * @return mixed|null
     */
    private static function extract_value( $data, array $path ) {
        if ( ! is_array( $data ) ) {
            return null;
        }

        $cursor = $data;

        foreach ( $path as $key ) {
            if ( ! is_array( $cursor ) || ! array_key_exists( $key, $cursor ) ) {
                return null;
            }

            $cursor = $cursor[ $key ];
        }

        return $cursor;
    }

    /**
     * Normalize a raw status label provided by Shipit.
     *
     * @param string $status Raw status string.
     */
    private static function format_status_label( $status ) {
        $status = wc_clean( $status );

        if ( '' === $status ) {
            return '';
        }

        $status = str_replace( '_', ' ', $status );
        $status = preg_replace( '/\s+/', ' ', $status );
        $status = trim( (string) $status );

        if ( '' === $status ) {
            return '';
        }

        $normalized = strtolower( $status );

        $map = [
            'in transit'       => __( 'En tránsito', 'woo-check' ),
            'en transito'      => __( 'En tránsito', 'woo-check' ),
            'out for delivery' => __( 'En reparto', 'woo-check' ),
            'delivered'        => __( 'Entregado', 'woo-check' ),
            'pending'          => __( 'Pendiente', 'woo-check' ),
            'pending pickup'   => __( 'Pendiente de retiro', 'woo-check' ),
        ];

        if ( isset( $map[ $normalized ] ) ) {
            return $map[ $normalized ];
        }

        if ( function_exists( 'mb_convert_case' ) ) {
            return mb_convert_case( $status, MB_CASE_TITLE, 'UTF-8' );
        }

        return ucwords( strtolower( $status ) );
    }

    /**
     * Format the estimated delivery string returned by Shipit.
     *
     * @param string $eta Raw ETA string.
     */
    private static function format_eta( $eta ) {
        $eta = wc_clean( $eta );

        if ( '' === $eta ) {
            return '';
        }

        $timestamp = strtotime( $eta );

        if ( false === $timestamp ) {
            return $eta;
        }

        return date_i18n( 'd-m-Y', $timestamp );
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

if ( ! class_exists( 'WooCheck_Shipit' ) ) {
    class WooCheck_Shipit {
        public static function send( $order ) {
            $client = new WC_Check_Shipit();

            return $client->create_shipment( $order );
        }
    }
}
