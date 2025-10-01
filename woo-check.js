jQuery(document).ready(function($) {
    // Change the label text for the City field in both billing and shipping sections
    $('label[for="billing_city"]').text('Región *');
    $('label[for="shipping_city"]').text('Región *');

    // Ensure the checkout page title has a consistent ID for styling/hooks
    $('body.woocommerce-checkout h1.alignwide.wp-block-post-title').attr('id', 'checkout-page-title');
});
