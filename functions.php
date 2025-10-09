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

add_shortcode( 'villegas-packing-list', 'villegas_packing_list_shortcode' );

/**
 * Shortcode callback to display a paginated table of processing orders.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function villegas_packing_list_shortcode( $atts ) {
    if ( ! function_exists( 'wc_get_orders' ) ) {
        return '';
    }

    $atts = shortcode_atts(
        [
            'per_page' => 10,
        ],
        $atts,
        'villegas-packing-list'
    );

    $per_page = max( 1, (int) $atts['per_page'] );
    $page     = isset( $_GET['packing_page'] ) ? max( 1, (int) $_GET['packing_page'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $orders_query = wc_get_orders(
        [
            'status'   => 'processing',
            'orderby'  => 'date',
            'order'    => 'DESC',
            'limit'    => $per_page,
            'paged'    => $page,
            'paginate' => true,
            'return'   => 'objects',
        ]
    );

    $orders       = [];
    $total_orders = 0;
    $total_pages  = 1;

    if ( is_object( $orders_query ) && isset( $orders_query->orders ) ) {
        $orders       = $orders_query->orders;
        $total_orders = isset( $orders_query->total ) ? (int) $orders_query->total : count( $orders );
        $total_pages  = isset( $orders_query->max_num_pages ) ? (int) $orders_query->max_num_pages : max( 1, (int) ceil( $total_orders / $per_page ) );
    } elseif ( is_array( $orders_query ) ) {
        $orders       = $orders_query;
        $total_orders = count( $orders );
        $total_pages  = max( 1, (int) ceil( $total_orders / $per_page ) );
    }

    if ( empty( $orders ) ) {
        return '<p>' . esc_html__( 'There are no processing orders at the moment.', 'woo-check' ) . '</p>';
    }

    ob_start();

    static $packing_assets_printed = false;

    if ( ! $packing_assets_printed ) {
        $packing_assets_printed = true;
        ?>
        <style>
            .villegas-packing-list {
                border: 1px solid #ccc;
                border-collapse: collapse;
                width: 100%;
            }

            .villegas-packing-list th,
            .villegas-packing-list td {
                border: 1px solid #ccc;
                padding: 8px;
                vertical-align: top;
            }

            .villegas-packing-list tr.is-checked {
                background-color: #fff9c4;
            }
        </style>
        <script>
            ( function () {
                document.addEventListener( 'change', function ( event ) {
                    if ( ! event.target.matches( '.packing-checkbox' ) ) {
                        return;
                    }

                    var row = event.target.closest( 'tr' );

                    if ( ! row ) {
                        return;
                    }

                    if ( event.target.checked ) {
                        row.classList.add( 'is-checked' );
                    } else {
                        row.classList.remove( 'is-checked' );
                    }
                } );
            } )();
        </script>
        <?php
    }

    ?>
    <table class="villegas-packing-list">
        <thead>
            <tr>
                <th class="packing-select">
                    <span class="screen-reader-text"><?php esc_html_e( 'Select order', 'woo-check' ); ?></span>
                </th>
                <th><?php esc_html_e( 'Order ID', 'woo-check' ); ?></th>
                <th><?php esc_html_e( 'Items', 'woo-check' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $orders as $order ) : ?>
                <?php if ( ! $order instanceof WC_Order ) { continue; } ?>
                <tr>
                    <td>
                        <input
                            type="checkbox"
                            class="packing-checkbox"
                            data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
                            aria-label="<?php echo esc_attr( sprintf( __( 'Select order %d', 'woo-check' ), $order->get_id() ) ); ?>"
                        />
                    </td>
                    <td><?php echo esc_html( $order->get_id() ); ?></td>
                    <td>
                        <?php
                        $item_lines = [];

                        foreach ( $order->get_items() as $item ) {
                            $line = sprintf(
                                '%s - %s',
                                $item->get_name(),
                                wc_stock_amount( $item->get_quantity() )
                            );

                            $item_lines[] = esc_html( $line );
                        }

                        echo wp_kses_post( implode( '<br />', $item_lines ) );
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ( $total_pages > 1 ) : ?>
        <nav class="villegas-packing-pagination">
            <ul>
                <?php if ( $page > 1 ) : ?>
                    <li class="prev">
                        <a href="<?php echo esc_url( add_query_arg( 'packing_page', $page - 1 ) ); ?>"><?php esc_html_e( 'Previous', 'woo-check' ); ?></a>
                    </li>
                <?php endif; ?>
                <li class="current">
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: 1: current page number. 2: total pages. */
                            __( 'Page %1$d of %2$d', 'woo-check' ),
                            $page,
                            $total_pages
                        )
                    );
                    ?>
                </li>
                <?php if ( $page < $total_pages ) : ?>
                    <li class="next">
                        <a href="<?php echo esc_url( add_query_arg( 'packing_page', $page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'woo-check' ); ?></a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
    <?php

    return trim( ob_get_clean() );
}

