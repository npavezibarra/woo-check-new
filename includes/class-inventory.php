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
            $product_id      = isset( $row['product_id'] ) ? (int) $row['product_id'] : 0;
            $quantity        = isset( $row['quantity'] ) ? (float) $row['quantity'] : 0.0;
            $variation_id    = isset( $row['variation_id'] ) ? (int) $row['variation_id'] : 0;
            $order_item_name = isset( $row['order_item_name'] ) ? (string) $row['order_item_name'] : '';

            if ( $variation_id > 0 ) {
                $quantity = self::normalize_pack_librerias_quantity( $quantity, $variation_id, $order_item_name );
            }

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
        $timezone = self::resolve_timezone();

        if ( ! $timezone instanceof DateTimeZone ) {
            return [ null, null ];
        }

        try {
            $start = new DateTimeImmutable( $start_date . ' 00:00:00', $timezone );
            $end   = new DateTimeImmutable( $end_date . ' 00:00:00', $timezone );
        } catch ( Exception $e ) {
            return [ null, null ];
        }

        $utc_zone = new DateTimeZone( 'UTC' );

        $start_utc = $start->setTimezone( $utc_zone )->format( 'Y-m-d H:i:s' );
        $end_utc   = $end->modify( '+1 day' )->setTimezone( $utc_zone )->format( 'Y-m-d H:i:s' );

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
                qty.meta_value AS quantity,
                COALESCE( vid.meta_value, '0' ) AS variation_id,
                oi.order_item_name AS order_item_name
            FROM {$wpdb->prefix}wc_orders o
            INNER JOIN {$wpdb->prefix}woocommerce_order_items oi
                ON o.id = oi.order_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta pid
                ON oi.order_item_id = pid.order_item_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta qty
                ON oi.order_item_id = qty.order_item_id
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta vid
                ON oi.order_item_id = vid.order_item_id
               AND vid.meta_key = '_variation_id'
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
     * Convert pack quantities into the number of individual books sold.
     *
     * @param float  $quantity        Ordered quantity for the variation.
     * @param int    $variation_id    Variation identifier.
     * @param string $order_item_name Order item label as stored in WooCommerce.
     *
     * @return float Normalized quantity representing individual units.
     */
    public static function normalize_pack_librerias_quantity( $quantity, $variation_id, $order_item_name = '' ) {
        $quantity     = (float) $quantity;
        $variation_id = (int) $variation_id;

        if ( 0 === $variation_id || abs( $quantity ) < 0.0001 ) {
            return $quantity;
        }

        if ( '' !== $order_item_name && ! self::string_contains_pack_librerias( $order_item_name ) ) {
            return $quantity;
        }

        $pack_size = self::get_pack_librerias_pack_size( $variation_id );

        if ( $pack_size <= 1 ) {
            return $quantity;
        }

        return $quantity * $pack_size;
    }

    /**
     * Retrieve the mapped component product IDs for a pack product.
     *
     * @param int $pack_product_id Pack product identifier.
     *
     * @return array<int,int> Sanitized list of related product IDs.
     */
    public static function get_pack_component_product_ids( $pack_product_id ) {
        $pack_product_id = (int) $pack_product_id;

        if ( $pack_product_id <= 0 ) {
            return [];
        }

        $pack_map = self::get_pack_map();

        if ( empty( $pack_map[ $pack_product_id ] ) ) {
            return [];
        }

        $components = array_map( 'intval', (array) $pack_map[ $pack_product_id ] );

        return array_values(
            array_filter(
                $components,
                static function ( $value ) {
                    return (int) $value > 0;
                }
            )
        );
    }

    /**
     * Retrieve the configured pack size for a Pack Librerías variation.
     *
     * @param int $variation_id Variation identifier.
     *
     * @return int Number of individual units included in the pack.
     */
    public static function get_pack_librerias_pack_size( $variation_id ) {
        $variation_id = (int) $variation_id;

        if ( $variation_id <= 0 ) {
            return 1;
        }

        $map = self::get_pack_librerias_variation_map();

        return isset( $map[ $variation_id ] ) ? max( 1, (int) $map[ $variation_id ] ) : 1;
    }

    /**
     * Determine whether the provided label references a Pack Librerías product.
     *
     * @param string $value Raw label or product name.
     *
     * @return bool
     */
    protected static function string_contains_pack_librerias( $value ) {
        $value = trim( (string) $value );

        if ( '' === $value ) {
            return false;
        }

        if ( function_exists( 'remove_accents' ) ) {
            $value = remove_accents( $value );
        }

        return false !== stripos( $value, 'pack librerias' );
    }

    /**
     * Get the mapping of Pack Librerías variation IDs to their pack sizes.
     *
     * @return array<int,int>
     */
    protected static function get_pack_librerias_variation_map() {
        static $pack_sizes = null;

        if ( null !== $pack_sizes ) {
            return $pack_sizes;
        }

        $pack_sizes = [
            // Debut & Despedida.
            2934 => 8,
            2935 => 10,
            2936 => 12,
            2937 => 15,
            // La Torre de Papel.
            2942 => 8,
            2943 => 10,
            2944 => 12,
            2945 => 15,
            // Insurrección.
            5619 => 8,
            5620 => 10,
            5621 => 12,
            5622 => 15,
            // Para No Tirarse por la Ventana.
            9457 => 8,
            9458 => 10,
            9460 => 12,
            9459 => 15,
        ];

        return $pack_sizes;
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

    /**
     * Determine the most appropriate timezone for calculations.
     *
     * @return DateTimeZone|null
     */
    protected static function resolve_timezone() {
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
            $timezone = new DateTimeZone( date_default_timezone_get() );
        } catch ( Exception $e ) {
            $timezone = null;
        }

        if ( $timezone instanceof DateTimeZone ) {
            return $timezone;
        }

        return timezone_open( 'UTC' );
    }
}
