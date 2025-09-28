<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action(
    'rest_api_init',
    static function () {
        register_rest_route(
            'woo-check/v1',
            '/shipit-webhook',
            [
                'methods'             => 'POST',
                'callback'            => 'wc_check_shipit_webhook_handler',
                'permission_callback' => '__return_true',
            ]
        );
    }
);

if ( ! function_exists( 'wc_check_shipit_webhook_handler' ) ) {
    function wc_check_shipit_webhook_handler( WP_REST_Request $request ) {
        $data = $request->get_json_params();

        error_log( 'Shipit Webhook: ' . wp_json_encode( $data ) );

        return rest_ensure_response(
            [
                'status' => 'ok',
            ]
        );
    }
}
