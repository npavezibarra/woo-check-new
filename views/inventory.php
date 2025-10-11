<?php
/**
 * Inventory view for Libro category products.
 *
 * @package Woo_Check
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$villegas_inventory_context = isset( $villegas_inventory_context ) && is_array( $villegas_inventory_context )
    ? $villegas_inventory_context
    : [];
$max_sales        = isset( $villegas_inventory_context['max_sales'] ) ? (int) $villegas_inventory_context['max_sales'] : 0;
$max_stock        = isset( $villegas_inventory_context['max_stock'] ) ? (int) $villegas_inventory_context['max_stock'] : 0;
$total_stock      = isset( $villegas_inventory_context['total_stock'] ) ? (int) $villegas_inventory_context['total_stock'] : 0;
$sort_column      = isset( $villegas_inventory_context['sort_column'] ) ? (string) $villegas_inventory_context['sort_column'] : '';
$sort_order       = isset( $villegas_inventory_context['sort_order'] ) ? (string) $villegas_inventory_context['sort_order'] : 'desc';

$start_date       = isset( $villegas_inventory_context['start_date'] ) ? (string) $villegas_inventory_context['start_date'] : '';
$display_end_date = isset( $villegas_inventory_context['display_end_date'] ) ? (string) $villegas_inventory_context['display_end_date'] : '';
$rows             = isset( $villegas_inventory_context['rows'] ) && is_array( $villegas_inventory_context['rows'] )
    ? $villegas_inventory_context['rows']
    : [];
$max_sales        = isset( $villegas_inventory_context['max_sales'] ) ? (int) $villegas_inventory_context['max_sales'] : 0;
$max_stock        = isset( $villegas_inventory_context['max_stock'] ) ? (int) $villegas_inventory_context['max_stock'] : 0;
?>
<div class="inventory-container">
    <div class="inventory-header">
        <form method="get" class="inventory-filter-form">
            <label for="start_date" class="inventory-filter-label">
                <?php esc_html_e( 'Start Date', 'woo-check' ); ?>
            </label>
            <input
                type="date"
                id="start_date"
                name="start_date"
                value="<?php echo esc_attr( $start_date ); ?>"
            />
            <button type="submit" class="inventory-filter-button button">
                <?php esc_html_e( 'Apply', 'woo-check' ); ?>
            </button>
            <?php if ( '' !== $display_end_date ) : ?>
            <span class="inventory-filter-note">
                <?php
                printf(
                    /* translators: %s: end date */
                    esc_html__( 'Counting until today: %s', 'woo-check' ),
                    esc_html( $display_end_date )
                );
                ?>
            </span>
            <?php endif; ?>
        </form>
    </div>
    <div class="inventory-summary">
        <div class="inventory-total-card">
            <span class="inventory-total-label"><?php esc_html_e( 'TOTAL BOOKS', 'woo-check' ); ?></span>
            <span class="inventory-total-value"><?php echo esc_html( number_format_i18n( $total_stock ) ); ?></span>
        </div>
    </div>
    <table class="inventory-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Libro', 'woo-check' ); ?></th>
                <th>
                    <a
                        href="<?php echo esc_url( add_query_arg( array_merge( $base_args, [
                            'inventory_sort'  => 'sales',
                            'inventory_order' => $sales_next_direction,
                        ] ), $base_url ) ); ?>"
                        class="inventory-sort-link<?php echo $sales_is_active ? ' is-active' : ''; ?>"
                    >
                        <?php esc_html_e( 'Vendidos', 'woo-check' ); ?><?php echo esc_html( $sales_label_suffix ); ?>
                    </a>
                </th>
                <th>
                    <a
                        href="<?php echo esc_url( add_query_arg( array_merge( $base_args, [
                            'inventory_sort'  => 'stock',
                            'inventory_order' => $stock_next_direction,
                        ] ), $base_url ) ); ?>"
                        class="inventory-sort-link<?php echo $stock_is_active ? ' is-active' : ''; ?>"
                    >
                        <?php esc_html_e( 'Stock actual', 'woo-check' ); ?><?php echo esc_html( $stock_label_suffix ); ?>
                    </a>
                </th>
            </tr>
        </thead>
        <tbody>
        <?php if ( empty( $rows ) ) : ?>
            <tr>
                <td colspan="3" class="inventory-empty">
                    <?php esc_html_e( 'No books found in the Libro category.', 'woo-check' ); ?>
                </td>
            </tr>
        <?php else : ?>
            <?php foreach ( $rows as $row ) :
                $book_name = isset( $row['name'] ) ? (string) $row['name'] : '';
                $sales     = isset( $row['sales'] ) ? (int) $row['sales'] : 0;
                $stock     = array_key_exists( 'stock', $row ) ? $row['stock'] : null;

                $sales_percent = 0;
                $stock_percent = 0;

                if ( $max_sales > 0 ) {
                    $sales_percent = min( 100, ( $sales / $max_sales ) * 100 );
                    $sales_percent = round( $sales_percent, 2 );
                }

                if ( $max_stock > 0 && null !== $stock ) {
                    $stock_percent = min( 100, ( $stock / $max_stock ) * 100 );
                    $stock_percent = round( $stock_percent, 2 );
                }
            ?>
            <tr>
                <td class="book-name"><?php echo esc_html( $book_name ); ?></td>
                <td class="bar-cell">
                    <div class="bar-wrapper">
                        <span class="bar-label"><?php echo esc_html( number_format_i18n( $sales ) ); ?></span>
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
                            <?php echo null !== $stock ? esc_html( number_format_i18n( (int) $stock ) ) : esc_html__( 'N/A', 'woo-check' ); ?>
                        </span>
                        <?php if ( null !== $stock ) : ?>
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
