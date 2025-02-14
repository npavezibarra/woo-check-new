<?php
defined('ABSPATH') || exit;

$error_message = isset($_GET['reset_error']) ? sanitize_text_field(urldecode($_GET['reset_error'])) : '';
$success_message = isset($_GET['reset_success']) ? sanitize_text_field(urldecode($_GET['reset_success'])) : '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña</title>
    <style>
        body {
            background: url('https://elvillegas.cl/wp-content/uploads/2024/05/Nunork4.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            font-family: Arial, sans-serif;
            text-align: center;
            color: white;
        }
        .container {
            max-width: 400px;
            padding: 20px;
            border-radius: 8px;
        }
        h3 {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 20px;
            background: transparent;
            padding: 10px;
        }
        .back-link {
            display: inline-block;
            padding: 10px 20px;
            background: black;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
        }
        .back-link:hover {
            background: #333;
        }
    </style>
</head>
<body>

<div class="container">
    <?php if (!empty($error_message)): ?>
        <h3><?php echo esc_html($error_message); ?></h3>
        <p style="color: white;">Si tu correo no está registrado, regístrate con el botón de abajo.</p>
        <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="back-link">
        Registrarse
    </a>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
        <h3><?php echo esc_html($success_message); ?></h3>
        <p style="color: white;">No olvides revisar spam.</p>
        <a href="<?php echo esc_url(home_url('/mi-cuenta/')); ?>" class="back-link">
    Volver
</a>

    <?php endif; ?>

    
</div>

</body>
</html>




