<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Check_Logistics {

    public function __construct() {
        add_action( 'woocommerce_new_order', [ $this, 'process_order' ], 20, 1 );
        add_action( 'woocommerce_order_status_processing', [ $this, 'maybe_send_on_processing' ], 20, 2 );
    }

    public function process_order( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $courier = $this->determine_courier( $order );

        if ( ! $courier ) {
            return;
        }

        if ( $this->has_sent_shipment( $order->get_id(), $courier ) ) {
            return;
        }

        $payment_method = $order->get_payment_method();

        if ( 'bacs' === $payment_method ) {
            $this->mark_pending( $order->get_id(), $courier );
            return;
        }

        if ( in_array( $payment_method, [ 'stripe', 'credit_card' ], true ) && $order->has_status( 'processing' ) ) {
            $this->send_to_courier( $courier, $order );
        }
    }

    public function maybe_send_on_processing( $order_id, $order = null ) {
        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order_id );
        }

        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $courier = $this->determine_courier( $order );

        if ( ! $courier ) {
            return;
        }

        if ( $this->has_sent_shipment( $order_id, $courier ) ) {
            return;
        }

        $payment_method = $order->get_payment_method();

        if ( 'bacs' === $payment_method ) {
            if ( 'yes' !== get_post_meta( $order_id, $this->get_pending_meta_key( $courier ), true ) ) {
                return;
            }
        } elseif ( ! in_array( $payment_method, [ 'stripe', 'credit_card' ], true ) ) {
            return;
        }

        if ( $order->has_status( 'processing' ) ) {
            $this->send_to_courier( $courier, $order );
        }
    }

    private function determine_courier( WC_Order $order ) {
        $region = strtoupper( (string) $order->get_shipping_state() );

        if ( 'RM' === $region ) {
            return class_exists( 'WC_Check_Recibelo' ) ? 'recibelo' : null;
        }

        return class_exists( 'WC_Check_Shipit' ) ? 'shipit' : null;
    }

    private function send_to_courier( $courier, WC_Order $order ) {
        switch ( $courier ) {
            case 'recibelo':
                $sent = WC_Check_Recibelo::create_shipment( $order );
                break;
            case 'shipit':
                $sent = WC_Check_Shipit::create_shipment( $order );
                break;
            default:
                $sent = false;
        }

        if ( $sent ) {
            $this->mark_sent( $order->get_id(), $courier );
            $this->clear_pending( $order->get_id(), $courier );
        }
    }

    private function has_sent_shipment( $order_id, $courier ) {
        return 'yes' === get_post_meta( $order_id, $this->get_sent_meta_key( $courier ), true );
    }

    private function mark_sent( $order_id, $courier ) {
        update_post_meta( $order_id, $this->get_sent_meta_key( $courier ), 'yes' );
    }

    private function mark_pending( $order_id, $courier ) {
        update_post_meta( $order_id, $this->get_pending_meta_key( $courier ), 'yes' );
    }

    private function clear_pending( $order_id, $courier ) {
        delete_post_meta( $order_id, $this->get_pending_meta_key( $courier ) );
    }

    private function get_sent_meta_key( $courier ) {
        return sprintf( '_wc_check_%s_shipment_sent', $courier );
    }

    private function get_pending_meta_key( $courier ) {
        return sprintf( '_wc_check_%s_shipment_pending', $courier );
    }
}

new WC_Check_Logistics();
