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
        return '<p>' . esc_html__( 'Información Confidencial', 'woo-check' ) . '</p>';
    }

    $default_start = '2025-10-10';
    $raw_start     = isset( $_GET['start_date'] ) ? wp_unslash( $_GET['start_date'] ) : $default_start; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $start_date    = ( is_string( $raw_start ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw_start ) ) ? $raw_start : $default_start;

    $timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : null;

    if ( ! $timezone instanceof DateTimeZone ) {
        try {
            $timezone = new DateTimeZone( date_default_timezone_get() );
        } catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        }

        if ( ! $timezone instanceof DateTimeZone ) {
            $timezone = new DateTimeZone( 'UTC' );
        }
    }

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

    $sales_counts = class_exists( 'Woo_Check_Inventory' )
        ? Woo_Check_Inventory::get_sales_counts( $start_date, $end_date )
        : [];

    $rows      = [];
    $max_sales = 0;
    $max_stock = 0;

    foreach ( $products as $product ) {
        if ( ! $product instanceof WC_Product ) {
            continue;
        }

        $product_id = $product->get_id();
        $stock_raw  = $product->get_stock_quantity();
        $stock      = null !== $stock_raw ? (int) $stock_raw : null;

        $sales = isset( $sales_counts[ $product_id ] ) ? (int) $sales_counts[ $product_id ] : 0;

        $rows[] = [
            'name'  => $product->get_name(),
            'sales' => $sales,
            'stock' => $stock,
        ];

        $max_sales = max( $max_sales, $sales );

        if ( null !== $stock ) {
            $max_stock = max( $max_stock, $stock );
        }
    }

    $villegas_inventory_context = [
        'start_date'        => $start_date,
        'display_end_date'  => $display_end_date,
        'rows'              => $rows,
        'max_sales'         => $max_sales,
        'max_stock'         => $max_stock,
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
