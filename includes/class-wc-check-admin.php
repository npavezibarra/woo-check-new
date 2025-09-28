<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Check_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'WooCheck Settings',
            'WooCheck',
            'manage_options',
            'woo-check-settings',
            [ $this, 'settings_page_html' ]
        );
    }

    public function register_settings() {
        register_setting( 'woo_check_settings', 'woo_check_recibelo_token' );
        register_setting( 'woo_check_settings', 'wc_check_shipit_email' );
        register_setting( 'woo_check_settings', 'wc_check_shipit_token' );

        add_settings_section(
            'woo_check_section',
            'API Tokens',
            null,
            'woo-check-settings'
        );

        add_settings_field(
            'woo_check_recibelo_token',
            'Recíbelo Token',
            [ $this, 'recibelo_token_field_html' ],
            'woo-check-settings',
            'woo_check_section'
        );

        add_settings_field(
            'wc_check_shipit_email',
            'Shipit Email',
            [ $this, 'shipit_email_field_html' ],
            'woo-check-settings',
            'woo_check_section'
        );

        add_settings_field(
            'wc_check_shipit_token',
            'Shipit Token',
            [ $this, 'shipit_token_field_html' ],
            'woo-check-settings',
            'woo_check_section'
        );

    }

    public function recibelo_token_field_html() {
        $value = esc_attr( get_option( 'woo_check_recibelo_token', '' ) );
        echo "<input type='text' name='woo_check_recibelo_token' value='$value' class='regular-text' />";
    }

    public function shipit_email_field_html() {
        $value = esc_attr( get_option( 'wc_check_shipit_email', get_option( 'woo_check_shipit_email', '' ) ) );
        echo "<input type='email' name='wc_check_shipit_email' value='$value' class='regular-text' />";
    }

    public function shipit_token_field_html() {
        $value = esc_attr( get_option( 'wc_check_shipit_token', get_option( 'woo_check_shipit_token', '' ) ) );
        echo "<input type='text' name='wc_check_shipit_token' value='$value' class='regular-text' />";
    }

    public function settings_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        echo '<div class="wrap"><h1>WooCheck Settings</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields( 'woo_check_settings' );
        do_settings_sections( 'woo-check-settings' );
        submit_button();
        echo '</form></div>';
    }
}

new WC_Check_Admin();
