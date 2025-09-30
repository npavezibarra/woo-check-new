<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
    <style>
        /* Target header group inside your template-part */
        header.wp-block-template-part .wp-block-group {
            justify-content: space-between;
            align-items: center;
            max-width: 1720px !important;
            margin: auto;
        }

        /* Force mini-cart to be visible */
        .wc-block-mini-cart.wp-block-woocommerce-mini-cart {
            visibility: visible !important;
        }
    </style>
</head>


<body <?php body_class(); ?>>
<?php
// Cargar la cabecera del tema Twenty Twenty-Four (incluye la barra de navegación)
echo do_blocks('<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->');

// Obtener los detalles de la orden
$order_id = wc_get_order_id_by_order_key($_GET['key']);
$order = wc_get_order($order_id);
?>

<div class="order-received-page">
<h3>
    <?php
    $customer_first_name = $order ? $order->get_billing_first_name() : '';
    echo 'Gracias por tu compra' . (!empty($customer_first_name) ? ' ' . esc_html($customer_first_name) : '') . '!';
    ?>
</h3>



<?php if ($order) : ?>
    <?php
        $order_datetime_display = '';
        if ($order->get_date_created()) {
            $order_datetime_display = $order->get_date_created()->date_i18n('d-m-Y H:i:s');
        }
    ?>
    <div id="order-information">
        <div class="order-header">
            <p class="titulo-seccion">Número de orden: <?php echo esc_html($order->get_id()); ?></p>
            <?php
            $tracking_number  = get_post_meta($order->get_id(), '_tracking_number', true);
            $tracking_provider = get_post_meta($order->get_id(), '_tracking_provider', true);

            $has_tracking = !empty($tracking_number);

            if ($has_tracking) :
            ?>
                <p id="tracking-info" data-has-tracking="1"><strong>Tracking:</strong> <?php echo esc_html($tracking_number); ?><?php if (!empty($tracking_provider)) : ?> (<?php echo esc_html(ucfirst($tracking_provider)); ?>)<?php endif; ?></p>
            <?php else : ?>
                <p id="tracking-info" data-has-tracking="0"><em>Tu número de seguimiento estará disponible pronto.</em></p>
            <?php endif; ?>
            <?php if (!empty($tracking_provider) && strtolower((string) $tracking_provider) === 'recibelo') : ?>
                <?php
                $internal_id = get_post_meta($order->get_id(), '_recibelo_internal_id', true);

                if (empty($internal_id)) {
                    $internal_id = $tracking_number;
                }

                $billing_full_name = $order->get_formatted_billing_full_name();
                $tracking_status = class_exists('WC_Check_Recibelo')
                    ? WC_Check_Recibelo::get_tracking_status($internal_id, $billing_full_name)
                    : __('Estamos consultando el estado de este envío...', 'woo-check');
                ?>
                <p class="recibelo-tracking-status"><?php echo esc_html($tracking_status); ?></p>
            <?php endif; ?>
            <?php if (!empty($order_datetime_display)) : ?>
                <p class="fecha-hora-orden">Fecha y hora de la orden: <?php echo esc_html($order_datetime_display); ?></p>
            <?php endif; ?>
        </div>

        <?php
