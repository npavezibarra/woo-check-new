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

$order_datetime_display = '';
if ( $order->get_date_created() ) {
    $order_datetime_display = $order->get_date_created()->date_i18n( 'd-m-Y H:i:s' );
}

$format_address_block = static function ( $type ) use ( $order ) {
    $lines       = array();
    $address_key = 'billing' === $type ? 'billing' : 'shipping';

    if ( 'billing' === $address_key ) {
        $first_name = $order->get_billing_first_name();
        $last_name  = $order->get_billing_last_name();
        $address_1  = $order->get_billing_address_1();
        $address_2  = $order->get_billing_address_2();
        $state      = $order->get_billing_state();
        $phone      = $order->get_billing_phone();
        $email      = $order->get_billing_email();
    } else {
        $first_name = $order->get_shipping_first_name();
        $last_name  = $order->get_shipping_last_name();
        $address_1  = $order->get_shipping_address_1();
        $address_2  = $order->get_shipping_address_2();
        $state      = $order->get_shipping_state();
        $phone      = $order->get_shipping_phone();
        $email      = '';
    }

    $comuna = '';

    if ( function_exists( 'woo_check_get_order_comuna_value' ) ) {
        $comuna = woo_check_get_order_comuna_value( $order, $address_key );
    }

    if ( '' === trim( (string) $comuna ) ) {
        $comuna = get_post_meta( $order->get_id(), sprintf( '%s_comuna', $address_key ), true );
    }

    if ( '' === trim( (string) $comuna ) ) {
        $comuna = 'billing' === $address_key
            ? $order->get_billing_city()
            : $order->get_shipping_city();
    }

    $full_name = trim( trim( (string) $first_name ) . ' ' . trim( (string) $last_name ) );

    if ( '' !== $full_name ) {
        $lines[] = esc_html( $full_name );
    }

    $address_line = trim( (string) $address_1 );

    if ( '' !== trim( (string) $address_2 ) ) {
        $address_line .= ', ' . trim( (string) $address_2 );
    }

    if ( '' !== $address_line ) {
        $lines[] = esc_html( $address_line );
    }

    if ( '' !== trim( (string) $comuna ) ) {
        $lines[] = esc_html( (string) $comuna );
    }

    if ( '' !== trim( (string) $state ) ) {
        $lines[] = esc_html( (string) $state );
    }

    if ( '' !== trim( (string) $phone ) ) {
        $lines[] = wc_make_phone_clickable( $phone );
    }

    if ( '' !== trim( (string) $email ) ) {
        $lines[] = sprintf(
            '<a href="mailto:%1$s">%2$s</a>',
            esc_attr( $email ),
            esc_html( $email )
        );
    }

    $lines = array_filter(
        $lines,
        static function ( $line ) {
            return '' !== $line && null !== $line;
        }
    );

    return implode( '<br>', $lines );
};

$billing_address_content  = $format_address_block( 'billing' );
$shipping_address_content = $format_address_block( 'shipping' );

if ( empty( $billing_address_content ) && ! empty( $shipping_address_content ) ) {
    $billing_address_content = $shipping_address_content;
}

if ( empty( $shipping_address_content ) && ! empty( $billing_address_content ) ) {
    $shipping_address_content = $billing_address_content;
}

$has_address_information = ! empty( $billing_address_content ) || ! empty( $shipping_address_content );

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

<div class="woocommerce-order-columns">

    <!-- Left Column -->
    <div class="woocommerce-order-column woocommerce-order-column--left">
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

        <?php if ( $has_address_information ) : ?>
            <div class="order-address-flip-card">
                <div id="order-address-flip-card" class="flip-card" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'Ver direcciones de facturación y envío', 'woocommerce' ); ?>" aria-pressed="false">
                    <div class="flip-card-inner">
                        <div class="flip-card-face flip-card-front">
                            <h4><?php esc_html_e( 'Dirección de Facturación', 'woocommerce' ); ?></h4>
                            <address><?php echo wp_kses_post( $billing_address_content ); ?></address>
                        </div>
                        <div class="flip-card-face flip-card-back">
                            <h4><?php esc_html_e( 'Dirección de Envío', 'woocommerce' ); ?></h4>
                            <address><?php echo wp_kses_post( $shipping_address_content ); ?></address>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <p class="woocommerce-order-status">
            <strong><?php esc_html_e( 'Estado del pedido:', 'woo-check' ); ?></strong> <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
        </p>

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

<?php if ( $has_address_information ) : ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var flipCard = document.getElementById('order-address-flip-card');
        if (!flipCard) {
            return;
        }

        var supportsHover = window.matchMedia('(hover: hover)').matches;

        var updatePressedState = function() {
            flipCard.setAttribute('aria-pressed', flipCard.classList.contains('is-flipped') ? 'true' : 'false');
        };

        updatePressedState();

        if (!supportsHover) {
            flipCard.addEventListener('click', function(event) {
                if (event.target.closest('a')) {
                    return;
                }

                event.preventDefault();
                flipCard.classList.toggle('is-flipped');
                updatePressedState();
            });
        }

        flipCard.addEventListener('keydown', function(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                flipCard.classList.toggle('is-flipped');
                updatePressedState();
            }
        });
    });
    </script>
<?php endif; ?>
