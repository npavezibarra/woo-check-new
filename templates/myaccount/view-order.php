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
        <?php
        $order_datetime_display = '';

        if ( $order->get_date_created() ) {
            $order_datetime_display = $order->get_date_created()->date_i18n( 'd-m-Y H:i:s' );
        }

        $tracking_provider_raw = get_post_meta( $order->get_id(), '_tracking_provider', true );

        $tracking_provider_labels = array(
            'recibelo' => __( 'Recíbelo', 'woo-check' ),
            'shipit'   => __( 'Shipit', 'woo-check' ),
        );

        $tracking_provider_slug  = '';
        $tracking_provider_label = '';

        if ( is_string( $tracking_provider_raw ) ) {
            $tracking_provider_candidate = strtolower( trim( $tracking_provider_raw ) );

            if ( isset( $tracking_provider_labels[ $tracking_provider_candidate ] ) ) {
                $tracking_provider_slug  = $tracking_provider_candidate;
                $tracking_provider_label = $tracking_provider_labels[ $tracking_provider_candidate ];
            }
        }

        $recibelo_internal_id = trim( (string) get_post_meta( $order->get_id(), '_recibelo_internal_id', true ) );
        $shipit_tracking      = trim( (string) get_post_meta( $order->get_id(), '_shipit_tracking', true ) );
        $generic_tracking     = trim( (string) get_post_meta( $order->get_id(), '_tracking_number', true ) );

        $order_targets_recibelo = function_exists( 'wc_check_order_targets_recibelo' )
            ? wc_check_order_targets_recibelo( $order )
            : false;

        $order_identifier          = (string) $order->get_id();
        $shipit_fallback_reference = '' !== $order_identifier ? $order_identifier . 'N' : '';

        $tracking_message = __( 'We are checking the status of this shipment...', 'woo-check' );
        $tracking_number  = $generic_tracking;
        $default_tracking = $tracking_number;

        $uses_recibelo_context = (
            'recibelo' === $tracking_provider_slug
            || $order_targets_recibelo
            || '' !== $recibelo_internal_id
        );

        if ( $uses_recibelo_context ) {
            if ( '' !== $recibelo_internal_id ) {
                $tracking_number = $recibelo_internal_id;
            } else {
                if ( $tracking_number === $shipit_fallback_reference ) {
                    $tracking_number = '';
                }

                if ( '' === $tracking_number ) {
                    $tracking_number = $order_identifier;
                }
            }

            if ( 'recibelo' !== $tracking_provider_slug ) {
                $tracking_provider_slug  = 'recibelo';
                $tracking_provider_label = $tracking_provider_labels['recibelo'];
            }
        } else {
            if ( '' === $tracking_number ) {
                if ( '' !== $shipit_tracking ) {
                    $tracking_number = $shipit_tracking;
                } elseif ( 'shipit' === $tracking_provider_slug && '' !== $shipit_fallback_reference ) {
                    $tracking_number = $shipit_fallback_reference;
                }
            }
        }

        if ( '' !== $tracking_number ) {
            $tracking_number_display = $tracking_number;
        } elseif ( '' !== $tracking_provider_label ) {
            $tracking_number_display = $tracking_message;
        } else {
            $tracking_number_display = '';
        }

        $tracking_message_visible = ( '' === $tracking_number && '' === $tracking_provider_label );
        $tracking_message_style   = $tracking_message_visible ? '' : ' style="display:none;"';

        $tracking_courier_html = '';

        if ( '' !== $tracking_provider_label ) {
            if ( 'recibelo' === $tracking_provider_slug ) {
                $tracking_courier_html = sprintf(
                    '(<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>)',
                    esc_url( 'https://recibelo.cl/seguimiento' ),
                    esc_html( $tracking_provider_label )
                );
            } else {
                $tracking_courier_html = sprintf( '(%s)', esc_html( $tracking_provider_label ) );
            }
        }

        $tracking_status_attributes = sprintf(
            'data-order-id="%s"',
            esc_attr( $order->get_id() )
        );

        if ( '' !== $tracking_provider_slug ) {
            $tracking_status_attributes .= sprintf(
                ' data-tracking-provider="%s"',
                esc_attr( $tracking_provider_slug )
            );
        }

        if ( '' !== $tracking_provider_label ) {
            $tracking_status_attributes .= sprintf(
                ' data-tracking-provider-label="%s"',
                esc_attr( $tracking_provider_label )
            );
        }
        ?>
        <div id="order-header" class="order-header">
            <p class="titulo-seccion">
                <?php esc_html_e( 'Número de orden:', 'woo-check' ); ?> <?php echo esc_html( $order->get_id() ); ?>
            </p>
            <div id="tracking-status" <?php echo $tracking_status_attributes; ?>>
                <p class="tracking-heading">
                    <strong><?php esc_html_e( 'Tracking:', 'woo-check' ); ?></strong>
                    <span class="tracking-number"><?php echo esc_html( $tracking_number_display ); ?></span>
                    <?php if ( '' !== $tracking_courier_html ) : ?>
                        <span class="tracking-courier"><?php echo wp_kses_post( $tracking_courier_html ); ?></span>
                    <?php endif; ?>
                </p>
                <p class="tracking-message"<?php echo $tracking_message_style; ?>><?php echo esc_html( $tracking_message ); ?></p>
                <p class="tracking-link" style="display:none;"><a href="#" target="_blank" rel="noopener noreferrer"></a></p>
            </div>
            <?php if ( 'recibelo' === $tracking_provider_slug ) : ?>
                <?php
                $internal_id = get_post_meta( $order->get_id(), '_recibelo_internal_id', true );

                if ( empty( $internal_id ) ) {
                    $internal_id = $default_tracking;
                }

                $billing_full_name = $order->get_formatted_billing_full_name();
                $tracking_status   = class_exists( 'WC_Check_Recibelo' )
                    ? WC_Check_Recibelo::get_tracking_status( $internal_id, $billing_full_name )
                    : __( 'Estamos consultando el estado de este envío...', 'woo-check' );
                ?>
                <p class="recibelo-tracking-status"><?php echo esc_html( $tracking_status ); ?></p>
            <?php endif; ?>
            <?php if ( '' !== $order_datetime_display ) : ?>
                <p class="fecha-hora-orden"><?php printf( esc_html__( 'Fecha y hora de la orden: %s', 'woo-check' ), esc_html( $order_datetime_display ) ); ?></p>
            <?php endif; ?>
        </div>

        <p class="woocommerce-order-status">
            <strong><?php esc_html_e( 'Estado del pedido:', 'woo-check' ); ?></strong> <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
        </p>

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

        <p class="woocommerce-order-payment-method">
            <strong><?php esc_html_e( 'Payment method:', 'woo-check' ); ?></strong> <?php echo esc_html( $order->get_payment_method_title() ); ?>
        </p>
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
