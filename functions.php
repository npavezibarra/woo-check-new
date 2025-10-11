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

    if ( ! current_user_can( 'manage_options' ) ) {
        return '<p>' . esc_html__( 'Información Confidencial', 'woo-check' ) . '</p>';
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

    $site_timezone = null;

    if ( function_exists( 'wp_timezone' ) ) {
        $site_timezone = wp_timezone();
    } elseif ( function_exists( 'wp_timezone_string' ) ) {
        $timezone_string = wp_timezone_string();

        if ( $timezone_string ) {
            $site_timezone = timezone_open( $timezone_string );
        }
    }

    if ( ! $site_timezone instanceof DateTimeZone ) {
        $fallback_timezone = timezone_open( date_default_timezone_get() );

        if ( $fallback_timezone instanceof DateTimeZone ) {
            $site_timezone = $fallback_timezone;
        } else {
            $site_timezone = new DateTimeZone( 'UTC' );
        }
    }

    $default_range_date = ( new DateTimeImmutable( 'now', $site_timezone ) )->format( 'Y-m-d' );

    $range_start_input = isset( $_GET['packing_start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['packing_start_date'] ) ) : $default_range_date; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $range_end_input   = isset( $_GET['packing_end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['packing_end_date'] ) ) : $default_range_date; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $range_start_obj = DateTimeImmutable::createFromFormat( 'Y-m-d', $range_start_input, $site_timezone );
    $range_end_obj   = DateTimeImmutable::createFromFormat( 'Y-m-d', $range_end_input, $site_timezone );

    if ( false === $range_start_obj ) {
        $range_start_obj = DateTimeImmutable::createFromFormat( 'Y-m-d', $default_range_date, $site_timezone );
    }

    if ( false === $range_end_obj ) {
        $range_end_obj = DateTimeImmutable::createFromFormat( 'Y-m-d', $default_range_date, $site_timezone );
    }

    if ( $range_end_obj < $range_start_obj ) {
        $tmp             = $range_start_obj;
        $range_start_obj = $range_end_obj;
        $range_end_obj   = $tmp;
    }

    $range_start_day = $range_start_obj->setTime( 0, 0, 0 );
    $range_end_day   = $range_end_obj->setTime( 23, 59, 59 );

    $is_single_day_range = $range_start_day->format( 'Y-m-d' ) === $range_end_day->format( 'Y-m-d' );

    $summary_counts = [
        'orders_in_range'      => 0,
        'region_metropolitana' => 0,
        'other_regions'        => 0,
    ];

    $undetermined_regions_in_range = 0;

    $hourly_region_counts = [
        'region_metropolitana' => array_fill( 0, 24, 0 ),
        'other_regions'        => array_fill( 0, 24, 0 ),
    ];

    $daily_region_counts = [
        'region_metropolitana' => [],
        'other_regions'        => [],
    ];

    if ( ! $is_single_day_range ) {
        $current_day = $range_start_day;

        while ( $current_day <= $range_end_day ) {
            $day_key                                  = $current_day->format( 'Y-m-d' );
            $daily_region_counts['region_metropolitana'][ $day_key ] = 0;
            $daily_region_counts['other_regions'][ $day_key ]        = 0;
            $current_day                              = $current_day->modify( '+1 day' );
        }
    }

    $current_query_args = [];

    if ( isset( $_GET ) && is_array( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        foreach ( $_GET as $key => $value ) {
            if ( in_array( $key, [ 'packing_start_date', 'packing_end_date' ], true ) ) {
                continue;
            }

            if ( is_scalar( $value ) ) {
                $current_query_args[ $key ] = (string) $value;
            }
        }
    }

    $order_region_cache = [];

    $summary_orders = wc_get_orders(
        [
            'status' => 'processing',
            'limit'  => -1,
            'return' => 'objects',
        ]
    );

    if ( is_array( $summary_orders ) ) {
        foreach ( $summary_orders as $summary_order ) {
            if ( ! $summary_order instanceof WC_Order ) {
                continue;
            }

            $order_id                = $summary_order->get_id();
            $region_label            = $determine_region_label( $summary_order );
            $order_region_cache[ $order_id ] = $region_label;

            $date_created    = $summary_order->get_date_created();
            $localized_date  = null;
            $is_in_range_day = false;

            if ( $date_created instanceof WC_DateTime ) {
                $localized_date = clone $date_created;
                $localized_date->setTimezone( $site_timezone );

                if ( $localized_date >= $range_start_day && $localized_date <= $range_end_day ) {
                    $summary_counts['orders_in_range']++;
                    $is_in_range_day = true;
                }
            }

            if ( $is_in_range_day ) {
                $order_hour = 0;

                if ( isset( $localized_date ) && $localized_date instanceof DateTimeInterface ) {
                    $order_hour = (int) $localized_date->format( 'G' );
                }

                $order_hour = max( 0, min( 23, $order_hour ) );

                if ( $is_metropolitana_order( $summary_order, $region_label ) ) {
                    $summary_counts['region_metropolitana']++;

                    if ( $is_single_day_range ) {
                        $hourly_region_counts['region_metropolitana'][ $order_hour ]++;
                    } else {
                        $day_key = $localized_date->format( 'Y-m-d' );

                        if ( isset( $daily_region_counts['region_metropolitana'][ $day_key ] ) ) {
                            $daily_region_counts['region_metropolitana'][ $day_key ]++;
                        }
                    }
                } else {
                    $summary_counts['other_regions']++;

                    if ( $is_single_day_range ) {
                        $hourly_region_counts['other_regions'][ $order_hour ]++;
                    } else {
                        $day_key = $localized_date->format( 'Y-m-d' );

                        if ( isset( $daily_region_counts['other_regions'][ $day_key ] ) ) {
                            $daily_region_counts['other_regions'][ $day_key ]++;
                        }
                    }
                }

                if ( '' === trim( (string) $region_label ) ) {
                    $undetermined_regions_in_range++;
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

            .villegas-packing-list tr.is-hidden {
                display: none;
            }

            .villegas-packing-toolbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
                margin-bottom: 12px;
            }

            .villegas-packing-toolbar .villegas-packing-pagination {
                margin-left: auto;
            }

            .packing-region-toggle {
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .packing-region-toggle__button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 6px 16px;
                border: 1px solid #d0d5dd;
                border-radius: 999px;
                background: #fff;
                color: #1f2937;
                cursor: pointer;
                font-weight: 600;
                line-height: 1;
                transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
            }

            .packing-region-toggle__button:hover,
            .packing-region-toggle__button:focus {
                background: #f8fafc;
            }

            .packing-region-toggle__button.is-active {
                background: #1f2937;
                border-color: #1f2937;
                color: #fff;
                box-shadow: 0 0 0 1px rgba(31, 41, 55, 0.15);
            }

            .packing-region-toggle__button[data-region-filter="rm"] {
                color: #ed1c24;
                border-color: #ed1c24;
            }

            .packing-region-toggle__button[data-region-filter="rm"]:hover,
            .packing-region-toggle__button[data-region-filter="rm"]:focus {
                background: rgba(237, 28, 36, 0.08);
            }

            .packing-region-toggle__button[data-region-filter="rm"].is-active {
                background: #ed1c24;
                border-color: #ed1c24;
                color: #fff;
                box-shadow: 0 0 0 1px rgba(237, 28, 36, 0.15);
            }

            .packing-region-toggle__button[data-region-filter="non-rm"] {
                color: #1e90ff;
                border-color: #1e90ff;
            }

            .packing-region-toggle__button[data-region-filter="non-rm"]:hover,
            .packing-region-toggle__button[data-region-filter="non-rm"]:focus {
                background: rgba(30, 144, 255, 0.08);
            }

            .packing-region-toggle__button[data-region-filter="non-rm"].is-active {
                background: #1e90ff;
                border-color: #1e90ff;
                color: #fff;
                box-shadow: 0 0 0 1px rgba(30, 144, 255, 0.15);
            }

            #packing-stats {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                margin-bottom: 12px;
            }

            #packing-stats .packing-stats__widget {
                flex: 1 1 calc((100% - 40px) / 3);
                min-width: 220px;
                border: 1px solid #000;
                border-radius: 6px;
                background: #fff;
                padding: 16px;
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .packing-stats__widget-title {
                font-weight: 700;
                font-size: 16px;
                margin: 0;
            }

            .packing-stats__stat {
                display: flex;
                align-items: baseline;
                justify-content: space-between;
                gap: 8px;
                font-size: 20px;
            }

            .packing-stats__stat-label {
                font-weight: 600;
            }

            #villegas-packing-overview .packing-stats__header {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 16px;
            }

            #villegas-packing-overview .packing-stats__controls {
                margin-left: auto;
                display: flex;
                align-items: flex-end;
                gap: 12px;
                flex-wrap: wrap;
            }

            #villegas-packing-overview .packing-stats__control {
                display: flex;
                flex-direction: column;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                color: #4b5563;
                gap: 4px;
            }

            #villegas-packing-overview .packing-stats__control input[type="date"] {
                padding: 6px 8px;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                font-size: 13px;
                color: #111827;
            }

            #villegas-packing-overview .packing-stats__apply-button {
                padding: 7px 14px;
                border: 1px solid #2563eb;
                border-radius: 4px;
                background-color: #2563eb;
                color: #ffffff;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                transition: background-color 0.2s ease, border-color 0.2s ease;
            }

            #villegas-packing-overview .packing-stats__apply-button:hover,
            #villegas-packing-overview .packing-stats__apply-button:focus {
                background-color: #1d4ed8;
                border-color: #1d4ed8;
            }

            #villegas-packing-overview .packing-stats__header + .packing-stats__metrics {
                margin-top: 12px;
            }

            @media (max-width: 600px) {
                #villegas-packing-overview .packing-stats__controls {
                    margin-left: 0;
                }
            }

            #villegas-packing-overview .packing-stats__metrics {
                display: flex;
                flex-direction: column;
                gap: 0;
                max-width: 247px;
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

            #villegas-packing-overview .packing-stats__chart {
                position: relative;
                width: 100%;
                height: 300px;
                max-height: 300px;
            }

            #villegas-packing-overview .packing-stats__chart canvas {
                width: 100% !important;
                height: 100% !important;
            }
        </style>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
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

                var applyRegionFilter = function ( button ) {
                    if ( ! button ) {
                        return;
                    }

                    var toolbar = button.closest( '.villegas-packing-toolbar' );

                    if ( ! toolbar ) {
                        return;
                    }

                    var filter = button.getAttribute( 'data-region-filter' );
                    var buttons = toolbar.querySelectorAll( '.packing-region-toggle__button' );

                    buttons.forEach( function ( toggleButton ) {
                        var isActive = toggleButton === button;
                        toggleButton.classList.toggle( 'is-active', isActive );
                        toggleButton.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
                    } );

                    var container = toolbar.parentElement;
                    var table = container ? container.querySelector( '.villegas-packing-list' ) : null;

                    if ( ! table ) {
                        return;
                    }

                    var rows = table.querySelectorAll( 'tbody tr' );

                    rows.forEach( function ( row ) {
                        var regionGroup = row.getAttribute( 'data-region-group' );
                        var shouldShow = 'all' === filter || filter === regionGroup;

                        row.classList.toggle( 'is-hidden', ! shouldShow );
                    } );
                };

                document.addEventListener( 'click', function ( event ) {
                    var button = event.target.closest( '.packing-region-toggle__button' );

                    if ( ! button ) {
                        return;
                    }

                    event.preventDefault();
                    applyRegionFilter( button );
                } );

                document.querySelectorAll( '.packing-region-toggle__button.is-active' ).forEach( function ( button ) {
                    applyRegionFilter( button );
                } );
            } )();
        </script>
        <?php
    }

    ?>
    <div id="packing-stats">
        <?php
        $range_display_format = function_exists( 'get_option' ) ? (string) get_option( 'date_format', 'M j, Y' ) : 'M j, Y';

        $villegas_overview_chart_payload = [
            'labels'       => [],
            'rm'           => [],
            'not_rm'       => [],
            'mode'         => $is_single_day_range ? 'hourly' : 'daily',
            'x_axis_label' => $is_single_day_range
                ? __( 'Hour of Day (24hr Clock)', 'woo-check' )
                : __( 'Order Date', 'woo-check' ),
        ];

        if ( $is_single_day_range ) {
            $villegas_overview_chart_payload['labels'] = array_map(
                static function ( $hour ) {
                    return sprintf( '%02d:00', $hour );
                },
                range( 0, 23 )
            );

            $villegas_overview_chart_payload['rm']     = array_map( 'intval', $hourly_region_counts['region_metropolitana'] );
            $villegas_overview_chart_payload['not_rm'] = array_map( 'intval', $hourly_region_counts['other_regions'] );
        } else {
            $chart_day_keys = array_keys( $daily_region_counts['region_metropolitana'] );

            $villegas_overview_chart_payload['labels'] = array_map(
                static function ( $date_str ) use ( $range_display_format, $site_timezone ) {
                    $date_obj = DateTimeImmutable::createFromFormat( 'Y-m-d', $date_str, $site_timezone );

                    if ( $date_obj instanceof DateTimeImmutable ) {
                        return $date_obj->format( $range_display_format );
                    }

                    return $date_str;
                },
                $chart_day_keys
            );

            $villegas_overview_chart_payload['rm']     = array_map( 'intval', array_values( $daily_region_counts['region_metropolitana'] ) );
            $villegas_overview_chart_payload['not_rm'] = array_map( 'intval', array_values( $daily_region_counts['other_regions'] ) );
        }

        $chart_aria_label   = $is_single_day_range
            ? __( 'Stacked hourly orders by region', 'woo-check' )
            : __( 'Stacked daily orders by region', 'woo-check' );
        ?>
        <div id="villegas-packing-overview" class="packing-stats__widget">
            <div class="packing-stats__header">
                <p class="packing-stats__widget-title"><?php esc_html_e( 'Processing Overview', 'woo-check' ); ?></p>
                <form method="get" class="packing-stats__controls">
                    <?php foreach ( $current_query_args as $query_key => $query_value ) : ?>
                        <input type="hidden" name="<?php echo esc_attr( $query_key ); ?>" value="<?php echo esc_attr( $query_value ); ?>" />
                    <?php endforeach; ?>
                    <label class="packing-stats__control">
                        <span><?php esc_html_e( 'Start date', 'woo-check' ); ?></span>
                        <input
                            type="date"
                            name="packing_start_date"
                            value="<?php echo esc_attr( $range_start_obj->format( 'Y-m-d' ) ); ?>"
                            max="<?php echo esc_attr( $range_end_obj->format( 'Y-m-d' ) ); ?>"
                        />
                    </label>
                    <label class="packing-stats__control">
                        <span><?php esc_html_e( 'End date', 'woo-check' ); ?></span>
                        <input
                            type="date"
                            name="packing_end_date"
                            value="<?php echo esc_attr( $range_end_obj->format( 'Y-m-d' ) ); ?>"
                            min="<?php echo esc_attr( $range_start_obj->format( 'Y-m-d' ) ); ?>"
                        />
                    </label>
                    <button type="submit" class="packing-stats__apply-button"><?php esc_html_e( 'Apply', 'woo-check' ); ?></button>
                </form>
            </div>
            <div class="packing-stats__metrics">
                <div class="packing-stats__stat">
                    <span class="packing-stats__stat-label"><?php esc_html_e( 'Total Orders', 'woo-check' ); ?>:</span>
                    <span class="packing-stats__stat-value"><?php echo esc_html( number_format_i18n( $summary_counts['orders_in_range'] ) ); ?></span>
                </div>
                <div class="packing-stats__stat">
                    <span class="packing-stats__stat-label"><?php esc_html_e( 'RECIBELO', 'woo-check' ); ?>:</span>
                    <span class="packing-stats__stat-value"><?php echo esc_html( number_format_i18n( $summary_counts['region_metropolitana'] ) ); ?></span>
                </div>
                <div class="packing-stats__stat">
                    <span class="packing-stats__stat-label"><?php esc_html_e( 'SHIPIT', 'woo-check' ); ?>:</span>
                    <span class="packing-stats__stat-value"><?php echo esc_html( number_format_i18n( $summary_counts['other_regions'] ) ); ?></span>
                </div>
                <?php if ( $undetermined_regions_in_range > 0 ) : ?>
                    <div class="packing-stats__stat">
                        <span class="packing-stats__stat-label"><?php esc_html_e( 'Unassigned Region Orders', 'woo-check' ); ?>:</span>
                        <span class="packing-stats__stat-value"><?php echo esc_html( number_format_i18n( $undetermined_regions_in_range ) ); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="packing-stats__chart">
                <canvas id="villegasPackingOverviewChart" role="img" aria-label="<?php echo esc_attr( $chart_aria_label ); ?>"></canvas>
            </div>
        </div>
    </div>

    <script>
        ( function () {
            if ( 'undefined' === typeof Chart ) {
                return;
            }

            var chartCanvas = document.getElementById( 'villegasPackingOverviewChart' );

            if ( ! chartCanvas || chartCanvas.dataset.chartRendered ) {
                return;
            }

            chartCanvas.dataset.chartRendered = '1';

            var chartData = <?php echo wp_json_encode( $villegas_overview_chart_payload ); ?>;

            var datasets = [
                {
                    label: '<?php echo esc_js( __( 'RECIBELO Orders', 'woo-check' ) ); ?>',
                    data: chartData.rm,
                    backgroundColor: 'rgba(237, 28, 36, 0.85)',
                    borderColor: 'rgba(237, 28, 36, 1)',
                    borderWidth: 1,
                    borderRadius: 3,
                    borderSkipped: false,
                    stack: 'orders',
                },
                {
                    label: '<?php echo esc_js( __( 'SHIPIT Orders', 'woo-check' ) ); ?>',
                    data: chartData.not_rm,
                    backgroundColor: 'rgba(30, 144, 255, 0.85)',
                    borderColor: 'rgba(30, 144, 255, 1)',
                    borderWidth: 1,
                    borderRadius: 3,
                    borderSkipped: false,
                    stack: 'orders',
                }
            ];

            var yAxisMax = null;

            if ( chartData.mode === 'hourly' ) {
                var dataLength = Math.max(
                    Array.isArray( chartData.rm ) ? chartData.rm.length : 0,
                    Array.isArray( chartData.not_rm ) ? chartData.not_rm.length : 0
                );

                var highestTotal = 0;

                for ( var i = 0; i < dataLength; i++ ) {
                    var rmValue = Array.isArray( chartData.rm ) ? Number( chartData.rm[ i ] || 0 ) : 0;
                    var notRmValue = Array.isArray( chartData.not_rm ) ? Number( chartData.not_rm[ i ] || 0 ) : 0;

                    highestTotal = Math.max( highestTotal, rmValue + notRmValue );
                }

                yAxisMax = highestTotal > 20 ? highestTotal : 20;
            }

            var config = {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: datasets,
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                            },
                        },
                        tooltip: {
                            callbacks: {
                                footer: function ( tooltipItems ) {
                                    var total = tooltipItems.reduce( function ( sum, item ) {
                                        return sum + ( item.parsed.y || 0 );
                                    }, 0 );

                                    return '<?php echo esc_js( __( 'Total Orders:', 'woo-check' ) ); ?> ' + total;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                            title: {
                                display: true,
                                text: chartData.x_axis_label,
                                font: {
                                    weight: 'bold'
                                }
                            },
                            grid: {
                                display: false,
                            },
                            ticks: {
                                maxRotation: chartData.mode === 'daily' ? 45 : 0,
                                minRotation: 0,
                            }
                        },
                        y: ( function () {
                            var options = {
                                stacked: true,
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: '<?php echo esc_js( __( 'Orders (Units)', 'woo-check' ) ); ?>',
                                    font: {
                                        weight: 'bold'
                                    }
                                }
                            };

                            if ( yAxisMax !== null ) {
                                options.max = yAxisMax;
                            }

                            return options;
                        }() )
                    }
                }
            };

            new Chart( chartCanvas.getContext( '2d' ), config );
        } )();
    </script>

    <?php
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

    ?>
    <div class="villegas-packing-toolbar">
        <div class="packing-region-toggle" role="group" aria-label="<?php esc_attr_e( 'Filter orders by region', 'woo-check' ); ?>">
            <button type="button" class="packing-region-toggle__button is-active" data-region-filter="all" aria-pressed="true">
                <?php echo esc_html_x( 'ALL', 'Filter region option for all orders', 'woo-check' ); ?>
            </button>
            <button type="button" class="packing-region-toggle__button" data-region-filter="rm" aria-pressed="false">
                <?php echo esc_html_x( 'RECIBELO', 'Filter region option for Región Metropolitana orders', 'woo-check' ); ?>
            </button>
            <button type="button" class="packing-region-toggle__button" data-region-filter="non-rm" aria-pressed="false">
                <?php echo esc_html_x( 'SHIPIT', 'Filter region option for non Región Metropolitana orders', 'woo-check' ); ?>
            </button>
        </div>
        <?php echo $pagination_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
    <?php

    ?>
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
                <?php
                $order_id    = $order->get_id();
                $region_name = $order_region_cache[ $order_id ] ?? $determine_region_label( $order );
                $region_type = $is_metropolitana_order( $order, $region_name ) ? 'rm' : 'non-rm';
                ?>
                <tr data-region-group="<?php echo esc_attr( $region_type ); ?>">
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
                        <?php echo esc_html( $region_name ); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php

    return trim( ob_get_clean() );
}

