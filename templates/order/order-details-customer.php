<?php
/**
 * Order Customer Details
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/order-details-customer.php.
 * 
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.7.0
 */

defined( 'ABSPATH' ) || exit;

$show_shipping = ! wc_ship_to_billing_address_only() && $order->needs_shipping_address();
?>
<section class="woocommerce-customer-details">

    <?php if ( $show_shipping ) : ?>

    <section class="woocommerce-columns woocommerce-columns--2 woocommerce-columns--addresses col2-set addresses">
        <div class="woocommerce-column woocommerce-column--1 woocommerce-column--billing-address col-1">

    <?php endif; ?>

    <h2 class="woocommerce-column__title"><?php esc_html_e( 'Billing address', 'woocommerce' ); ?></h2>

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
        <?php 
            $billing_comuna = get_post_meta($order->get_id(), 'billing_comuna', true);
            if ( ! empty( $billing_comuna ) ) {
                echo esc_html( $billing_comuna ) . '<br>';
            } 
        ?>
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

    <?php if ( $show_shipping ) : ?>

        </div><!-- /.col-1 -->

        <div class="woocommerce-column woocommerce-column--2 woocommerce-column--shipping-address col-2">
            <h2 class="woocommerce-column__title"><?php esc_html_e( 'Shipping address', 'woocommerce' ); ?></h2>
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
                <?php 
                    $shipping_comuna = get_post_meta($order->get_id(), 'shipping_comuna', true);
                    if ( ! empty( $shipping_comuna ) ) {
                        echo esc_html( $shipping_comuna ) . '<br>';
                    } 
                ?>
                <?php if ( $order->get_shipping_state() ) : ?>
                    <?php echo esc_html( $order->get_shipping_state() ); ?><br>
                <?php endif; ?>
                <?php if ( $order->get_shipping_phone() ) : ?>
                    <?php echo wc_make_phone_clickable( $order->get_shipping_phone() ); ?>
                <?php endif; ?>
            </address>
        </div><!-- /.col-2 -->
        
    </section><!-- /.col2-set -->
    

    <?php endif; ?>

    <?php do_action( 'woocommerce_order_details_after_customer_details', $order ); ?>

</section>
