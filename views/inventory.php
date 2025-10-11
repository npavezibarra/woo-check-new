<?php
/**
 * Inventory view for Libro category products.
 *
 * @package Woo_Check
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$default_start = '2025-10-10';
$raw_start     = isset( $_GET['start_date'] ) ? wp_unslash( $_GET['start_date'] ) : '';

if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw_start ) ) {
    $start_date = $raw_start;
} else {
    $start_date = $default_start;
}

$timezone = wp_timezone();

$start_dt = DateTime::createFromFormat( 'Y-m-d H:i:s', $start_date . ' 00:00:00', $timezone );

if ( ! $start_dt ) {
    $start_dt = DateTime::createFromFormat( 'Y-m-d H:i:s', $default_start . ' 00:00:00', $timezone );
    $start_date = $default_start;
}

$today    = new DateTime( 'now', $timezone );
$end_date = $today->format( 'Y-m-d' );
$end_dt   = clone $today;
$end_dt->setTime( 23, 59, 59 );

if ( $start_dt > $end_dt ) {
    $start_dt  = DateTime::createFromFormat( 'Y-m-d H:i:s', $default_start . ' 00:00:00', $timezone );
    $start_date = $default_start;
}

$books = wc_get_products(
    [
        'status'   => 'publish',
        'category' => [ 'libro' ],
        'limit'    => -1,
        'orderby'  => 'title',
        'order'    => 'ASC',
    ]
);

$data      = [];
$max_sales = 0;
$max_stock = 0;

$start_bound = $start_dt->format( 'Y-m-d H:i:s' );
$end_bound   = $end_dt->format( 'Y-m-d H:i:s' );

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
            SELECT SUM( CAST( qty_meta.meta_value AS DECIMAL(18,2) ) )
            FROM {$wpdb->prefix}woocommerce_order_items AS order_items
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS qty_meta
                ON order_items.order_item_id = qty_meta.order_item_id
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS product_meta
                ON order_items.order_item_id = product_meta.order_item_id
                    AND product_meta.meta_key = '_product_id'
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS variation_meta
                ON order_items.order_item_id = variation_meta.order_item_id
                    AND variation_meta.meta_key = '_variation_id'
            INNER JOIN {$wpdb->posts} AS posts
                ON order_items.order_id = posts.ID
            WHERE qty_meta.meta_key = '_qty'
                AND posts.post_type = 'shop_order'
                AND posts.post_status IN ( 'wc-processing', 'wc-completed' )
                AND posts.post_date BETWEEN %s AND %s
                AND ( product_meta.meta_value = %d OR variation_meta.meta_value = %d )
            ",
            $start_bound,
            $end_bound,
            $book_id,
            $book_id
        )
    );

    $sales_value = $sales ? (int) $sales : 0;
    $stock_value = null !== $stock ? (int) $stock : null;

    $data[] = [
        'name'  => $book->get_name(),
        'sales' => $sales_value,
        'stock' => $stock_value,
    ];

    $max_sales = max( $max_sales, $sales_value );

    if ( null !== $stock_value ) {
        $max_stock = max( $max_stock, $stock_value );
    }
}

?>
<div class="inventory-container">
    <div class="inventory-header">
        <form method="get" class="inventory-filter-form">
            <label for="inventory-start-date"><?php esc_html_e( 'Start Date:', 'woo-check' ); ?></label>
            <input
                type="date"
                id="inventory-start-date"
                name="start_date"
                value="<?php echo esc_attr( $start_date ); ?>"
            />
            <button type="submit" class="button button-primary">
                <?php esc_html_e( 'Apply', 'woo-check' ); ?>
            </button>
            <span class="auto-end-note">
                <?php
                printf(
                    /* translators: %s: current date */
                    esc_html__( 'Counting until today: %s', 'woo-check' ),
                    esc_html( $end_date )
                );
                ?>
            </span>
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
        <?php if ( empty( $data ) ) : ?>
            <tr>
                <td colspan="3" class="inventory-empty">
                    <?php esc_html_e( 'No books found in the Libro category.', 'woo-check' ); ?>
                </td>
            </tr>
        <?php else : ?>
            <?php foreach ( $data as $row ) :
                $sales_percent = 0;
                $stock_percent = 0;

                if ( $max_sales > 0 ) {
                    $sales_percent = min( 100, ( $row['sales'] / $max_sales ) * 100 );
                    $sales_percent = round( $sales_percent, 2 );
                }

                if ( $max_stock > 0 && null !== $row['stock'] ) {
                    $stock_percent = min( 100, ( $row['stock'] / $max_stock ) * 100 );
                    $stock_percent = round( $stock_percent, 2 );
                }
            ?>
            <tr>
                <td class="book-name"><?php echo esc_html( $row['name'] ); ?></td>
                <td class="bar-cell">
                    <div class="bar-wrapper">
                        <span class="bar-label"><?php echo esc_html( number_format_i18n( $row['sales'] ) ); ?></span>
                        <div
                            class="bar-fill bar-fill--sales"
                            style="width: <?php echo esc_attr( $sales_percent ); ?>%;"
                            aria-hidden="true"
                        ></div>
                    </div>
                </td>
                <td class="bar-cell">
                    <div class="bar-wrapper">
                        <span class="bar-label">
                            <?php echo null !== $row['stock'] ? esc_html( number_format_i18n( $row['stock'] ) ) : esc_html__( 'N/A', 'woo-check' ); ?>
                        </span>
                        <?php if ( null !== $row['stock'] ) : ?>
                        <div
                            class="bar-fill bar-fill--stock"
                            style="width: <?php echo esc_attr( $stock_percent ); ?>%;"
                            aria-hidden="true"
                        ></div>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.inventory-container {
    width: 90%;
    max-width: 900px;
    margin: 30px auto;
    font-family: system-ui, sans-serif;
}

.inventory-header {
    text-align: left;
    margin-bottom: 20px;
}

.inventory-filter-form {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 14px;
}

.inventory-filter-form label {
    font-weight: 600;
    color: #222;
}

.inventory-filter-form input[type='date'] {
    padding: 6px 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
    color: #333;
    background-color: #fff;
}

.inventory-filter-form .button {
    padding: 6px 16px;
    font-size: 14px;
    height: auto;
    line-height: 1.4;
}

.auto-end-note {
    font-size: 13px;
    color: #666;
}

.inventory-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #ddd;
    font-size: 15px;
    background: #fff;
}

.inventory-table thead {
    background: #f6f6f6;
    border-bottom: 2px solid #ccc;
}

.inventory-table th {
    text-align: left;
    padding: 12px 14px;
    font-weight: 600;
    text-transform: uppercase;
    color: #222;
    border-bottom: 1px solid #ddd;
    letter-spacing: 0.04em;
}

.inventory-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #eee;
    color: #333;
}

.inventory-table tr:hover {
    background: #fafafa;
}

.inventory-empty {
    text-align: center;
    padding: 24px 16px;
    font-style: italic;
    color: #666;
}

.bar-cell {
    width: 40%;
}

.bar-wrapper {
    position: relative;
    height: 28px;
    border-radius: 4px;
    background: #f0f0f0;
    overflow: hidden;
    display: flex;
    align-items: center;
    padding-left: 12px;
}

.bar-label {
    font-size: 14px;
    font-weight: 600;
    color: #111;
    z-index: 1;
}

.bar-fill {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    border-radius: 4px;
    transition: width 0.4s ease;
}

.bar-fill--sales {
    background: #4e79a7;
}

.bar-fill--stock {
    background: #59a14f;
}
</style>
