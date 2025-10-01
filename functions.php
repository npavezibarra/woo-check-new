<?php

/**
 * Wrap WooCommerce checkout login and coupon notices inside a flex container.
 */
add_action( 'woocommerce_before_checkout_form', function() {
    echo '<div class="checkout-notices-row">';
}, 5 );

if ( function_exists( 'woocommerce_checkout_coupon_form' ) ) {
    remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
    add_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 15 );
}

add_action( 'woocommerce_before_checkout_form', function() {
    echo '<div id="checkout-notices-divider" aria-hidden="true"></div>';
}, 13 );

add_action( 'woocommerce_before_checkout_form', function() {
    echo '</div>';
}, 20 );
