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
            'per_page' => 100,
        ],
        $atts,
        'villegas-packing-list'
    );

    $per_page = max( 1, (int) $atts['per_page'] );
    $page     = isset( $_GET['packing_page'] ) ? max( 1, (int) $_GET['packing_page'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $orders_args = [
        'status'   => 'processing',
        'orderby'  => 'date',
        'order'    => 'DESC',
        'limit'    => $per_page,
        'paged'    => $page,
        'paginate' => true,
        'return'   => 'objects',
    ];

    $orders_query = wc_get_orders( $orders_args );

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

    $determine_region_label = static function ( WC_Order $order ) {
        $region_name = '';

        if ( function_exists( 'wc_check_determine_commune_region_data' ) ) {
            $location = wc_check_determine_commune_region_data( $order );

            if ( ! empty( $location['region_name'] ) ) {
                $region_name = $location['region_name'];
            }
        }

        if ( '' === $region_name ) {
            $region_name = $order->get_shipping_state() ?: $order->get_billing_state();
        }

        return $region_name;
    };

    $normalize_region_name = static function ( $region_name ) {
        $region_name = (string) $region_name;

        if ( class_exists( 'WooCheck_Shipit_Validator' ) && method_exists( 'WooCheck_Shipit_Validator', 'normalize_commune' ) ) {
            return WooCheck_Shipit_Validator::normalize_commune( $region_name );
        }

        if ( function_exists( 'remove_accents' ) ) {
            $region_name = remove_accents( $region_name );
        }

        return strtoupper( trim( $region_name ) );
    };

    $is_metropolitana_order = static function ( WC_Order $order, $region_label ) use ( $normalize_region_name ) {
        $normalized_region = '' !== $region_label ? $normalize_region_name( $region_label ) : '';

        if ( '' !== $normalized_region && false !== strpos( $normalized_region, 'METROPOLITANA' ) ) {
            return true;
        }

        $shipping_state = strtoupper( (string) $order->get_shipping_state() );
        $billing_state  = strtoupper( (string) $order->get_billing_state() );

        $metropolitana_states = [ 'RM', 'CL-RM' ];

        return in_array( $shipping_state, $metropolitana_states, true ) || in_array( $billing_state, $metropolitana_states, true );
    };

    $summary_counts = [
        'new_orders_today'     => 0,
        'region_metropolitana' => 0,
        'other_regions'        => 0,
    ];

    $order_region_cache = [];

    $summary_orders = wc_get_orders(
        [
            'status' => 'processing',
            'limit'  => -1,
            'return' => 'objects',
        ]
    );

    if ( is_array( $summary_orders ) ) {
        $current_timestamp = current_time( 'timestamp' );
        $today_start_ts    = strtotime( 'today', $current_timestamp );
        $today_end_ts      = strtotime( 'tomorrow', $today_start_ts );

        if ( false === $today_start_ts ) {
            $today_start_ts = strtotime( 'today' );
        }

        if ( false === $today_start_ts ) {
            $today_start_ts = (int) $current_timestamp;
        }

        if ( false === $today_end_ts ) {
            $day_in_seconds = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
            $today_end_ts   = (int) $today_start_ts + $day_in_seconds;
        }

        foreach ( $summary_orders as $summary_order ) {
            if ( ! $summary_order instanceof WC_Order ) {
                continue;
            }

            $order_id                = $summary_order->get_id();
            $region_label            = $determine_region_label( $summary_order );
            $order_region_cache[ $order_id ] = $region_label;

            $date_created = $summary_order->get_date_created();
            $is_today     = false;

            if ( $date_created instanceof WC_DateTime ) {
                $order_timestamp = $date_created->getTimestamp();

                if ( $order_timestamp >= $today_start_ts && $order_timestamp < $today_end_ts ) {
                    $summary_counts['new_orders_today']++;
                    $is_today = true;
                }
            }

            if ( $is_today ) {
                if ( $is_metropolitana_order( $summary_order, $region_label ) ) {
                    $summary_counts['region_metropolitana']++;
                } else {
                    $summary_counts['other_regions']++;
                }
            }
        }
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

            .villegas-packing-toolbar {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                flex-wrap: wrap;
                gap: 12px;
                margin-bottom: 12px;
            }

            .villegas-packing-pagination {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 8px;
            }

            .villegas-packing-pagination__button {
                display: inline-block;
                padding: 6px 12px;
                border: 1px solid #ccc;
                border-radius: 4px;
                background: #f3f4f6;
                color: inherit;
                text-decoration: none;
                transition: background-color 0.2s ease;
                cursor: pointer;
            }

            .villegas-packing-pagination__button:hover,
            .villegas-packing-pagination__button:focus {
                background: #e5e7eb;
            }

            .villegas-packing-pagination__status {
                font-weight: 600;
            }

            #villegas-packing-summary {
                border: 1px solid #ccc;
                padding: 12px;
                margin-bottom: 12px;
                display: flex;
                flex-wrap: wrap;
                gap: 16px;
                background: #fff;
            }

            #villegas-packing-summary .villegas-packing-summary__item {
                display: flex;
                align-items: baseline;
                gap: 6px;
                font-size: 14px;
            }

            #villegas-packing-summary .villegas-packing-summary__label {
                font-weight: 600;
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

    $pagination_markup = '';

    if ( $total_pages > 1 ) {
        ob_start();
        ?>
        <nav class="villegas-packing-pagination" aria-label="<?php esc_attr_e( 'Packing list pagination', 'woo-check' ); ?>">
            <?php if ( $page > 1 ) : ?>
                <a class="villegas-packing-pagination__button" href="<?php echo esc_url( add_query_arg( 'packing_page', $page - 1 ) ); ?>">
                    <?php esc_html_e( 'Previous', 'woo-check' ); ?>
                </a>
            <?php endif; ?>
            <span class="villegas-packing-pagination__status">
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
            </span>
            <?php if ( $page < $total_pages ) : ?>
                <a class="villegas-packing-pagination__button" href="<?php echo esc_url( add_query_arg( 'packing_page', $page + 1 ) ); ?>">
                    <?php esc_html_e( 'Next', 'woo-check' ); ?>
                </a>
            <?php endif; ?>
        </nav>
        <?php
        $pagination_markup = ob_get_clean();
    }

    if ( $pagination_markup ) {
        ?>
        <div class="villegas-packing-toolbar">
            <?php echo $pagination_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <?php
    }

    ?>
    <div id="villegas-packing-summary">
        <div class="villegas-packing-summary__item">
            <span class="villegas-packing-summary__label"><?php esc_html_e( 'New Orders Today', 'woo-check' ); ?>:</span>
            <span class="villegas-packing-summary__value"><?php echo esc_html( number_format_i18n( $summary_counts['new_orders_today'] ) ); ?></span>
        </div>
        <div class="villegas-packing-summary__item">
            <span class="villegas-packing-summary__label"><?php esc_html_e( 'Region Metropolitana', 'woo-check' ); ?>:</span>
            <span class="villegas-packing-summary__value"><?php echo esc_html( number_format_i18n( $summary_counts['region_metropolitana'] ) ); ?></span>
        </div>
        <div class="villegas-packing-summary__item">
            <span class="villegas-packing-summary__label"><?php esc_html_e( 'Other Regions', 'woo-check' ); ?>:</span>
            <span class="villegas-packing-summary__value"><?php echo esc_html( number_format_i18n( $summary_counts['other_regions'] ) ); ?></span>
        </div>
    </div>

    <table class="villegas-packing-list">
        <thead>
            <tr>
                <th class="packing-select">
                    <span class="screen-reader-text"><?php esc_html_e( 'Select order', 'woo-check' ); ?></span>
                </th>
                <th><?php esc_html_e( 'Order ID', 'woo-check' ); ?></th>
                <th><?php esc_html_e( 'Items', 'woo-check' ); ?></th>
                <th><?php esc_html_e( 'Region', 'woo-check' ); ?></th>
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
                    <td>
                        <?php
                        $order_id    = $order->get_id();
                        $region_name = $order_region_cache[ $order_id ] ?? $determine_region_label( $order );

                        echo esc_html( $region_name );
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php

    return trim( ob_get_clean() );
}

