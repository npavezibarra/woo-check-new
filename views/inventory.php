<?php
/**
 * Inventory view for Libro category products.
 *
 * @package Woo_Check
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_time    = current_time( 'timestamp' );
$default_start   = wp_date( 'Y-m-d', $current_time - WEEK_IN_SECONDS );
$default_end     = wp_date( 'Y-m-d', $current_time );
$raw_start_date  = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : $default_start;
$raw_end_date    = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : $default_end;
$start_date      = $raw_start_date;
$end_date        = $raw_end_date;

// Ensure valid dates in Y-m-d format.
if ( ! wp_checkdate( substr( $start_date, 5, 2 ), substr( $start_date, 8, 2 ), substr( $start_date, 0, 4 ), $start_date ) ) {
    $start_date = $default_start;
}

if ( ! wp_checkdate( substr( $end_date, 5, 2 ), substr( $end_date, 8, 2 ), substr( $end_date, 0, 4 ), $end_date ) ) {
    $end_date = $default_end;
}

$start_datetime = $start_date . ' 00:00:00';
$end_datetime   = $end_date . ' 23:59:59';

$books = wc_get_products(
    [
        'status'   => 'publish',
        'category' => [ 'libro' ],
        'limit'    => -1,
        'orderby'  => 'title',
        'order'    => 'ASC',
    ]
);

?>
<div class="inventory-header">
    <form method="get">
        <label>
            <?php esc_html_e( 'Start Date:', 'woo-check' ); ?>
            <input type="date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>">
        </label>
        <label>
            <?php esc_html_e( 'End Date:', 'woo-check' ); ?>
            <input type="date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>">
        </label>
        <button type="submit" class="button">
            <?php esc_html_e( 'Apply', 'woo-check' ); ?>
        </button>
    </form>
</div>
<table class="inventory-table">
    <thead>
        <tr>
            <th><?php esc_html_e( 'Libro', 'woo-check' ); ?></th>
            <th><?php esc_html_e( 'Vendidos', 'woo-check' ); ?></th>
            <th><?php esc_html_e( 'Stock actual', 'woo-check' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        global $wpdb;

        foreach ( $books as $book ) {
            if ( ! $book instanceof WC_Product ) {
                continue;
            }

            $book_id = $book->get_id();
            $stock   = $book->get_stock_quantity();

            $sales = $wpdb->get_var(
                $wpdb->prepare(
                    "
                    SELECT SUM( CAST( qty_meta.meta_value AS UNSIGNED ) )
                    FROM {$wpdb->prefix}woocommerce_order_items AS order_items
                    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS product_meta
                        ON order_items.order_item_id = product_meta.order_item_id
                    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS qty_meta
                        ON order_items.order_item_id = qty_meta.order_item_id
                    INNER JOIN {$wpdb->posts} AS posts
                        ON order_items.order_id = posts.ID
                    WHERE product_meta.meta_key = '_product_id'
                        AND product_meta.meta_value = %d
                        AND qty_meta.meta_key = '_qty'
                        AND posts.post_type = 'shop_order'
                        AND posts.post_status IN ( 'wc-processing', 'wc-completed' )
                        AND posts.post_date BETWEEN %s AND %s
                    ",
                    $book_id,
                    $start_datetime,
                    $end_datetime
                )
            );

            $sales = $sales ? (int) $sales : 0;
            ?>
            <tr>
                <td><?php echo esc_html( $book->get_name() ); ?></td>
                <td><?php echo esc_html( $sales ); ?></td>
                <td><?php echo esc_html( null !== $stock ? (string) $stock : __( 'N/A', 'woo-check' ) ); ?></td>
            </tr>
            <?php
        }
        ?>
    </tbody>
</table>
