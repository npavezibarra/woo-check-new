<?php

/**
 * Wrap WooCommerce checkout login and coupon notices inside a flex container.
 */
add_action( 'woocommerce_before_checkout_form', function() {
    echo '<div class="checkout-notices-row">';
}, 5 );

add_action( 'woocommerce_before_checkout_form', function() {
    echo '</div>';
}, 15 );
