<?php

/**
 * Wrap WooCommerce checkout login and coupon notices inside a flex container.
 */
add_action( 'woocommerce_before_checkout_form', function() {
    ?>
    <div class="checkout-notices-row">
    <?php
}, 5 );

add_action( 'woocommerce_before_checkout_form', function() {
    ?>
    </div>
    <?php
}, 15 );