function calcular_dias_entrega($regionCode, $horaCompra, $metodoPago, $order_id) {
    // Mapeo de regiones y días promedio de entrega
    $regionMapping = [
        "CL-AP" => 4,
        "CL-TA" => 3,
        "CL-AN" => 3,
        "CL-AT" => 2,
        "CL-CO" => 2,
        "CL-VS" => 2,
        "CL-RM" => 2, // Región Metropolitana con regla especial
        "CL-LI" => 2,
        "CL-ML" => 2,
        "CL-NB" => 2,
        "CL-BI" => 2,
        "CL-AR" => 2,
        "CL-LR" => 3,
        "CL-LL" => 3,
        "CL-AI" => 2,
        "CL-MA" => 10
    ];

    // Si el método de pago es transferencia bancaria (bacs), muestra el mensaje de espera con datos bancarios
    if ($metodoPago === 'bacs') {
        return "
            <strong>Datos para realizar la transferencia:</strong><br>
           
                <p><strong>Nombre:</strong> Villegas y Compañía SpA</p>
                <p><strong>RUT:</strong> 77593240-6</p>
                <p><strong>Banco:</strong> Banco Itaú</p>
                <p><strong>Cuenta Corriente:</strong> 0224532529</p>
                <p><strong>Correo:</strong> <a href='mailto:villeguistas@gmail.com'>villeguistas@gmail.com</a></p>
            <p><strong>IMPORTANTE:</strong> Enviar comprobante al correo indicado. Sin comprobante no podemos procesar la orden.</p>
            <p>Indique el número de orden (<strong>$order_id</strong>) y su nombre en el mensaje de la transferencia.</p>
        ";
    }

    // Obtén los días base para la región
    $diasRegion = isset($regionMapping[$regionCode]) ? $regionMapping[$regionCode] : 0;

    // Convierte la hora de compra a un objeto DateTime
    $horaCompraDT = new DateTime($horaCompra);
    $horaDelDia = (int)$horaCompraDT->format('H');
    $diaSemana = (int)$horaCompraDT->format('N'); // 1 = Lunes, 7 = Domingo

    // Regla especial para Región Metropolitana
    if ($regionCode === "CL-RM") {
        if ($diaSemana >= 6) {
            // Sábados y domingos no hay entregas
            return "El despacho será realizado el lunes y llegará el mismo día.";
        }
        if ($diaSemana === 5 && $horaDelDia >= 11) {
            // Viernes después de las 11am, se despacha el lunes y llega el mismo día
            return "El despacho será realizado el lunes y llegará el mismo día.";
        }
        if ($horaDelDia < 11) {
            // Antes de las 11am, llega el mismo día
            return "Tu orden será entregada hoy mismo.";
        }
    }

    // Cálculo estándar para otras regiones
    $diasExtra = 0;

    if ($horaDelDia >= 11) {
        if ($diaSemana == 5) { // Viernes después de las 11am
            $diasExtra = 3; // Se despacha el lunes
        } elseif ($diaSemana >= 6) {
            $diasExtra = 2; // Sábados o domingos, despacho lunes
        } else {
            $diasExtra = 1; // Día regular después de las 11am
        }
    } else {
        if ($diaSemana == 5) { // Viernes antes de las 11am
            $diasExtra = 2; // Se despacha el lunes
        } elseif ($diaSemana >= 6) {
            $diasExtra = 2; // Sábados o domingos, despacho lunes
        }
    }

    return $diasRegion + $diasExtra;
}

// Obtener los detalles de la orden
$order_id = wc_get_order_id_by_order_key($_GET['key']);
$order = wc_get_order($order_id);

if (!$order) {
    echo "<div class='order-error'>Lo sentimos, no pudimos encontrar tu orden. Por favor, verifica tu información.</div>";
    return; // Salir si la orden no es válida
}

// Aquí continúa el resto de tu código que utiliza $order


if ($order) {
    $regionCode = $order->get_billing_state(); // Código de la región
    $horaCompra = $order->get_date_created()->date('Y-m-d H:i:s'); // Fecha y hora de la compra
    $metodoPago = $order->get_payment_method(); // Método de pago (por ejemplo: 'bacs', 'stripe', etc.)

    // Muestra la fecha y hora de la orden
    $diasEntrega = calcular_dias_entrega($regionCode, $horaCompra, $metodoPago, $order_id);

    // Genera el contenido dentro de un <div> con id="info-entrega"
    echo "<div id='info-entrega'>";

    if (is_string($diasEntrega)) {
        // Regla especial o mensaje de espera devuelve un mensaje directo
        echo "<div id='estimacion-entrega'>$diasEntrega</div>";
    } else {
        // Otras regiones o reglas estándar
        echo "<div id='estimacion-entrega'>Tu orden será entregada cerca de $diasEntrega días.</div>";
    }

    echo "</div>";
} else {
    echo "<div id='info-entrega'>No se pudo obtener la información de la orden.</div>";
}
?>

