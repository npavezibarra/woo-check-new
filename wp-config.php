<?php
/**
 * WooCheck development configuration for WordPress debugging.
 *
 * These settings ensure that WooCommerce API interactions are logged to
 * wp-content/debug.log during development and testing.
 */
if ( ! defined( 'WP_DEBUG' ) ) {
    define( 'WP_DEBUG', true );
}

if ( ! defined( 'WP_DEBUG_LOG' ) ) {
    define( 'WP_DEBUG_LOG', true );
}

if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
    define( 'WP_DEBUG_DISPLAY', false );
}
