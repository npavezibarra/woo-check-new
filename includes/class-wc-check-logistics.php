<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Check_Logistics {

    public function __construct() {
        add_action( 'woocommerce_new_order', [ $this, 'process_order' ], 20, 1 );
    }

    public function process_order( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $region = strtoupper( (string) $order->get_shipping_state() );

        if ( 'RM' === $region ) {
            if ( class_exists( 'WC_Check_Recibelo' ) ) {
                WC_Check_Recibelo::create_shipment( $order );
            }
            return;
        }

        if ( class_exists( 'WC_Check_Shipit' ) ) {
            WC_Check_Shipit::create_shipment( $order );
        }
    }
}

new WC_Check_Logistics();
