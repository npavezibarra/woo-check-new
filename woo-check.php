<?php
/*
Plugin Name: WooCheck
Description: A plugin to customize the WooCommerce checkout page with an autocomplete field for Comuna.
Version: 1.0
Author: Your Name
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include email customizations
require_once plugin_dir_path( __FILE__ ) . 'includes/email-customizations.php';
// Include the plugin's functions.php file
require_once plugin_dir_path(__FILE__) . 'functions.php';
// Load WooCheck logistics dependencies
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-check-admin.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-check-recibelo-communes.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-check-recibelo.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-woo-check-courier-router.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-woo-check-shipit-validator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-check-shipit.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-check-shipit-webhook.php';

add_action( 'woocommerce_order_status_processing', 'wc_check_handle_processing_order', 10, 1 );

add_action( 'wp_ajax_woocheck_shipit_status', [ 'WC_Check_Shipit', 'ajax_get_tracking_status' ] );
add_action( 'wp_ajax_nopriv_woocheck_shipit_status', [ 'WC_Check_Shipit', 'ajax_get_tracking_status' ] );

function wc_check_get_commune_catalog() {
    static $catalog = null;

    if ( null !== $catalog ) {
        return $catalog;
    }

    $catalog = [
        'by_id'   => [],
        'by_name' => [],
    ];

    $file = plugin_dir_path( __FILE__ ) . 'includes/communes.json';

    if ( ! file_exists( $file ) ) {
        return $catalog;
    }

    $data = json_decode( file_get_contents( $file ), true );

    if ( ! is_array( $data ) ) {
        return $catalog;
    }

    foreach ( $data as $entry ) {
        if ( empty( $entry['id'] ) ) {
            continue;
        }

        $id = (int) $entry['id'];
        $catalog['by_id'][ $id ] = $entry;

        if ( ! empty( $entry['name'] ) ) {
            $normalized = WooCheck_Shipit_Validator::normalize_commune( $entry['name'] );
            $catalog['by_name'][ $normalized ] = $entry;
        }
    }

    return $catalog;
}

function wc_check_region_id_from_state_code( $state_code ) {
    $state_code = strtoupper( trim( (string) $state_code ) );

    if ( '' === $state_code ) {
        return null;
    }

    $direct_map = [
        'RM'   => 7,
        'CL-RM' => 7,
    ];

    if ( isset( $direct_map[ $state_code ] ) ) {
        return $direct_map[ $state_code ];
    }

    static $region_lookup = null;

    if ( null === $region_lookup ) {
        $region_lookup = [];
        $catalog       = wc_check_get_commune_catalog();

        foreach ( $catalog['by_id'] as $entry ) {
            if ( empty( $entry['region_name'] ) || empty( $entry['region_id'] ) ) {
                continue;
            }

            $normalized = WooCheck_Shipit_Validator::normalize_commune( $entry['region_name'] );
            $region_lookup[ $normalized ] = (int) $entry['region_id'];
        }
    }

    $normalized_state = WooCheck_Shipit_Validator::normalize_commune( $state_code );

    return $region_lookup[ $normalized_state ] ?? null;
}

function wc_check_determine_commune_region_data( WC_Order $order ) {
    $catalog = wc_check_get_commune_catalog();

    $commune_id_keys = [
        '_shipping_commune_id',
        '_billing_commune_id',
        'shipping_commune_id',
        'billing_commune_id',
        '_shipping_comuna_id',
        '_billing_comuna_id',
    ];

    $commune_id = null;

    foreach ( $commune_id_keys as $key ) {
        $value = $order->get_meta( $key, true );

        if ( '' !== $value ) {
            $commune_id = (int) $value;

            if ( $commune_id > 0 ) {
                break;
            }
        }
    }

    $commune_name_candidates = [
        $order->get_meta( '_shipping_comuna', true ),
        $order->get_meta( 'shipping_comuna', true ),
        $order->get_meta( '_billing_comuna', true ),
        $order->get_meta( 'billing_comuna', true ),
        $order->get_shipping_city(),
        $order->get_billing_city(),
    ];

    $commune_name = '';

    foreach ( $commune_name_candidates as $candidate ) {
        if ( '' !== trim( (string) $candidate ) ) {
            $commune_name = $candidate;
            break;
        }
    }

    $commune_entry = null;

    if ( $commune_id && isset( $catalog['by_id'][ $commune_id ] ) ) {
        $commune_entry = $catalog['by_id'][ $commune_id ];
    } elseif ( '' !== $commune_name ) {
        $normalized = WooCheck_Shipit_Validator::normalize_commune( $commune_name );

        if ( isset( $catalog['by_name'][ $normalized ] ) ) {
            $commune_entry = $catalog['by_name'][ $normalized ];
        }
    }

    if ( $commune_entry ) {
        $commune_id   = (int) $commune_entry['id'];
        $commune_name = $commune_entry['name'];
        $region_id    = isset( $commune_entry['region_id'] ) ? (int) $commune_entry['region_id'] : null;
        $region_name  = $commune_entry['region_name'] ?? '';
    } else {
        $region_id   = null;
        $region_name = '';
    }

    $region_id_meta_keys = [
        '_shipping_region_id',
        '_billing_region_id',
        'shipping_region_id',
        'billing_region_id',
    ];

    if ( ! $region_id ) {
        foreach ( $region_id_meta_keys as $key ) {
            $value = $order->get_meta( $key, true );

            if ( '' !== $value ) {
                $region_id = (int) $value;

                if ( $region_id > 0 ) {
                    break;
                }
            }
        }
    }

    if ( ! $region_name ) {
        $region_name_candidates = [
            $order->get_shipping_state(),
            $order->get_billing_state(),
        ];

        foreach ( $region_name_candidates as $candidate ) {
            if ( '' !== trim( (string) $candidate ) ) {
                $region_name = $candidate;
                break;
            }
        }
    }

    if ( ! $region_id ) {
        $region_id = wc_check_region_id_from_state_code( $order->get_shipping_state() );

        if ( ! $region_id ) {
            $region_id = wc_check_region_id_from_state_code( $order->get_billing_state() );
        }
    }

    return [
        'commune_id'   => $commune_id ? (int) $commune_id : null,
        'commune_name' => $commune_name,
        'region_id'    => $region_id ? (int) $region_id : null,
        'region_name'  => $region_name,
    ];
}

function woo_check_map_region_name_to_state_code($region_name) {
    static $map = null;

    if (null === $map) {
        $map = array();

        if (function_exists('WC')) {
            $countries = WC()->countries;

            if (is_a($countries, 'WC_Countries')) {
                $states = (array) $countries->get_states('CL');

                foreach ($states as $code => $label) {
                    $normalized_label = WooCheck_Shipit_Validator::normalize_commune($label);
                    $map[$normalized_label] = $code;
                }

                $aliases = array(
                    'OHIGGINS'                     => "Libertador General Bernardo O'Higgins",
                    'LIBERTADOR GENERAL BERNARDO OHIGGINS' => "Libertador General Bernardo O'Higgins",
                    'METROPOLITANA'                => 'Región Metropolitana de Santiago',
                    'METROPOLITANA DE SANTIAGO'    => 'Región Metropolitana de Santiago',
                    'REGION METROPOLITANA'         => 'Región Metropolitana de Santiago',
                );

                foreach ($aliases as $alias => $canonical) {
                    $normalized_alias     = WooCheck_Shipit_Validator::normalize_commune($alias);
                    $normalized_canonical = WooCheck_Shipit_Validator::normalize_commune($canonical);

                    if (isset($map[$normalized_canonical])) {
                        $map[$normalized_alias] = $map[$normalized_canonical];
                    }
                }
            }
        }
    }

    if ('' === trim((string) $region_name)) {
        return '';
    }

    $normalized = WooCheck_Shipit_Validator::normalize_commune($region_name);

    if (isset($map[$normalized])) {
        return $map[$normalized];
    }

    foreach ($map as $normalized_label => $code) {
        if (false !== strpos($normalized_label, $normalized) || false !== strpos($normalized, $normalized_label)) {
            return $code;
        }
    }

    return '';
}

function woo_check_validate_commune_input($value) {
    $value = sanitize_text_field($value);
    $value = trim($value);

    if ('' === $value) {
        return null;
    }

    $catalog = wc_check_get_commune_catalog();
    $normalized = WooCheck_Shipit_Validator::normalize_commune($value);

    if (!isset($catalog['by_name'][$normalized])) {
        return null;
    }

    $entry = $catalog['by_name'][$normalized];
    $region_name = isset($entry['region_name']) ? $entry['region_name'] : '';

    return array(
        'name'        => $value,
        'normalized'  => $normalized,
        'commune_id'  => isset($entry['id']) ? (int) $entry['id'] : null,
        'region_id'   => isset($entry['region_id']) ? (int) $entry['region_id'] : null,
        'region_name' => $region_name,
        'region_code' => woo_check_map_region_name_to_state_code($region_name),
    );
}

function woo_check_apply_commune_to_order(WC_Order $order, $type, array $commune_data) {
    $type = 'shipping' === $type ? 'shipping' : 'billing';
    $name = $commune_data['name'];

    $order->update_meta_data(sprintf('%s_comuna', $type), $name);
    $order->update_meta_data(sprintf('_%s_comuna', $type), $name);

    if (!empty($commune_data['commune_id'])) {
        $order->update_meta_data(sprintf('%s_commune_id', $type), (int) $commune_data['commune_id']);
        $order->update_meta_data(sprintf('_%s_commune_id', $type), (int) $commune_data['commune_id']);
    }

    if (!empty($commune_data['region_id'])) {
        $order->update_meta_data(sprintf('%s_region_id', $type), (int) $commune_data['region_id']);
        $order->update_meta_data(sprintf('_%s_region_id', $type), (int) $commune_data['region_id']);
    }

    if (!empty($commune_data['region_name'])) {
        $order->update_meta_data(sprintf('%s_region_name', $type), $commune_data['region_name']);
        $order->update_meta_data(sprintf('_%s_region_name', $type), $commune_data['region_name']);
    }

    if ('shipping' === $type) {
        $order->set_shipping_city($name);

        if (!empty($commune_data['region_code'])) {
            $order->set_shipping_state($commune_data['region_code']);
        }
    } else {
        $order->set_billing_city($name);

        if (!empty($commune_data['region_code'])) {
            $order->set_billing_state($commune_data['region_code']);
        }
    }
}

function wc_check_order_targets_recibelo( WC_Order $order ) {
    $shipping_state = strtoupper( trim( (string) $order->get_shipping_state() ) );
    $billing_state  = strtoupper( trim( (string) $order->get_billing_state() ) );

    if ( 'CL-RM' === $shipping_state || ( '' === $shipping_state && 'CL-RM' === $billing_state ) ) {
        return true;
    }

    $location   = wc_check_determine_commune_region_data( $order );
    $commune_id = $location['commune_id'];
    $region_id  = $location['region_id'];

    return 'recibelo' === WooCheck_Courier_Router::decide( $commune_id, $region_id );
}

function wc_check_handle_processing_order( $order_id ) {
    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        return;
    }

    $location = wc_check_determine_commune_region_data( $order );

    $force_shipit = '1' === get_option( 'woocheck_force_shipit', '0' );

    if ( $force_shipit ) {
        $courier = 'shipit';
    } else {
        $courier = wc_check_order_targets_recibelo( $order ) ? 'recibelo' : 'shipit';
    }

    /**
     * Filter the courier selected for the order.
     */
    $courier = apply_filters( 'woocheck_courier_selection', $courier, $order, $location );

    $courier_label = 'shipit' === $courier ? 'Shipit' : 'Recíbelo';
    error_log( sprintf( 'WooCheck: Routing order #%d to %s', $order->get_id(), $courier_label ) );

    if ( 'shipit' === $courier ) {
        if ( $order->get_meta( '_shipit_tracking', true ) ) {
            return;
        }

        WooCheck_Shipit::send( $order );
        return;
    }

    if ( 'synced' === $order->get_meta( '_recibelo_sync_status', true ) ) {
        return;
    }

    $response = WooCheck_Recibelo::send( $order );

    if ( is_wp_error( $response ) ) {
        error_log(
            sprintf(
                'WooCheck: Recibelo sync failed for order #%d - %s',
                $order->get_id(),
                $response->get_error_message()
            )
        );
    }
}

