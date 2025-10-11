<?php
/**
 * Inventory helper for WooCheck.
 *
 * @package Woo_Check
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides inventory aggregation helpers.
 */
class Woo_Check_Inventory {

    /**
     * Get sales counts for products between two dates, expanding pack products.
     *
     * @param string $start_date Inclusive start date in Y-m-d format (site timezone).
     * @param string $end_date   Inclusive end date in Y-m-d format (site timezone).
     *
     * @return array<int,int> Map of product ID to quantity sold.
     */
    public static function get_sales_counts( $start_date, $end_date ) {
        list( $start_utc, $end_utc ) = self::get_utc_range( $start_date, $end_date );

        if ( ! $start_utc || ! $end_utc ) {
            return [];
        }

        $order_items = self::query_order_items( $start_utc, $end_utc );

        if ( empty( $order_items ) ) {
            return [];
        }

        $pack_map = self::get_pack_map();
        $sales    = [];

        foreach ( $order_items as $row ) {
            $product_id = isset( $row['product_id'] ) ? (int) $row['product_id'] : 0;
            $quantity   = isset( $row['quantity'] ) ? (float) $row['quantity'] : 0.0;

            if ( $product_id <= 0 || abs( $quantity ) < 0.0001 ) {
                continue;
            }

            if ( isset( $pack_map[ $product_id ] ) ) {
                foreach ( $pack_map[ $product_id ] as $included_id ) {
                    $included_id = (int) $included_id;

                    if ( $included_id <= 0 ) {
                        continue;
                    }

                    $sales[ $included_id ] = ( $sales[ $included_id ] ?? 0 ) + $quantity;
                }
            } else {
                $sales[ $product_id ] = ( $sales[ $product_id ] ?? 0 ) + $quantity;
            }
        }

        // Cast totals to integers for presentation consistency.
        foreach ( $sales as $id => $total ) {
            $sales[ $id ] = (int) round( $total );
        }

        return $sales;
    }

    /**
     * Build the UTC datetime range from local dates.
     *
     * @param string $start_date Start date in site timezone.
     * @param string $end_date   End date in site timezone.
     *
     * @return array{string|null,string|null}
     */
    protected static function get_utc_range( $start_date, $end_date ) {
        $timezone = wp_timezone();

        try {
            $start = new DateTimeImmutable( $start_date . ' 00:00:00', $timezone );
            $end   = new DateTimeImmutable( $end_date . ' 00:00:00', $timezone );
        } catch ( Exception $e ) {
            return [ null, null ];
        }

        $start_utc = $start->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
        $end_utc   = $end->modify( '+1 day' )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );

        return [ $start_utc, $end_utc ];
    }

    /**
     * Query WooCommerce order items within a UTC range.
     *
     * @param string $start_utc Inclusive UTC start datetime (Y-m-d H:i:s).
     * @param string $end_utc   Exclusive UTC end datetime (Y-m-d H:i:s).
     *
     * @return array[]
     */
    protected static function query_order_items( $start_utc, $end_utc ) {
        global $wpdb;

        $sql = "
            SELECT
                pid.meta_value AS product_id,
                qty.meta_value AS quantity
            FROM {$wpdb->prefix}wc_orders o
            INNER JOIN {$wpdb->prefix}woocommerce_order_items oi
                ON o.id = oi.order_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta pid
                ON oi.order_item_id = pid.order_item_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta qty
                ON oi.order_item_id = qty.order_item_id
            WHERE oi.order_item_type = 'line_item'
              AND pid.meta_key = '_product_id'
              AND qty.meta_key = '_qty'
              AND o.status IN ('wc-processing', 'wc-completed')
              AND o.date_created_gmt >= %s
              AND o.date_created_gmt < %s
        ";

        $prepared = $wpdb->prepare( $sql, $start_utc, $end_utc );

        if ( false === $prepared ) {
            return [];
        }

        $results = $wpdb->get_results( $prepared, ARRAY_A );

        if ( ! is_array( $results ) ) {
            return [];
        }

        return $results;
    }

    /**
     * Retrieve the pack mapping table.
     *
     * @return array<int,array<int,int>>
     */
    protected static function get_pack_map() {
        static $pack_map = null;

        if ( null !== $pack_map ) {
            return $pack_map;
        }

        $file = plugin_dir_path( __FILE__ ) . 'inventory-map.php';

        if ( ! file_exists( $file ) ) {
            $pack_map = [];
            return $pack_map;
        }

        $data = include $file;

        if ( ! is_array( $data ) ) {
            $pack_map = [];
            return $pack_map;
        }

        $normalized = [];

        foreach ( $data as $pack_id => $book_ids ) {
            $pack_id = (int) $pack_id;

            if ( $pack_id <= 0 || ! is_array( $book_ids ) ) {
                continue;
            }

            $normalized[ $pack_id ] = [];

            foreach ( $book_ids as $book_id ) {
                $book_id = (int) $book_id;

                if ( $book_id > 0 ) {
                    $normalized[ $pack_id ][] = $book_id;
                }
            }
        }

        $pack_map = $normalized;

        return $pack_map;
    }
}
