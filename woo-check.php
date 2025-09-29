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
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-check-recibelo.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-woo-check-courier-router.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-woo-check-shipit-validator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-check-shipit.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-check-shipit-webhook.php';

add_action( 'woocommerce_order_status_processing', 'wc_check_handle_processing_order', 10, 1 );

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


// Override WooCommerce checkout template if needed
add_filter('woocommerce_locate_template', 'woo_check_override_checkout_template', 10, 3);
function woo_check_override_checkout_template($template, $template_name, $template_path) {
    if ($template_name === 'checkout/form-checkout.php') {
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/checkout/form-checkout.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
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

    // Enqueue on the Order Received page
    if (is_wc_endpoint_url('order-received')) {
        wp_enqueue_style(
            'woo-check-order-received-style',
            plugin_dir_url(__FILE__) . 'order-received.css',
            array(),
            '1.0'
        );
    }

    // Enqueue on the Checkout page or Edit Address page
    if (is_checkout() || is_wc_endpoint_url('edit-address')) {
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

