<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Check_Shipit {
    private $endpoint = 'https://api.shipit.cl/v/shipments';
    private $email;
    private $token;

    public function __construct() {
        $this->email = get_option( 'wc_check_shipit_email' );
        if ( empty( $this->email ) ) {
            $this->email = get_option( 'woo_check_shipit_email' );
        }

        $this->token = get_option( 'wc_check_shipit_token' );
        if ( empty( $this->token ) ) {
            $this->token = get_option( 'woo_check_shipit_token' );
        }
    }

    public function create_shipment( $order ) {
        if ( ! $order instanceof WC_Order ) {
            return new WP_Error( 'wc_check_shipit_invalid_order', __( 'Invalid order instance provided.', 'woo-check' ) );
        }

        $data = $this->build_payload( $order );

        if ( empty( $data ) ) {
            return new WP_Error( 'wc_check_shipit_missing_commune', __( 'Unable to determine commune for Shipit shipment.', 'woo-check' ) );
        }

        if ( empty( $this->email ) || empty( $this->token ) ) {
            $error = new WP_Error( 'wc_check_shipit_missing_credentials', __( 'Shipit credentials are missing.', 'woo-check' ) );
            $this->log_api( $data, $error );
            return $error;
        }

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
            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( isset( $body['tracking_number'] ) ) {
                $order->update_meta_data( '_shipit_tracking', sanitize_text_field( $body['tracking_number'] ) );
                $order->save_meta_data();
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

            $sku = $product instanceof WC_Product ? $product->get_sku() : '';
            if ( '' === $sku ) {
                $sku = (string) $item->get_product_id();
            }

            $products[] = [
                'sku_id'       => $sku,
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

        $shipping_first_name = $order->get_shipping_first_name();
        $shipping_last_name  = $order->get_shipping_last_name();
        $full_name           = trim( $shipping_first_name . ' ' . $shipping_last_name );

        if ( '' === $full_name ) {
            $full_name = $order->get_formatted_billing_full_name();
        }

        $street  = $order->get_shipping_address_1();
        $street2 = $order->get_shipping_address_2();

        if ( '' === $street ) {
            $street  = $order->get_billing_address_1();
            $street2 = $order->get_billing_address_2();
        }

        $phone = $order->get_shipping_phone();
        if ( '' === $phone ) {
            $phone = $order->get_billing_phone();
        }

        $commune_name = $order->get_shipping_city();
        $commune_id   = $this->get_commune_id( $commune_name );

        if ( ! $commune_id ) {
            error_log( "WooCheck Shipit: Commune not found for '{$commune_name}' in order {$order->get_id()}." );

            return null;
        }

        $payload = [
            'shipment' => [
                'platform'  => 2,
                'reference' => $order->get_id() . 'N',
                'items'     => max( 1, $item_count ),
                'sizes'     => $dimensions,
                'courier'   => [
                    'id'               => 1,
                    'algorithm'        => 1,
                    'without_courier'  => false,
                ],
                'destiny'   => [
                    'name'         => $full_name,
                    'email'        => $order->get_billing_email(),
                    'phone'        => $phone,
                    'street'       => $street,
                    'number'       => $this->extract_street_number( $street ),
                    'complement'   => $street2,
                    'commune_id'   => $commune_id,
                    'commune_name' => $commune_name,
                    'country_id'   => 1,
                    'kind'         => 'home_delivery',
                ],
                'products'  => $products,
            ],
        ];

        return $payload;
    }

    private function log_api( $data, $response ) {
        error_log( 'Shipit Request: ' . wp_json_encode( $data ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'Shipit HTTP: 0 ' . $response->get_error_code() );
            error_log( 'Shipit Response: ' . $response->get_error_message() );
            return;
        }

        $response_code    = wp_remote_retrieve_response_code( $response );
        $response_message = wp_remote_retrieve_response_message( $response );
        $response_body    = wp_remote_retrieve_body( $response );

        error_log( sprintf( 'Shipit HTTP: %s %s', $response_code, $response_message ) );
        error_log( 'Shipit Response: ' . $response_body );
    }

    /**
     * Map WooCommerce shipping city/comuna to Shipit commune_id
     */
    private function get_commune_id( $commune_name ) {
        $file = plugin_dir_path( __FILE__ ) . 'communes.json';

        if ( ! file_exists( $file ) ) {
            error_log( "WooCheck Shipit: communes.json not found at {$file}" );
            return null;
        }

        $communes = json_decode( file_get_contents( $file ), true );

        foreach ( $communes as $commune ) {
            if ( strcasecmp( $commune['name'], $commune_name ) === 0 ) {
                return $commune['id'];
            }
        }

        return null;
    }

    private function extract_street_number( $street ) {
        if ( empty( $street ) ) {
            return '';
        }

        if ( preg_match( '/(\d+)/', $street, $matches ) ) {
            return $matches[1];
        }

        return '';
    }
}
