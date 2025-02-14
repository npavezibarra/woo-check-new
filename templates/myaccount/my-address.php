<?php
/**
 * My Addresses
 * Template for WooCommerce My Account addresses section.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.3.0
 */

defined( 'ABSPATH' ) || exit;

$customer_id = get_current_user_id();

// Check if shipping is enabled and separate shipping addresses are used
if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) {
    $get_addresses = apply_filters(
        'woocommerce_my_account_get_addresses',
        array(
            'billing'  => __( 'Billing address', 'woocommerce' ),
            'shipping' => __( 'Shipping address', 'woocommerce' ),
        ),
        $customer_id
    );
} else {
    $get_addresses = apply_filters(
        'woocommerce_my_account_get_addresses',
        array(
            'billing' => __( 'Billing address', 'woocommerce' ),
        ),
        $customer_id
    );
}

$oldcol = 1;
$col    = 1;
?>

<p>
    <?php echo apply_filters( 'woocommerce_my_account_my_address_description', esc_html__( 'The following addresses will be used on the checkout page by default.', 'woocommerce' ) ); ?>
</p>

<?php if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) : ?>
    <div class="u-columns woocommerce-Addresses col2-set addresses">
<?php endif; ?>

<?php foreach ( $get_addresses as $name => $address_title ) : ?>
    <?php
        $col     = $col * -1;
        $oldcol  = $oldcol * -1;

        // Create a WC_Customer instance to fetch customer data
        $customer = new WC_Customer( $customer_id );

        $address_data = $name === 'billing'
            ? array(
                'first_name' => $customer->get_billing_first_name(),
                'last_name'  => $customer->get_billing_last_name(),
                'address_1'  => $customer->get_billing_address_1(),
                'address_2'  => $customer->get_billing_address_2(),
                'comuna'     => get_user_meta( $customer_id, 'billing_comuna', true ),
                'state'      => $customer->get_billing_state(),
                'phone'      => $customer->get_billing_phone(),
                'email'      => $customer->get_billing_email(),
            )
            : array(
                'first_name' => $customer->get_shipping_first_name(),
                'last_name'  => $customer->get_shipping_last_name(),
                'address_1'  => $customer->get_shipping_address_1(),
                'address_2'  => $customer->get_shipping_address_2(),
                'comuna'     => get_user_meta( $customer_id, 'shipping_comuna', true ),
                'state'      => $customer->get_shipping_state(),
                'phone'      => $customer->get_shipping_phone(),
            );
    ?>

    <div class="u-column<?php echo $col < 0 ? 1 : 2; ?> col-<?php echo $oldcol < 0 ? 1 : 2; ?> woocommerce-Address">
        <header class="woocommerce-Address-title title">
            <h2><?php echo esc_html( $address_title ); ?></h2>
            <a href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address', $name ) ); ?>" class="edit">
                <?php
                    printf(
                        /* translators: %s: Address title */
                        $address_data['address_1'] ? esc_html__( 'Edit %s', 'woocommerce' ) : esc_html__( 'Add %s', 'woocommerce' ),
                        esc_html( $address_title )
                    );
                ?>
            </a>
        </header>
        <address>
            <?php
            if ( ! empty( $address_data['first_name'] ) || ! empty( $address_data['last_name'] ) ) {
                echo esc_html( $address_data['first_name'] . ' ' . $address_data['last_name'] ) . '<br>';
            }
            if ( ! empty( $address_data['address_1'] ) ) {
                echo esc_html( $address_data['address_1'] ) . '<br>';
            }
            if ( ! empty( $address_data['address_2'] ) ) {
                echo esc_html( $address_data['address_2'] ) . '<br>';
            }
            if ( ! empty( $address_data['comuna'] ) ) {
                echo esc_html( $address_data['comuna'] ) . '<br>';
            }
            if ( ! empty( $address_data['state'] ) ) {
                echo esc_html( $address_data['state'] ) . '<br>';
            }
            if ( ! empty( $address_data['phone'] ) ) {
                echo wc_make_phone_clickable( $address_data['phone'] ) . '<br>';
            }
            if ( $name === 'billing' && ! empty( $address_data['email'] ) ) {
                echo esc_html( $address_data['email'] );
            } elseif ( empty( $address_data['first_name'] ) && empty( $address_data['address_1'] ) ) {
                echo esc_html__( 'You have not set up this type of address yet.', 'woocommerce' );
            }
            ?>
        </address>
    </div>

<?php endforeach; ?>

<?php if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) : ?>
    </div>
<?php endif; ?>