add_filter( 'woocommerce_order_actions', 'wc_check_add_recibelo_order_action', 10, 2 );
function wc_check_add_recibelo_order_action( $actions, $order ) {
    if ( ! $order instanceof WC_Order ) {
        return $actions;
    }

    if ( ! wc_check_order_targets_recibelo( $order ) ) {
        return $actions;
    }

    $actions['wc_check_resend_recibelo'] = __( 'Resend to Recíbelo', 'woo-check' );

    return $actions;
}

add_action( 'woocommerce_order_action_wc_check_resend_recibelo', 'wc_check_resend_order_to_recibelo' );
function wc_check_resend_order_to_recibelo( $order ) {
    if ( is_numeric( $order ) ) {
        $order = wc_get_order( $order );
    }

    if ( ! $order instanceof WC_Order ) {
        return;
    }

    $response = WooCheck_Recibelo::send( $order );

    if ( is_wp_error( $response ) ) {
        $order->add_order_note(
            sprintf(
                __( 'Recíbelo resend failed: %s', 'woo-check' ),
                $response->get_error_message()
            )
        );

        return;
    }

    $status_code = (int) wp_remote_retrieve_response_code( $response );

    if ( $status_code >= 400 ) {
        $order->add_order_note(
            sprintf(
                __( 'Recíbelo resend returned HTTP %d. Please review logs.', 'woo-check' ),
                $status_code
            )
        );

        return;
    }

    $order->add_order_note( __( 'WooCheck: Order resent to Recíbelo.', 'woo-check' ) );
    $order->update_meta_data( '_recibelo_sync_status', 'synced' );
    $order->delete_meta_data( '_recibelo_sync_failed' );
    $order->save_meta_data();
}

