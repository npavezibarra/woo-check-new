<?php

/**
 * Wrap WooCommerce checkout login and coupon notices inside a flex container.
 */
add_action( 'woocommerce_before_checkout_form', function() {
    echo '<div class="checkout-notices-row">';
}, 5 );

if ( function_exists( 'woocommerce_checkout_coupon_form' ) ) {
    remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );

    add_action( 'woocommerce_before_checkout_form', function() {
        if ( function_exists( 'wc_coupons_enabled' ) && ! wc_coupons_enabled() ) {
            return;
        }

        echo '<div id="checkout-notices-divider" aria-hidden="true"></div>';
        woocommerce_checkout_coupon_form();
    }, 15 );
}

add_action( 'woocommerce_before_checkout_form', function() {
    echo '</div>';
}, 20 );

add_action( 'wp_enqueue_scripts', function() {
    if ( function_exists( 'is_checkout' ) && ! is_checkout() ) {
        return;
    }

    $script_path = plugin_dir_path( __FILE__ ) . 'js/checkout-coupon-modal.js';
    $script_url  = plugin_dir_url( __FILE__ ) . 'js/checkout-coupon-modal.js';
    $version     = file_exists( $script_path ) ? filemtime( $script_path ) : '1.0.0';

    wp_enqueue_script(
        'checkout-coupon-modal',
        $script_url,
        [ 'jquery' ],
        $version,
        true
    );
}, 20 );
