<?php
/**
 * Inventory shortcode for Libro category products.
 *
 * @package Woo_Check
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_shortcode( 'villegas-inventario', 'villegas_render_inventory_page' );

if ( ! function_exists( 'villegas_inventory_resolve_timezone' ) ) {
    /**
     * Resolve the most suitable timezone for inventory calculations.
     *
     * @return DateTimeZone
     */
    function villegas_inventory_resolve_timezone() {
        if ( function_exists( 'wp_timezone' ) ) {
            $timezone = wp_timezone();

            if ( $timezone instanceof DateTimeZone ) {
                return $timezone;
            }
        }

        if ( function_exists( 'wp_timezone_string' ) ) {
            $timezone_string = wp_timezone_string();

            if ( $timezone_string ) {
                $timezone = @timezone_open( $timezone_string ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

                if ( $timezone instanceof DateTimeZone ) {
                    return $timezone;
                }
            }
        }

        try {
            return new DateTimeZone( date_default_timezone_get() );
        } catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        }

        return new DateTimeZone( 'UTC' );
    }
}

/**
 * Render the inventory page with sales and stock information.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function villegas_render_inventory_page( $atts = [] ) {
    if ( ! function_exists( 'wc_get_products' ) ) {
        return '';
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return function_exists( 'woo_check_render_confidential_message' )
            ? woo_check_render_confidential_message()
            : '<p>' . esc_html__( 'Información Confidencial', 'woo-check' ) . '</p>';
    }

    $default_start = '2025-10-10';
    $raw_start     = isset( $_GET['start_date'] ) ? wp_unslash( $_GET['start_date'] ) : $default_start; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $start_date    = ( is_string( $raw_start ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw_start ) ) ? $raw_start : $default_start;

    $timezone = villegas_inventory_resolve_timezone();

    try {
        $today = new DateTimeImmutable( 'now', $timezone );
    } catch ( Exception $e ) {
        $today = new DateTimeImmutable( 'now' );
    }

    $display_end_date = $today->format( 'Y-m-d' );
    $end_date         = $display_end_date;

    $products = wc_get_products(
        [
            'status'   => 'publish',
            'category' => [ 'libro' ],
            'limit'    => -1,
            'orderby'  => 'title',
            'order'    => 'ASC',
        ]
    );

    $sales_counts = [];
    $error_message = '';

    if ( class_exists( 'Woo_Check_Inventory' ) ) {
        try {
            $sales_counts = Woo_Check_Inventory::get_sales_counts( $start_date, $end_date );
        } catch ( Throwable $throwable ) {
            $error_message = esc_html__( 'No se pudo obtener la información de ventas en este momento.', 'woo-check' );
            error_log( sprintf( 'Woo_Check inventory error: %s', $throwable->getMessage() ) );
            $sales_counts = [];
        }
    }

    $allowed_sort_columns = [ 'sales', 'stock' ];
    $sort_column          = isset( $_GET['inventory_sort'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ? sanitize_key( wp_unslash( $_GET['inventory_sort'] ) )
        : '';

    if ( ! in_array( $sort_column, $allowed_sort_columns, true ) ) {
        $sort_column = '';
    }

    $sort_order = isset( $_GET['inventory_order'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ? strtolower( (string) wp_unslash( $_GET['inventory_order'] ) )
        : 'desc';

    if ( ! in_array( $sort_order, [ 'asc', 'desc' ], true ) ) {
        $sort_order = 'desc';
    }

    $rows      = [];
    $max_sales = 0;
    $max_stock = 0;
    $total_stock = 0;

    foreach ( $products as $product ) {
        if ( ! $product instanceof WC_Product ) {
            continue;
        }

        $product_id = $product->get_id();
        $stock_raw  = $product->get_stock_quantity();
        $stock      = null !== $stock_raw ? (int) $stock_raw : null;

        if ( null === $stock ) {
            $meta_stock = get_post_meta( $product_id, '_stock', true );

            if ( '' !== $meta_stock ) {
                $stock = (int) $meta_stock;
            }
        }

        $sales = isset( $sales_counts[ $product_id ] ) ? (int) $sales_counts[ $product_id ] : 0;

        $rows[] = [
            'name'  => $product->get_name(),
            'sales' => $sales,
            'stock' => $stock,
        ];

        $max_sales = max( $max_sales, $sales );

        if ( null !== $stock ) {
            $max_stock = max( $max_stock, $stock );
            $total_stock += max( 0, (int) $stock );
        }
    }

    if ( '' !== $sort_column ) {
        usort(
            $rows,
            static function ( $a, $b ) use ( $sort_column, $sort_order ) {
                $tie_break = false;
                $a_sales = isset( $a['sales'] ) ? (int) $a['sales'] : 0;
                $b_sales = isset( $b['sales'] ) ? (int) $b['sales'] : 0;

                $a_stock = array_key_exists( 'stock', $a ) ? $a['stock'] : null;
                $b_stock = array_key_exists( 'stock', $b ) ? $b['stock'] : null;

                if ( 'sales' === $sort_column ) {
                    $comparison = $a_sales <=> $b_sales;
                } else {
                    $a_has_stock = null !== $a_stock;
                    $b_has_stock = null !== $b_stock;

                    if ( ! $a_has_stock && ! $b_has_stock ) {
                        $comparison = 0;
                    } elseif ( ! $a_has_stock ) {
                        $comparison = 1;
                    } elseif ( ! $b_has_stock ) {
                        $comparison = -1;
                    } else {
                        $comparison = (int) $a_stock <=> (int) $b_stock;
                    }
                }

                if ( 0 === $comparison ) {
                    $tie_break = true;
                    $a_name = isset( $a['name'] ) ? (string) $a['name'] : '';
                    $b_name = isset( $b['name'] ) ? (string) $b['name'] : '';
                    $comparison = strcasecmp( $a_name, $b_name );
                }

                if ( ! $tie_break && 'desc' === $sort_order ) {
                    $comparison *= -1;
                }

                return $comparison;
            }
        );
    }

    $villegas_inventory_context = [
        'start_date'        => $start_date,
        'display_end_date'  => $display_end_date,
        'rows'              => $rows,
        'max_sales'         => $max_sales,
        'max_stock'         => $max_stock,
        'total_stock'       => $total_stock,
        'sort_column'       => $sort_column,
        'sort_order'        => $sort_order,
        'error_message'     => $error_message,
    ];

    $plugin_root      = dirname( dirname( __DIR__ ) );
    $inventory_view   = trailingslashit( $plugin_root ) . 'views/inventory.php';

    ob_start();
    ?>
    <div id="inventory-stats-page">
        <?php
        if ( file_exists( $inventory_view ) ) {
            include $inventory_view;
        } else {
            echo '<p>' . esc_html__( 'Inventory view template not found.', 'woo-check' ) . '</p>';
        }
        ?>
    </div>
    <?php
    return trim( ob_get_clean() );
}

/**
 * Enqueue shared inventory styles.
 */
function villegas_inventory_enqueue_assets() {
    if ( is_admin() ) {
        return;
    }

    $plugin_root = dirname( dirname( __DIR__ ) );
    $plugin_file = trailingslashit( $plugin_root ) . 'woo-check.php';
    $style_path  = trailingslashit( $plugin_root ) . 'assets/inventory.css';
    $style_url   = plugins_url( 'assets/inventory.css', $plugin_file );
    $version     = file_exists( $style_path ) ? (string) filemtime( $style_path ) : null;

    wp_enqueue_style( 'villegas-inventory', $style_url, [], $version );
}
add_action( 'wp_enqueue_scripts', 'villegas_inventory_enqueue_assets' );