/**
 * Map a Recíbelo raw status into a user-friendly label.
 *
 * @param string $status Raw status returned by Recíbelo.
 *
 * @return string
 */
function woocheck_recibelo_status_label( $status ) {
    $status = trim( (string) $status );

    if ( '' === $status ) {
        return '';
    }

    $normalized = strtolower( $status );

    $map = [
        'creado'            => __( 'Preparando envío', 'woo-check' ),
        'etiqueta impresa'  => __( 'Preparando envío', 'woo-check' ),
        'preparado'         => __( 'Preparando envío', 'woo-check' ),
        'retirado'          => __( 'En tránsito', 'woo-check' ),
        'en deposito'       => __( 'En tránsito', 'woo-check' ),
        'en ruta'           => __( 'En tránsito', 'woo-check' ),
        'completado'        => __( 'Finalizado', 'woo-check' ),
        'no aceptado'       => __( 'Error/Rechazo', 'woo-check' ),
    ];

    if ( array_key_exists( $normalized, $map ) ) {
        return $map[ $normalized ];
    }

    return $status;
}

/**
 * Normalize a string so it can be safely compared against Recíbelo payload data.
 *
 * @param string $value Raw string.
 *
 * @return string Normalized string.
 */
function woocheck_recibelo_normalize_string( $value ) {
    $value = strtolower( trim( (string) $value ) );

    if ( '' === $value ) {
        return '';
    }

    if ( function_exists( 'remove_accents' ) ) {
        $value = remove_accents( $value );
    }

    $value = preg_replace( '/\s+/u', ' ', $value );

    return $value ?: '';
}