<h3>Detalles de la orden</h3>
        <ul class="order-products">
            <?php foreach ($order->get_items() as $item_id => $item) : ?>
                <?php
                $product = $item->get_product();
                $product_name = $item->get_name();
                $product_quantity = $item->get_quantity();
                $product_subtotal = $order->get_formatted_line_subtotal($item);
                $product_image = $product ? $product->get_image('thumbnail') : '<div class="placeholder-image"></div>';

                // Check if the product is linked to a LearnDash course
                $course_meta = get_post_meta($product->get_id(), '_related_course', true);

                // Handle serialized course_meta
                if (is_serialized($course_meta)) {
                    $course_meta = unserialize($course_meta);
                }

                // Extract the course ID
                $course_id = is_array($course_meta) && isset($course_meta[0]) ? $course_meta[0] : $course_meta;

                // Generate course URL if a valid course ID exists
                $course_url = !empty($course_id) && is_numeric($course_id) ? get_permalink($course_id) : null;
                ?>
                <li class="order-product-item">
                    <div class="product-flex-container">
                        <div class="product-image"><?php echo $product_image; ?></div>
                        <div class="product-details">
                            <?php if ($course_url) : ?>
                                <!-- Show only the product name without quantity for courses -->
                                <span><?php echo esc_html($product_name); ?></span>
                                <br><a href="<?php echo esc_url($course_url); ?>" class="button" style="display: inline-block; margin-top: 10px; padding: 5px 10px; background-color: black; color: #fff; text-decoration: none; border-radius: 3px; font-size: 12px;">Ir al Curso</a>
                            <?php else : ?>
                                <!-- Show product name with quantity for non-courses -->
                                <span><?php echo esc_html($product_quantity); ?> - <?php echo esc_html($product_name); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="product-total"><?php echo wp_kses_post($product_subtotal); ?></div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <div id="info-extra-envio">
            <?php if ($order) : ?>
                <?php
                // Obtener los valores del pedido
                $shipping_total = $order->get_shipping_total(); // Total de envío
                $total = $order->get_total(); // Total de la orden (incluye todo)

                // Calcular IVA como 19% del subtotal antes de impuestos
                $subtotal = $total - $shipping_total; // Subtotal sin envío
                $iva = $subtotal * 0.19; // IVA es el 19% del subtotal
                ?>
                <p><strong>Envío:</strong> <?php echo wc_price($shipping_total); ?></p>
                <p><strong>IVA (19%):</strong> <?php echo wc_price($iva); ?></p>
                <p><strong>Total Orden:</strong> <?php echo wc_price($total); ?></p>
            <?php else : ?>
                <p>No se encontraron detalles del pedido.</p>
            <?php endif; ?>
        </div>
    </div>
<?php else : ?>
    <h2>Orden no encontrada.</h2>
<?php endif; ?>

</div>

<?php if ($order): ?>
<?php
// Verificar si hay productos físicos en el pedido
$has_physical_products = false;
foreach ($order->get_items() as $item_id => $item) {
    $product = $item->get_product();
    if ($product && !$product->is_virtual()) {
        $has_physical_products = true;
        break;
    }
}

// Determinar clase adicional para el contenedor de Billing
$billing_class = $has_physical_products ? '' : ' only-billing';
?>

