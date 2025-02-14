<?php
defined('ABSPATH') || exit;
?>

<div class="woocommerce-lost-password-confirmation">
    <h2><?php esc_html_e('¡Revisa tu correo electrónico!', 'woocommerce'); ?></h2>
    <p><?php esc_html_e('Te hemos enviado un enlace para restablecer tu contraseña. Si no lo encuentras, revisa tu carpeta de spam.', 'woocommerce'); ?></p>
    
    <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="woocommerce-button button">
        <?php esc_html_e('Volver a Mi Cuenta', 'woocommerce'); ?>
    </a>
</div>
