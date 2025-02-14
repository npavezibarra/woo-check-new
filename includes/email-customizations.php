<?php

// Add custom addresses formatting for WooCommerce emails
add_action('woocommerce_email_customer_details', 'woo_check_custom_email_addresses', 9, 4);

function woo_check_custom_email_addresses( $order, $sent_to_admin, $plain_text, $email ) {
    // Get the billing and shipping comuna fields
    $billing_comuna = get_post_meta( $order->get_id(), 'billing_comuna', true );
    $shipping_comuna = get_post_meta( $order->get_id(), 'shipping_comuna', true );

    // Display Billing Address
    ?>
    <h2><?php esc_html_e( 'Billing address', 'woocommerce' ); ?></h2>
    <address>
        <?php if ( $order->get_billing_first_name() || $order->get_billing_last_name() ) : ?>
            <?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?><br>
        <?php endif; ?>
        <?php if ( $order->get_billing_address_1() ) : ?>
            <?php echo esc_html( $order->get_billing_address_1() ); ?><br>
        <?php endif; ?>
        <?php if ( $order->get_billing_address_2() ) : ?>
            <?php echo esc_html( $order->get_billing_address_2() ); ?><br>
        <?php endif; ?>
        <?php if ( ! empty( $billing_comuna ) ) : ?>
            <?php echo esc_html( $billing_comuna ); ?><br>
        <?php endif; ?>
        <?php if ( $order->get_billing_state() ) : ?>
            <?php echo esc_html( $order->get_billing_state() ); ?><br>
        <?php endif; ?>
        <?php if ( $order->get_billing_phone() ) : ?>
            <?php echo wc_make_phone_clickable( $order->get_billing_phone() ); ?><br>
        <?php endif; ?>
        <?php if ( $order->get_billing_email() ) : ?>
            <?php echo esc_html( $order->get_billing_email() ); ?>
        <?php endif; ?>
    </address>
    <?php

    // If shipping is needed and exists, display Shipping Address
    if ( ! wc_ship_to_billing_address_only() && $order->needs_shipping_address() && $order->get_formatted_shipping_address() ) : ?>
        <h2><?php esc_html_e( 'Shipping address', 'woocommerce' ); ?></h2>
        <address>
            <?php if ( $order->get_shipping_first_name() || $order->get_shipping_last_name() ) : ?>
                <?php echo esc_html( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ); ?><br>
            <?php endif; ?>
            <?php if ( $order->get_shipping_address_1() ) : ?>
                <?php echo esc_html( $order->get_shipping_address_1() ); ?><br>
            <?php endif; ?>
            <?php if ( $order->get_shipping_address_2() ) : ?>
                <?php echo esc_html( $order->get_shipping_address_2() ); ?><br>
            <?php endif; ?>
            <?php if ( ! empty( $shipping_comuna ) ) : ?>
                <?php echo esc_html( $shipping_comuna ); ?><br>
            <?php endif; ?>
            <?php if ( $order->get_shipping_state() ) : ?>
                <?php echo esc_html( $order->get_shipping_state() ); ?><br>
            <?php endif; ?>
            <?php if ( $order->get_shipping_phone() ) : ?>
                <?php echo wc_make_phone_clickable( $order->get_shipping_phone() ); ?>
            <?php endif; ?>
        </address>
    <?php endif;
}