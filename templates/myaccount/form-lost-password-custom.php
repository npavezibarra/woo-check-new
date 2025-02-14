<?php
defined('ABSPATH') || exit;

// Check if there's a reset password error
$error_message = isset($_GET['reset_error']) ? sanitize_text_field($_GET['reset_error']) : '';
$success_message = isset($_GET['reset_success']) ? sanitize_text_field($_GET['reset_success']) : '';

?>

<div class="woocommerce-form-lost-password-custom">
    <h2><?php esc_html_e('¿Olvidaste tu contraseña?', 'woocommerce'); ?></h2>

    <?php if ($error_message): ?>
        <div class="woocommerce-error"><?php echo esc_html($error_message); ?></div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="woocommerce-message"><?php echo esc_html($success_message); ?></div>
    <?php endif; ?>

    <p id="forgot-aviso"><?php esc_html_e('Ingresa tu correo electrónico o nombre de usuario para restablecer tu contraseña.', 'woocommerce'); ?></p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="woocommerce-ResetPassword lost_reset_password">

        <p class="woocommerce-form-row" id="elements-form-password">
            <label for="user_login"><?php esc_html_e('Correo electrónico o nombre de usuario', 'woocommerce'); ?> *</label>
            <input type="text" name="user_login" id="user_login" autocomplete="username" required />
        </p>

        <?php wp_nonce_field('lost_password', 'woocommerce-lost-password-nonce'); ?>

        <input type="hidden" name="action" value="custom_process_lost_password"> <!-- Custom action handler -->

        <p class="woocommerce-form-row">
            <button type="submit" class="woocommerce-button button">
                <?php esc_html_e('Restablecer contraseña', 'woocommerce'); ?>
            </button>
        </p>

    </form>
</div>