/**
 * Determine whether the provided array uses sequential numeric keys.
 *
 * @param array $array Array to inspect.
 *
 * @return bool
 */
function woocheck_recibelo_is_sequential_array( $array ) {
    if ( ! is_array( $array ) ) {
        return false;
    }

    $expected = 0;

    foreach ( $array as $key => $_ ) {
        if ( (int) $key !== $expected ) {
            return false;
        }

        $expected++;
    }

    return true;
}

/**
 * Retrieve a nested array value using the provided path.
 *
 * @param array $data Source array.
 * @param array $path Path of keys to traverse.
 *
 * @return mixed|null
 */
function woocheck_recibelo_dig_value( $data, array $path ) {
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
 * Extract the packages array from an arbitrary Recíbelo API payload.
 *
 * @param mixed $payload API response body.
 *
 * @return array<int,array<string,mixed>>
 */
function woocheck_recibelo_extract_packages( $payload ) {
    if ( ! is_array( $payload ) ) {
        return [];
    }

    if ( woocheck_recibelo_is_sequential_array( $payload ) ) {
        return $payload;
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
        $value = woocheck_recibelo_dig_value( $payload, $path );

        if ( is_array( $value ) ) {
            if ( woocheck_recibelo_is_sequential_array( $value ) ) {
                return $value;
            }

            return array_values( $value );
        }
    }

    return [];
}

/**
 * Render the Recíbelo tracking widget for a given order.
 *
 * @param int|WC_Order $order Order instance or ID.
 */
function woocheck_render_recibelo_tracking_widget( $order ) {
    if ( is_numeric( $order ) ) {
        $order = wc_get_order( $order );
    }

    if ( ! $order instanceof WC_Order ) {
        return;
    }

    $order_id         = $order->get_id();
    $default_message  = esc_html__( 'Estamos consultando el estado de este envío...', 'woo-check' );
    $status_message   = $default_message;
    $raw_status_label = '';
    $history_statuses = [];

    $internal_id = $order->get_meta( '_recibelo_internal_id', true );

    if ( empty( $internal_id ) ) {
        $internal_id = $order->get_meta( '_tracking_number', true );
    }

    $customer_name     = trim( $order->get_formatted_billing_full_name() );
    $shipping_customer = method_exists( $order, 'get_formatted_shipping_full_name' ) ? trim( (string) $order->get_formatted_shipping_full_name() ) : '';
    $normalized_names  = [];

    foreach ( [ $customer_name, $shipping_customer ] as $candidate ) {
        $normalized = woocheck_recibelo_normalize_string( $candidate );

        if ( '' !== $normalized ) {
            $normalized_names[ $normalized ] = true;
        }
    }

    if ( ! empty( $internal_id ) ) {
        $url      = add_query_arg( [ 'internal_ids[]' => $internal_id ], 'https://app.recibelo.cl/api/check-package-internal-id' );
        $response = wp_remote_get(
            $url,
            [
                'headers' => [ 'Accept' => 'application/json' ],
                'timeout' => 20,
            ]
        );

        if ( ! is_wp_error( $response ) ) {
            $body     = json_decode( wp_remote_retrieve_body( $response ), true );
            $packages = woocheck_recibelo_extract_packages( $body );

            if ( ! empty( $packages ) ) {
                $normalized_internal_id = strtolower( trim( (string) $internal_id ) );
                $package                = null;
                $fallback_package       = null;

                foreach ( $packages as $item ) {
                    if ( ! is_array( $item ) ) {
                        continue;
                    }

                    $internal_candidates = [
                        $item['internal_id']    ?? null,
                        $item['internalId']     ?? null,
                        $item['internalID']     ?? null,
                        $item['id']             ?? null,
                        $item['tracking_id']    ?? null,
                        $item['trackingId']     ?? null,
                    ];

                    $package_internal_id = '';

                    foreach ( $internal_candidates as $candidate ) {
                        $candidate = trim( (string) $candidate );

                        if ( '' !== $candidate ) {
                            $package_internal_id = $candidate;
                            break;
                        }
                    }

                    $normalized_package_internal = strtolower( $package_internal_id );

                    if ( '' !== $normalized_internal_id && '' !== $normalized_package_internal && $normalized_package_internal === $normalized_internal_id ) {
                        $fallback_package = $fallback_package ?? $item;
                    }

                    $name_candidates = [
                        $item['contact_full_name'] ?? null,
                        $item['contactFullName']   ?? null,
                        $item['contact_name']      ?? null,
                        $item['contactName']       ?? null,
                        $item['customer_name']     ?? null,
                        $item['customerName']      ?? null,
                    ];

                    $matched = empty( $normalized_names );

                    if ( ! $matched ) {
                        foreach ( $name_candidates as $name_candidate ) {
                            $normalized_name = woocheck_recibelo_normalize_string( $name_candidate );

                            if ( '' === $normalized_name ) {
                                continue;
                            }

                            if ( isset( $normalized_names[ $normalized_name ] ) ) {
                                $matched = true;
                                break;
                            }
                        }
                    }

                    if ( ! $matched ) {
                        continue;
                    }

                    $package = $item;
                    break;
                }

                if ( ! $package && $fallback_package ) {
                    $package = $fallback_package;
                }

                if ( $package ) {
                    $current_status_candidates = [
                        $package['current_status'] ?? null,
                        $package['currentStatus']  ?? null,
                    ];

                    foreach ( $current_status_candidates as $candidate ) {
                        $candidate = trim( (string) $candidate );

                        if ( '' !== $candidate ) {
                            $raw_status_label = $candidate;
                            break;
                        }
                    }

                    if ( '' !== $raw_status_label ) {
                        $friendly_label = woocheck_recibelo_status_label( $raw_status_label );
                        $status_message = $friendly_label;

                        foreach ( [ 'history_statuses', 'historyStatuses', 'history' ] as $history_key ) {
                            if ( isset( $package[ $history_key ] ) && is_array( $package[ $history_key ] ) ) {
                                $history_statuses = $package[ $history_key ];
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    $widget_id = 'woocheck-recibelo-tracking-' . absint( $order_id );

    echo '<div id="' . esc_attr( $widget_id ) . '" class="woocheck-recibelo-tracking-widget">';
    echo '<p class="woocheck-recibelo-current-status">' . esc_html( $status_message );

    if ( '' !== $raw_status_label && $status_message !== $raw_status_label ) {
        echo ' <small>(' . esc_html( $raw_status_label ) . ')</small>';
    }

    echo '</p>';

    if ( ! empty( $history_statuses ) ) {
        echo '<ul class="woocheck-recibelo-history">';

        foreach ( $history_statuses as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }

            $entry_status = '';

            foreach ( [ 'status', 'name' ] as $entry_status_key ) {
                if ( isset( $entry[ $entry_status_key ] ) && '' !== trim( (string) $entry[ $entry_status_key ] ) ) {
                    $entry_status = (string) $entry[ $entry_status_key ];
                    break;
                }
            }

            if ( '' === $entry_status ) {
                continue;
            }

            $entry_label = woocheck_recibelo_status_label( $entry_status );
            $timestamp   = '';

            foreach ( [ 'created_at', 'createdAt', 'date' ] as $entry_date_key ) {
                if ( isset( $entry[ $entry_date_key ] ) && '' !== trim( (string) $entry[ $entry_date_key ] ) ) {
                    $timestamp = (string) $entry[ $entry_date_key ];
                    break;
                }
            }

            echo '<li>' . esc_html( $entry_label );

            if ( $entry_label !== $entry_status ) {
                echo ' <small>(' . esc_html( $entry_status ) . ')</small>';
            }

            if ( '' !== $timestamp ) {
                echo ' <time datetime="' . esc_attr( $timestamp ) . '">' . esc_html( $timestamp ) . '</time>';
            }

            echo '</li>';
        }

        echo '</ul>';
    }

    echo '</div>';
}


// Override WooCommerce checkout template if needed
add_filter('woocommerce_locate_template', 'woo_check_override_checkout_template', 10, 3);
function woo_check_override_checkout_template($template, $template_name, $template_path) {
    if ($template_name === 'checkout/form-checkout.php') {
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/checkout/form-checkout.php';
        if (file_exists($plugin_template)) {
            error_log('WooCheck: usando template de plugin: ' . $plugin_template);
            return $plugin_template;
        } else {
            error_log('WooCheck: template no encontrado en ' . $plugin_template);
        }
    } else {
        error_log("WooCheck: plantilla cargada por defecto -> $template_name ($template)");
    }
    return $template;
}


// Override WooCommerce Lost Password Confirmation
add_filter('woocommerce_locate_template', 'woo_check_override_lost_password_confirmation', 10, 3);

function woo_check_override_lost_password_confirmation($template, $template_name, $template_path) {
    if ($template_name === 'myaccount/lost-password-confirmation.php') {
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/myaccount/lost-password-confirmation.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
}

// Override WooCommerce order details customer template
add_filter('woocommerce_locate_template', 'woo_check_override_order_details_customer_template', 10, 3);
function woo_check_override_order_details_customer_template($template, $template_name, $template_path) {
    if ($template_name === 'order/order-details-customer.php') {
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/order/order-details-customer.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
}

// Override WooCommerce order received template
add_filter('template_include', 'woo_check_override_order_received_template', 99);

function woo_check_override_order_received_template($template) {
    if (is_wc_endpoint_url('order-received')) {
        $custom_template = plugin_dir_path(__FILE__) . 'templates/checkout/order-received.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }
    return $template;
}

// Override WooCommerce email addresses template
add_filter('woocommerce_locate_template', 'woo_check_override_email_addresses_template', 10, 3);
function woo_check_override_email_addresses_template($template, $template_name, $template_path) {
    if ($template_name === 'emails/email-addresses.php') {
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/emails/email-addresses.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
}

add_filter('woocommerce_locate_template', 'woo_check_override_myaccount_templates', 10, 3);
function woo_check_override_myaccount_templates($template, $template_name, $template_path) {
    // Define the path to your plugin's templates folder
    $plugin_path = plugin_dir_path(__FILE__) . 'templates/';

    // Check if the template belongs to the myaccount folder and exists in your plugin
    if (strpos($template_name, 'myaccount/') === 0) {
        $custom_template = $plugin_path . $template_name;
        if (file_exists($custom_template)) {
            error_log('Using custom template: ' . $custom_template); // Log the custom template being used
            return $custom_template;
        }
    }

    error_log('Using default template: ' . $template); // Log the default template
    return $template;
}


add_filter('woocommerce_locate_template', 'woo_check_override_lost_password_template', 10, 3);

function woo_check_override_lost_password_template($template, $template_name, $template_path) {
    if ($template_name === 'myaccount/form-lost-password.php') {
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/myaccount/form-lost-password-custom.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
}

// 7 - Enqueue scripts
add_action('wp_enqueue_scripts', 'woo_check_enqueue_assets');
function woo_check_enqueue_assets() {

    wp_enqueue_script(
        'woo-check-comunas-chile',
        plugin_dir_url(__FILE__) . 'comunas-chile.js',
        array(), // No dependencies
        '1.0',
        true
    );

    // Enqueue general styles everywhere
    wp_enqueue_style(
        'woo-check-general-style',
        plugin_dir_url(__FILE__) . 'general.css',
        array(),
        '1.0'
    );

    $is_checkout     = function_exists('is_checkout') ? is_checkout() : false;
    $is_edit_address = function_exists('is_wc_endpoint_url') ? is_wc_endpoint_url('edit-address') : false;

    // Enqueue on the Order Received page
    if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) {
        wp_enqueue_style(
            'woo-check-order-received-style',
            plugin_dir_url(__FILE__) . 'order-received.css',
            array(),
            '1.0'
        );
    }

    // Enqueue on the Checkout page or Edit Address page
    if ($is_checkout || $is_edit_address) {
        wp_enqueue_style(
            'woo-check-style',
            plugin_dir_url(__FILE__) . 'woo-check-style.css',
            array(),
            '1.0'
        );

        wp_enqueue_script(
            'woo-check-autocomplete',
            plugin_dir_url(__FILE__) . 'woo-check-autocomplete.js',
            array('jquery', 'jquery-ui-autocomplete'),
            '1.0',
            true
        );
    }

    if ($is_checkout) {
        $checkout_script_path = plugin_dir_path(__FILE__) . 'woo-check.js';
        $checkout_script_ver  = file_exists($checkout_script_path) ? filemtime($checkout_script_path) : '1.0';

        wp_enqueue_script(
            'woo-check-checkout',
            plugin_dir_url(__FILE__) . 'woo-check.js',
            array('jquery'),
            $checkout_script_ver,
            true
        );
    }
}

add_action( 'wp_enqueue_scripts', function() {
    if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
        wp_enqueue_script(
            'woocheck-tracking',
            plugins_url( 'assets/js/woocheck-tracking.js', __FILE__ ),
            [ 'jquery' ],
            '1.0',
            true
        );

        wp_localize_script(
            'woocheck-tracking',
            'WooCheckAjax',
            [
                'ajax_url'         => admin_url( 'admin-ajax.php' ),
                'fallback_message' => __( 'Estamos consultando el estado de este envío...', 'woo-check' ),
            ]
        );
    }
} );

add_action('admin_enqueue_scripts', 'woo_check_enqueue_admin_order_assets');
function woo_check_enqueue_admin_order_assets($hook) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    if (empty($screen)) {
        return;
    }

    $is_order_editor = false;

    if (isset($screen->post_type) && 'shop_order' === $screen->post_type) {
        $is_order_editor = true;
    } elseif (isset($screen->id) && in_array($screen->id, array('shop_order', 'woocommerce_page_wc-orders'), true)) {
        $is_order_editor = true;
    }

    if (!$is_order_editor) {
        return;
    }

    wp_enqueue_script(
        'woo-check-comunas-chile',
        plugin_dir_url(__FILE__) . 'comunas-chile.js',
        array(),
        '1.0',
        true
    );

    wp_enqueue_script(
        'woo-check-admin-order',
        plugin_dir_url(__FILE__) . 'woo-check-admin-order.js',
        array('jquery', 'jquery-ui-autocomplete', 'woo-check-comunas-chile'),
        '1.0',
        true
    );
}


// 8 - Customize and Remove Unwanted Checkout Fields
add_filter('woocommerce_checkout_fields', 'customize_and_remove_unwanted_checkout_fields');
function customize_and_remove_unwanted_checkout_fields($fields) {
    // Remove unwanted fields (Company, Postal Code, and City)
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_postcode']);
    unset($fields['billing']['billing_city']);
    unset($fields['shipping']['shipping_company']);
    unset($fields['shipping']['shipping_postcode']);
    unset($fields['shipping']['shipping_city']);

    // Reorganize and customize billing fields
    $fields['billing'] = array(
        'billing_first_name' => $fields['billing']['billing_first_name'],
        'billing_last_name'  => $fields['billing']['billing_last_name'],
        'billing_address_1'  => $fields['billing']['billing_address_1'],
        'billing_address_2'  => $fields['billing']['billing_address_2'],
        'billing_comuna'     => array(
            'label'       => 'Comuna',
            'placeholder' => 'Ingresa comuna',
            'required'    => true,
            'class'       => array('form-row-wide'),
            'priority'    => 60, // Changed from 40
        ),
        'billing_state'      => $fields['billing']['billing_state'],
        'billing_phone'      => $fields['billing']['billing_phone'],
        'billing_email'      => $fields['billing']['billing_email'],
    );
// **Ahora** sobreescribe la etiqueta (label) de billing_state:
$fields['billing']['billing_state']['label'] = 'Regiones';


    // Reorganize and customize shipping fields
    $fields['shipping'] = array(
        'shipping_first_name' => $fields['shipping']['shipping_first_name'],
        'shipping_last_name'  => $fields['shipping']['shipping_last_name'],
        'shipping_address_1'  => $fields['shipping']['shipping_address_1'],
        'shipping_address_2'  => $fields['shipping']['shipping_address_2'],
        'shipping_comuna'     => array(
            'label'       => 'Comuna',
            'placeholder' => 'Ingresa comuna',
            'required'    => true,
            'class'       => array('form-row-wide'),
            'priority'    => 60, // Adjusted from 40
        ),
        'shipping_state'      => $fields['shipping']['shipping_state'],
        'shipping_phone'      => array(
            'type'       => 'tel',
            'label'      => __('Teléfono de quien recibe', 'woocommerce'),
            'required'   => false,
            'class'      => array('form-row-wide'),
            'priority'   => 70, // Added priority to control placement
        ),
    );
    // **Ahora** sobreescribe la etiqueta (label) de billing_state:
$fields['shipping']['shipping_state']['label'] = 'Regiones';

    return $fields;
}

// Ensure Comuna is visible and editable in the admin order edit screen.
add_filter('woocommerce_admin_billing_fields', function ($fields, $order) {
    return woo_check_register_admin_comuna_field($fields, 'billing', $order);
}, 10, 2);

add_filter('woocommerce_admin_shipping_fields', function ($fields, $order) {
    return woo_check_register_admin_comuna_field($fields, 'shipping', $order);
}, 10, 2);

function woo_check_register_admin_comuna_field($fields, $type, $order = null) {
    $type          = 'shipping' === $type ? 'shipping' : 'billing';
    $key           = 'comuna';
    $state_key     = sprintf('%s_state', $type);
    $field_args    = array(
        'label' => __('Comuna', 'woocommerce'),
        'show'  => true,
        'value' => '',
        'type'  => 'text',
        'placeholder' => __('Ingresa comuna', 'woo-check'),
        'wrapper_class' => 'form-field-wide woo-check-admin-comuna-field',
        'class' => array('woo-check-admin-comuna-input'),
        'custom_attributes' => array(
            'autocomplete' => 'off',
        ),
    );

    if ($order instanceof WC_Order) {
        $field_args['value'] = woo_check_get_order_comuna_value($order, $type);
    }

    if (isset($fields[$state_key])) {
        $state_field = $fields[$state_key];
        unset($fields[$state_key]);
        $fields[$key]     = $field_args;
        $fields[$state_key] = $state_field;
    } else {
        $fields[$key] = $field_args;
    }

    return $fields;
}

function woo_check_get_order_comuna_value(WC_Order $order, $type) {
    $type = 'shipping' === $type ? 'shipping' : 'billing';
    $meta_keys = array(
        sprintf('%s_comuna', $type),
        sprintf('_%s_comuna', $type),
        sprintf('%s_city', $type),
        sprintf('_%s_city', $type),
    );

    foreach ($meta_keys as $meta_key) {
        $value = $order->get_meta($meta_key, true);

        if ('' !== trim((string) $value)) {
            return $value;
        }
    }

    if ('shipping' === $type) {
        $value = $order->get_shipping_city();
    } else {
        $value = $order->get_billing_city();
    }

    return trim((string) $value);
}

add_action('woocommerce_process_shop_order_meta', 'woo_check_process_admin_order_commune');
function woo_check_process_admin_order_commune($order_id) {
    $order = wc_get_order($order_id);

    if (!$order instanceof WC_Order) {
        return;
    }

    $config = array(
        'billing'  => array(
            'field' => '_billing_comuna',
        ),
        'shipping' => array(
            'field' => '_shipping_comuna',
        ),
    );

    $updated = false;

    foreach ($config as $type => $settings) {
        $field_name = $settings['field'];

        if (!isset($_POST[$field_name])) {
            continue;
        }

        $raw_value = wp_unslash($_POST[$field_name]);
        $commune_data = woo_check_validate_commune_input($raw_value);

        if (!$commune_data) {
            $order->update_meta_data(sprintf('%s_comuna', $type), '');
            $order->update_meta_data(sprintf('_%s_comuna', $type), '');

            if (class_exists('WC_Admin_Meta_Boxes')) {
                $label = 'billing' === $type ? __('facturación', 'woo-check') : __('envío', 'woo-check');
                WC_Admin_Meta_Boxes::add_error(
                    sprintf(
                        __('La comuna de %s debe seleccionarse desde la lista de comunas disponibles.', 'woo-check'),
                        $label
                    )
                );
            }

            $updated = true;
            continue;
        }

        woo_check_apply_commune_to_order($order, $type, $commune_data);
        $updated = true;
    }

    if ($updated) {
        $order->save();
    }
}

// 9 - Save Billing and Shipping Comuna Fields to Order Meta
add_action('woocommerce_checkout_update_order_meta', 'save_comuna_order_meta');
function save_comuna_order_meta($order_id) {
    if (!empty($_POST['billing_comuna'])) {
        error_log('Saving billing_comuna: ' . sanitize_text_field($_POST['billing_comuna']));
        update_post_meta($order_id, 'billing_comuna', sanitize_text_field($_POST['billing_comuna']));
    }
    if (!empty($_POST['shipping_comuna'])) {
        error_log('Saving shipping_comuna: ' . sanitize_text_field($_POST['shipping_comuna']));
        update_post_meta($order_id, 'shipping_comuna', sanitize_text_field($_POST['shipping_comuna']));
    }
}

// 10 - EDIT SHIPPING ADDRESS MY ACCOUNT

// Add or modify the Comuna field in the Edit Address form
add_filter('woocommerce_default_address_fields', 'add_comuna_field_to_edit_address');
function add_comuna_field_to_edit_address($fields) {
    // Add 'comuna' field with adjusted priority
    $fields['comuna'] = array(
        'label'       => __('Comuna', 'woocommerce'),
        'placeholder' => __('Ingresa comuna', 'woocommerce'),
        'required'    => true,
        'class'       => array('form-row-wide'),
        'priority'    => 60, // Adjust priority to place it below address_2
    );

    // Adjust other field priorities
    $fields['address_1']['priority'] = 50; // Street Address 1
    $fields['address_2']['priority'] = 55; // Street Address 2 (optional)
    $fields['state']['priority'] = 70;     // Region/State field
    $fields['phone']['priority'] = 80;    // Phone field

    // Remove unwanted fields
    unset($fields['city']);
    unset($fields['postcode']);
    unset($fields['company']);

    return $fields;
}

// 11 - Save Comuna and Phone values when the Edit Address form is submitted
add_action('woocommerce_customer_save_address', 'save_comuna_and_phone_fields_in_edit_address', 10, 2);
function save_comuna_and_phone_fields_in_edit_address($user_id, $load_address) {
    if ($load_address === 'billing' && isset($_POST['billing_comuna'])) {
        update_user_meta($user_id, 'billing_comuna', sanitize_text_field($_POST['billing_comuna']));
    }
    if ($load_address === 'shipping' && isset($_POST['shipping_comuna'])) {
        update_user_meta($user_id, 'shipping_comuna', sanitize_text_field($_POST['shipping_comuna']));
    }
    if (isset($_POST['shipping_phone'])) {
        update_user_meta($user_id, 'shipping_phone', sanitize_text_field($_POST['shipping_phone']));
    }
}


// LOST PASSWORD PROCESS SUBMITION

add_action('admin_post_nopriv_custom_process_lost_password', 'custom_process_lost_password');
add_action('admin_post_custom_process_lost_password', 'custom_process_lost_password');

function custom_process_lost_password() {
    if (!isset($_POST['woocommerce-lost-password-nonce']) || !wp_verify_nonce($_POST['woocommerce-lost-password-nonce'], 'lost_password')) {
        wp_die(__('Invalid request.', 'woocommerce'));
    }

    if (empty($_POST['user_login'])) {
        wp_redirect(home_url('/reset-status/?reset_error=' . urlencode(__('Por favor ingresa tu correo electrónico.', 'woocommerce'))));
        exit;
    }

    $user_login = sanitize_text_field($_POST['user_login']);
    $user = get_user_by('email', $user_login);

    if (!$user) {
        // Redirect to reset-status with an error message
        wp_redirect(home_url('/reset-status/?reset_error=' . urlencode(__('No existe una cuenta con ese correo electrónico.', 'woocommerce'))));
        exit;
    }

    // Trigger WooCommerce password reset function
    WC_Shortcode_My_Account::retrieve_password();

    // Redirect with success message and user email
    wp_redirect(home_url('/reset-status/?reset_success=' . urlencode(__('Hemos enviado un link para restablecer tu clave. Revisa tu correo ' . $user_login . ' y sigue las instrucciones.', 'woocommerce'))));
    exit;
}



// Hook to register a custom page
add_action('init', 'woo_check_register_reset_status_page');

function woo_check_register_reset_status_page() {
    $page_slug = 'reset-status';

    // Check if the page already exists
    $existing_page = get_page_by_path($page_slug);
    
    if (!$existing_page) {
        $page_id = wp_insert_post(array(
            'post_title'    => 'Password Reset Status',
            'post_name'     => $page_slug,
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '',
        ));
    }
}

// Override the reset-status page template
add_filter('template_include', 'woo_check_override_reset_status_template');

function woo_check_override_reset_status_template($template) {
    if (is_page('reset-status')) {
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/myaccount/reset-status.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
}

