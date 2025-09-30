<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_ajax_woocheck_shipit_status', 'woocheck_shipit_status' );
add_action( 'wp_ajax_nopriv_woocheck_shipit_status', 'woocheck_shipit_status' );

/**
 * AJAX callback that renders the Shipit tracking widget contents.
 */
function woocheck_shipit_status() {
    $order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;

    if ( ! $order_id ) {
        wp_send_json_error( [ 'message' => __( 'Missing order_id', 'woo-check' ) ] );
    }

    $data = WC_Check_Shipit::get_tracking_status( $order_id );

    $tracking_number = isset( $data['tracking_number'] ) ? $data['tracking_number'] : '';
    $message         = isset( $data['message'] ) ? $data['message'] : '';
    $courier         = isset( $data['courier'] ) && '' !== trim( (string) $data['courier'] ) ? $data['courier'] : 'Shipit';
    $tracking_url    = isset( $data['tracking_url'] ) ? $data['tracking_url'] : '';

    ob_start();
    ?>
    <p><strong><?php printf( esc_html__( 'Tracking (%s):', 'woo-check' ), esc_html( $courier ) ); ?></strong> <?php echo esc_html( $tracking_number ); ?></p>
    <p class="tracking-message"><?php echo esc_html( $message ); ?></p>
    <?php if ( ! empty( $tracking_url ) ) : ?>
        <p><a href="<?php echo esc_url( $tracking_url ); ?>" target="_blank" rel="noopener noreferrer"><?php printf( esc_html__( 'Ver seguimiento en %s', 'woo-check' ), esc_html( $courier ) ); ?></a></p>
    <?php endif; ?>
    <?php
    $html = ob_get_clean();

    wp_send_json_success(
        [
            'html' => $html,
        ]
    );
}