<section class="woocommerce-customer-details" id="info-clientes">
    <h3>Detalles del cliente</h3>
    <div class="woocommerce-columns woocommerce-columns--2 woocommerce-columns--addresses col2-set addresses">
        <!-- Billing Address -->
        <div class="woocommerce-column woocommerce-column--1 woocommerce-column--billing-address col-1<?php echo esc_attr($billing_class); ?>">
            <h4><?php esc_html_e('Dirección de Facturación', 'woocommerce'); ?></h4>
            <address>
                <?php if ($order->get_billing_first_name() || $order->get_billing_last_name()) : ?>
                    <?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?><br>
                <?php endif; ?>
                <?php if ($order->get_billing_address_1()) : ?>
                    <?php echo esc_html($order->get_billing_address_1()); ?>
                    <?php if ($order->get_billing_address_2()) : ?>
                        , <?php echo esc_html($order->get_billing_address_2()); ?>
                    <?php endif; ?><br>
                <?php endif; ?>
                <?php 
                $billing_comuna = get_post_meta($order->get_id(), 'billing_comuna', true);
                if (!empty($billing_comuna)) : ?>
                    <?php echo esc_html($billing_comuna); ?><br>
                <?php endif; ?>
                <?php if ($order->get_billing_state()) : ?>
                    <?php echo esc_html($order->get_billing_state()); ?><br>
                <?php endif; ?>
                <?php if ($order->get_billing_phone()) : ?>
                    <?php echo wc_make_phone_clickable($order->get_billing_phone()); ?><br>
                <?php endif; ?>
                <?php if ($order->get_billing_email()) : ?>
                    <?php echo esc_html($order->get_billing_email()); ?>
                <?php endif; ?>
            </address>
        </div>

        <!-- Shipping Address -->
        <?php if ($has_physical_products): ?>
        <div class="woocommerce-column woocommerce-column--2 woocommerce-column--shipping-address col-2">
            <h4><?php esc_html_e('Dirección de Envío', 'woocommerce'); ?></h4>
            <address>
                <?php if ($order->get_shipping_first_name() || $order->get_shipping_last_name()) : ?>
                    <?php echo esc_html($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()); ?><br>
                <?php endif; ?>
                <?php if ($order->get_shipping_address_1()) : ?>
                    <?php echo esc_html($order->get_shipping_address_1()); ?>
                    <?php if ($order->get_shipping_address_2()) : ?>
                        , <?php echo esc_html($order->get_shipping_address_2()); ?>
                    <?php endif; ?><br>
                <?php endif; ?>
                <?php 
                $shipping_comuna = get_post_meta($order->get_id(), 'shipping_comuna', true);
                if (!empty($shipping_comuna)) : ?>
                    <?php echo esc_html($shipping_comuna); ?><br>
                <?php endif; ?>
                <?php if ($order->get_shipping_state()) : ?>
                    <?php echo esc_html($order->get_shipping_state()); ?><br>
                <?php endif; ?>
                <?php if ($order->get_shipping_phone()) : ?>
                    <?php echo wc_make_phone_clickable($order->get_shipping_phone()); ?><br>
                <?php endif; ?>
            </address>
        </div>
        <?php endif; ?>
    </div>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const trackingEl = document.getElementById("tracking-info");

        if (!trackingEl || trackingEl.dataset.hasTracking === "1") {
            return;
        }

        const orderId = "<?php echo esc_js($order->get_id()); ?>";
        const ajaxUrl = "<?php echo esc_js(esc_url_raw(admin_url('admin-ajax.php'))); ?>";

        function formatProvider(provider) {
            if (!provider) {
                return "";
            }

            const normalized = provider.toString();

            return normalized.charAt(0).toUpperCase() + normalized.slice(1);
        }

        function fetchTracking() {
            fetch(ajaxUrl, {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: "action=get_tracking_info&order_id=" + encodeURIComponent(orderId)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.tracking_number) {
                    const providerLabel = formatProvider(data.provider);
                    trackingEl.dataset.hasTracking = "1";
                    trackingEl.innerHTML = "<strong>Tracking:</strong> " + data.tracking_number + (providerLabel ? " (" + providerLabel + ")" : "");
                    clearInterval(pollInterval);
                }
            })
            .catch(function(error) {
                console.error(error);
            });
        }

        fetchTracking();
        const pollInterval = setInterval(fetchTracking, 15000);
    });
    </script>
</section>



<?php endif; ?>

<?php


wp_footer();
?>
</body>
</html>