<?php
/**
 * View Order
 *
 * Custom layout for the Woo Check plugin order view page.
 *
 * @package Woo_Check
 */

defined( 'ABSPATH' ) || exit;

$order = isset( $order_id ) ? wc_get_order( $order_id ) : false;

if ( ! $order ) {
    return;
}
?>

<div class="woocommerce-order-columns">

    <!-- Left Column -->
    <div class="woocommerce-order-column woocommerce-order-column--left">
        <h2><?php printf( __( 'Order #%s', 'woo-check' ), esc_html( $order->get_order_number() ) ); ?></h2>
        <p><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></p>
        <p><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></p>

        <!-- Tracking (if handled by woo-check) -->
        <?php do_action( 'woo_check_order_tracking', $order ); ?>

        <h3><?php esc_html_e( 'Billing Address', 'woo-check' ); ?></h3>
        <address>
            <?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?><br>
            <?php echo esc_html( $order->get_billing_phone() ); ?><br>
            <?php echo esc_html( $order->get_billing_email() ); ?>
        </address>

        <h3><?php esc_html_e( 'Totals', 'woo-check' ); ?></h3>
        <ul class="woocommerce-order-totals">
            <li><?php esc_html_e( 'Subtotal:', 'woo-check' ); ?> <?php echo wp_kses_post( $order->get_subtotal_to_display() ); ?></li>
            <li><?php esc_html_e( 'Shipping:', 'woo-check' ); ?> <?php echo wp_kses_post( wc_price( $order->get_shipping_total() ) ); ?></li>
            <li><?php esc_html_e( 'Tax:', 'woo-check' ); ?> <?php echo wp_kses_post( wc_price( $order->get_total_tax() ) ); ?></li>
            <li><strong><?php esc_html_e( 'Total:', 'woo-check' ); ?> <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong></li>
        </ul>
    </div>

    <!-- Right Column -->
    <div class="woocommerce-order-column woocommerce-order-column--right">
        <h3><?php esc_html_e( 'Your Items', 'woo-check' ); ?></h3>
        <ul class="order_items">
            <?php foreach ( $order->get_items() as $item_id => $item ) : ?>
                <?php
                $product   = $item->get_product();
                $thumbnail = $product ? $product->get_image( 'thumbnail' ) : '';
                ?>
                <li class="order_item">
                    <span class="order_item-thumbnail"><?php echo $thumbnail; ?></span>
                    <span class="order_item-name"><?php echo esc_html( $item->get_name() ); ?></span>
                    <span class="order_item-quantity">&times; <?php echo esc_html( $item->get_quantity() ); ?></span>
                    <span class="order_item-total"><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

</div>
<?php do_action( 'woocommerce_view_order', $order->get_id() ); ?>
