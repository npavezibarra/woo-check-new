<?php
/**
 * Checkout shipping information form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-shipping.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.6.0
 * @global WC_Checkout $checkout
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="woocommerce-shipping-fields">
	<?php if ( true === WC()->cart->needs_shipping_address() ) : ?>

		<h3 id="ship-to-different-address">
			<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
				<input id="ship-to-different-address-checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" <?php checked( apply_filters( 'woocommerce_ship_to_different_address_checked', 'shipping' === get_option( 'woocommerce_ship_to_destination' ) ? 1 : 0 ), 1 ); ?> type="checkbox" name="ship_to_different_address" value="1" /> <span><?php esc_html_e( 'Ship to a different address?', 'woocommerce' ); ?></span>
			</label>
		</h3>

                <div class="shipping_address shipping-modal-overlay" id="shipping-modal-overlay" aria-hidden="true">

                        <div class="shipping-modal" role="dialog" aria-modal="true" aria-labelledby="shipping-modal-title">
                                <div class="shipping-modal__header">
                                        <button type="button" class="shipping-modal__close" id="shipping-modal-close" aria-label="<?php esc_attr_e( 'Cerrar formulario de dirección de envío', 'woo-check' ); ?>">
                                                &times;
                                        </button>
                                        <h3 class="shipping-modal__title" id="shipping-modal-title"><?php esc_html_e( 'Dirección de envío', 'woo-check' ); ?></h3>
                                </div>

                                <div class="shipping-modal__content">
                                        <?php do_action( 'woocommerce_before_checkout_shipping_form', $checkout ); ?>

                                        <div class="woocommerce-shipping-fields__field-wrapper">
                                                <?php
                                                $fields = $checkout->get_checkout_fields( 'shipping' );

                                                foreach ( $fields as $key => $field ) {
                                                        woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
                                                }
                                                ?>
                                        </div>

                                        <?php do_action( 'woocommerce_after_checkout_shipping_form', $checkout ); ?>
                                </div>
                        </div>

                </div>

	<?php endif; ?>
</div>
<div class="woocommerce-additional-fields">
	<?php do_action( 'woocommerce_before_order_notes', $checkout ); ?>

	<?php if ( apply_filters( 'woocommerce_enable_order_notes_field', 'yes' === get_option( 'woocommerce_enable_order_comments', 'yes' ) ) ) : ?>

		<?php if ( ! WC()->cart->needs_shipping() || wc_ship_to_billing_address_only() ) : ?>

			<h3><?php esc_html_e( 'Additional information', 'woocommerce' ); ?></h3>

		<?php endif; ?>

		<div class="woocommerce-additional-fields__field-wrapper">
			<?php foreach ( $checkout->get_checkout_fields( 'order' ) as $key => $field ) : ?>
				<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
			<?php endforeach; ?>
		</div>

	<?php endif; ?>

	<?php do_action( 'woocommerce_after_order_notes', $checkout ); ?>
</div>
