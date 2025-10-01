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

        .recibelo-tracking-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #ffffff;
            border-radius: 3px;
            padding: 3px 6px;
        }

        .recibelo-tracking-status::before {
            content: "\1F4E6";
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
<h3 id="order-thank-you-heading">
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

        $format_address_block = function ($type) use ($order) {
            $lines = [];

            if ($type === 'billing') {
                $first_name = $order->get_billing_first_name();
                $last_name  = $order->get_billing_last_name();
                $address_1  = $order->get_billing_address_1();
                $address_2  = $order->get_billing_address_2();
                $comuna     = get_post_meta($order->get_id(), 'billing_comuna', true);
                $state      = $order->get_billing_state();
                $phone      = $order->get_billing_phone();
                $email      = $order->get_billing_email();
            } else {
                $first_name = $order->get_shipping_first_name();
                $last_name  = $order->get_shipping_last_name();
                $address_1  = $order->get_shipping_address_1();
                $address_2  = $order->get_shipping_address_2();
                $comuna     = get_post_meta($order->get_id(), 'shipping_comuna', true);
                $state      = $order->get_shipping_state();
                $phone      = $order->get_shipping_phone();
                $email      = '';
            }

            $full_name = trim(trim((string) $first_name) . ' ' . trim((string) $last_name));
            if (!empty($full_name)) {
                $lines[] = esc_html($full_name);
            }

            $address_line = trim((string) $address_1);
            if (!empty($address_2)) {
                $address_line .= ', ' . trim((string) $address_2);
            }
            if (!empty($address_line)) {
                $lines[] = esc_html($address_line);
            }

            if (!empty($comuna)) {
                $lines[] = esc_html((string) $comuna);
            }

            if (!empty($state)) {
                $lines[] = esc_html((string) $state);
            }

            if (!empty($phone)) {
                $lines[] = wc_make_phone_clickable($phone);
            }

            if (!empty($email)) {
                $lines[] = sprintf(
                    '<a href="mailto:%1$s">%2$s</a>',
                    esc_attr($email),
                    esc_html($email)
                );
            }

            $lines = array_filter($lines, static function ($line) {
                return $line !== '' && $line !== null;
            });

            return implode('<br>', $lines);
        };

        $billing_address_content  = $format_address_block('billing');
        $shipping_address_content = $format_address_block('shipping');

        if (empty($billing_address_content) && !empty($shipping_address_content)) {
            $billing_address_content = $shipping_address_content;
        }

        if (empty($shipping_address_content) && !empty($billing_address_content)) {
            $shipping_address_content = $billing_address_content;
        }

        $has_address_information = !empty($billing_address_content) || !empty($shipping_address_content);
    ?>
    <div id="order-information">
        <?php
        $metodoPago = $order ? $order->get_payment_method() : '';
        $order_products_markup = '';

        if ($order) {
            ob_start();
            ?>
            <ul class="order-products">
                <?php foreach ($order->get_items() as $item_id => $item) : ?>
                    <?php
                    $product = $item->get_product();
                    $product_name = $item->get_name();
                    $product_quantity = $item->get_quantity();
                    $product_subtotal = $order->get_formatted_line_subtotal($item);
                    $product_image = $product ? $product->get_image('thumbnail') : '<div class="placeholder-image"></div>';

                    // Check if the product is linked to a LearnDash course
                    $course_meta = $product ? get_post_meta($product->get_id(), '_related_course', true) : '';

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
            <?php
            $order_products_markup = ob_get_clean();
        }

        $bank_transfer_info = '';

        if ($metodoPago === 'bacs') {
            ob_start();
            ?>
            <div class="bank-transfer-info">
                <button class="bank-transfer-toggle" type="button" aria-expanded="false">
                    <span class="bank-transfer-title">🏦 Datos Transferencia Bancaria</span>
                    <span class="bank-transfer-icon" aria-hidden="true">+</span>
                </button>
                <div class="bank-transfer-content" hidden>
                    <ul class="bank-transfer-list">
                        <li class="bank-transfer-item">
                            <span class="bank-transfer-label">Nombre:</span>
                            <span class="bank-transfer-value">Villegas y Compañía SpA</span>
                            <button class="bank-transfer-copy" type="button" data-copy="Villegas y Compañía SpA" aria-label="Copiar nombre">
                                <span class="bank-transfer-copy-icon" aria-hidden="true">📋</span>
                            </button>
                        </li>
                        <li class="bank-transfer-item">
                            <span class="bank-transfer-label">RUT:</span>
                            <span class="bank-transfer-value">77593240-6</span>
                            <button class="bank-transfer-copy" type="button" data-copy="77593240-6" aria-label="Copiar RUT">
                                <span class="bank-transfer-copy-icon" aria-hidden="true">📋</span>
                            </button>
                        </li>
                        <li class="bank-transfer-item">
                            <span class="bank-transfer-label">Banco:</span>
                            <span class="bank-transfer-value">Banco Itaú</span>
                            <button class="bank-transfer-copy" type="button" data-copy="Banco Itaú" aria-label="Copiar banco">
                                <span class="bank-transfer-copy-icon" aria-hidden="true">📋</span>
                            </button>
                        </li>
                        <li class="bank-transfer-item">
                            <span class="bank-transfer-label">Cuenta Corriente:</span>
                            <span class="bank-transfer-value">0224532529</span>
                            <button class="bank-transfer-copy" type="button" data-copy="0224532529" aria-label="Copiar cuenta corriente">
                                <span class="bank-transfer-copy-icon" aria-hidden="true">📋</span>
                            </button>
                        </li>
                        <li class="bank-transfer-item">
                            <span class="bank-transfer-label">Correo:</span>
                            <span class="bank-transfer-value"><a href="mailto:villeguistas@gmail.com">villeguistas@gmail.com</a></span>
                            <button class="bank-transfer-copy" type="button" data-copy="villeguistas@gmail.com" aria-label="Copiar correo">
                                <span class="bank-transfer-copy-icon" aria-hidden="true">📋</span>
                            </button>
                        </li>
                    </ul>
                    <p class="bank-transfer-important"><strong>IMPORTANTE:</strong> Enviar comprobante al correo indicado. Sin comprobante no podemos procesar la orden.</p>
                    <p class="bank-transfer-reminder">Indique el número de orden (<strong><?php echo esc_html($order_id); ?></strong>) y su nombre en el mensaje de la transferencia.</p>
                </div>

                <?php if (!empty($order_products_markup)) : ?>
                    <button class="bank-transfer-toggle" type="button" aria-expanded="true">
                        <span class="bank-transfer-title">🛍️ Tu compra</span>
                        <span class="bank-transfer-icon" aria-hidden="true">−</span>
                    </button>
                    <div class="bank-transfer-content">
                        <?php echo $order_products_markup; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            $bank_transfer_info = ob_get_clean();
        }
        $order_summary_classes = 'order-summary-bank-wrapper';

        if (empty($bank_transfer_info)) {
            $order_summary_classes .= ' order-summary-bank-wrapper--single';
        }
        ?>
        <div class="<?php echo esc_attr($order_summary_classes); ?>">
            <div class="order-summary-primary">
                <div id="order-header" class="order-header">
                <p class="titulo-seccion">Número de orden: <?php echo esc_html($order->get_id()); ?></p>
                <?php
                $tracking_provider_raw = get_post_meta($order->get_id(), '_tracking_provider', true);
                $default_tracking      = $order->get_id() . 'N';

                $tracking_provider_labels = [
                    'recibelo' => __('Recíbelo', 'woo-check'),
                    'shipit'   => __('Shipit', 'woo-check'),
                ];

                $tracking_provider_slug   = '';
                $tracking_provider_label  = '';

                if (is_string($tracking_provider_raw)) {
                    $tracking_provider_candidate = strtolower(trim($tracking_provider_raw));

                    if (isset($tracking_provider_labels[$tracking_provider_candidate])) {
                        $tracking_provider_slug  = $tracking_provider_candidate;
                        $tracking_provider_label = $tracking_provider_labels[$tracking_provider_candidate];
                    }
                }

                $tracking_status_attributes = sprintf(
                    'data-order-id="%s"',
                    esc_attr($order->get_id())
                );

                if ($tracking_provider_slug !== '') {
                    $tracking_status_attributes .= sprintf(
                        ' data-tracking-provider="%s"',
                        esc_attr($tracking_provider_slug)
                    );
                }
                ?>
                <div id="tracking-status" <?php echo $tracking_status_attributes; ?>>
                    <p class="tracking-heading">
                        <strong><?php esc_html_e('Tracking:', 'woo-check'); ?></strong>
                        <span class="tracking-number"><?php echo esc_html($default_tracking); ?></span>
                        <?php if ($tracking_provider_label !== '') : ?>
                            <span class="tracking-courier">(<?php echo esc_html($tracking_provider_label); ?>)</span>
                        <?php endif; ?>
                    </p>
                    <p class="tracking-message"><?php esc_html_e('Estamos consultando el estado de este envío...', 'woo-check'); ?></p>
                    <p class="tracking-link" style="display:none;"><a href="#" target="_blank" rel="noopener noreferrer"></a></p>
                </div>
                <?php if ($tracking_provider_slug === 'recibelo') : ?>
                    <?php
                    $internal_id = get_post_meta($order->get_id(), '_recibelo_internal_id', true);

                    if (empty($internal_id)) {
                        $internal_id = $default_tracking;
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
                <?php if ($has_address_information) : ?>
                    <div class="order-address-flip-card">
                        <div class="flip-card" tabindex="0" role="button" aria-label="<?php esc_attr_e('Ver direcciones de facturación y envío', 'woocommerce'); ?>" aria-pressed="false">
                            <div class="flip-card-inner">
                                <div class="flip-card-face flip-card-front">
                                    <h4><?php esc_html_e('Dirección de Facturación', 'woocommerce'); ?></h4>
                                    <address><?php echo wp_kses_post($billing_address_content); ?></address>
                                </div>
                                <div class="flip-card-face flip-card-back">
                                    <h4><?php esc_html_e('Dirección de Envío', 'woocommerce'); ?></h4>
                                    <address><?php echo wp_kses_post($shipping_address_content); ?></address>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
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
                        <div class="info-extra-items" role="list">
                            <div class="info-extra-item" role="listitem">
                                <span class="info-extra-icon" aria-hidden="true">🚚</span>
                                <span class="info-extra-label"><?php esc_html_e('Envío', 'woocommerce'); ?></span>
                                <span class="info-extra-value"><?php echo wp_kses_post(wc_price($shipping_total)); ?></span>
                            </div>
                            <div class="info-extra-item" role="listitem">
                                <span class="info-extra-icon" aria-hidden="true">🕵️‍♂️</span>
                                <span class="info-extra-label"><?php esc_html_e('IVA (19%)', 'woocommerce'); ?></span>
                                <span class="info-extra-value"><?php echo wp_kses_post(wc_price($iva)); ?></span>
                            </div>
                            <div class="info-extra-item" role="listitem">
                                <span class="info-extra-icon" aria-hidden="true">💵</span>
                                <span class="info-extra-label"><?php esc_html_e('Total Orden', 'woocommerce'); ?></span>
                                <span class="info-extra-value"><?php echo wp_kses_post(wc_price($total)); ?></span>
                            </div>
                        </div>
                    <?php else : ?>
                        <p><?php esc_html_e('No se encontraron detalles del pedido.', 'woocommerce'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php echo $bank_transfer_info; ?>
        </div>
        <?php if ($metodoPago === 'bacs') : ?>
            <script>
                (function () {
                    if (window.bankTransferInfoInitialized) {
                        return;
                    }
                    window.bankTransferInfoInitialized = true;

                    const initBankTransferInfo = function () {
                        const toggles = document.querySelectorAll('.bank-transfer-toggle');
                        toggles.forEach(function (toggle) {
                            toggle.addEventListener('click', function () {
                                const content = this.nextElementSibling;
                                const icon = this.querySelector('.bank-transfer-icon');
                                const isExpanded = this.getAttribute('aria-expanded') === 'true';

                                if (isExpanded) {
                                    this.setAttribute('aria-expanded', 'false');
                                    content.setAttribute('hidden', '');
                                    if (icon) {
                                        icon.textContent = '+';
                                    }
                                } else {
                                    this.setAttribute('aria-expanded', 'true');
                                    content.removeAttribute('hidden');
                                    if (icon) {
                                        icon.textContent = '−';
                                    }
                                }
                            });
                        });

                        const fallbackCopyText = function (text) {
                            return new Promise(function (resolve, reject) {
                                const textArea = document.createElement('textarea');
                                textArea.value = text;
                                textArea.setAttribute('readonly', '');
                                textArea.style.position = 'absolute';
                                textArea.style.left = '-9999px';
                                document.body.appendChild(textArea);

                                const selection = document.getSelection();
                                const selectedRange = selection && selection.rangeCount > 0 ? selection.getRangeAt(0) : null;

                                textArea.select();

                                let copySuccessful = false;
                                let execCommandError = null;
                                try {
                                    copySuccessful = document.execCommand('copy');
                                } catch (error) {
                                    execCommandError = error;
                                }

                                document.body.removeChild(textArea);

                                if (selectedRange && selection) {
                                    selection.removeAllRanges();
                                    selection.addRange(selectedRange);
                                }

                                if (copySuccessful) {
                                    resolve();
                                } else if (execCommandError) {
                                    reject(execCommandError);
                                } else {
                                    reject(new Error('execCommand failed'));
                                }
                            });
                        };

                        const copyButtons = document.querySelectorAll('.bank-transfer-copy');
                        copyButtons.forEach(function (button) {
                            button.addEventListener('click', function (event) {
                                event.preventDefault();
                                const self = this;
                                const valueToCopy = self.getAttribute('data-copy');
                                const icon = self.querySelector('.bank-transfer-copy-icon');
                                const originalIcon = icon ? icon.textContent : '';

                                const handleSuccess = function () {
                                    if (icon) {
                                        icon.textContent = '✓';
                                    }
                                    self.classList.add('bank-transfer-copy--success');
                                    setTimeout(function () {
                                        if (icon) {
                                            icon.textContent = originalIcon || '📋';
                                        }
                                        button.classList.remove('bank-transfer-copy--success');
                                    }, 2000);
                                };

                                const handleFailure = function (error) {
                                    console.error('No se pudo copiar el texto:', error);
                                };

                                const canUseNavigatorClipboard = !!(navigator.clipboard && window.isSecureContext);

                                const attemptCopy = canUseNavigatorClipboard
                                    ? navigator.clipboard.writeText(valueToCopy)
                                    : fallbackCopyText(valueToCopy);

                                attemptCopy
                                    .then(handleSuccess)
                                    .catch(function (error) {
                                        if (canUseNavigatorClipboard) {
                                            fallbackCopyText(valueToCopy)
                                                .then(handleSuccess)
                                                .catch(function (fallbackError) {
                                                    handleFailure(fallbackError || error);
                                                });
                                        } else {
                                            handleFailure(error);
                                        }
                                    });
                            });
                        });
                    };

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', initBankTransferInfo);
                    } else {
                        initBankTransferInfo();
                    }
                })();
            </script>
        <?php endif; ?>

        <?php if ($metodoPago !== 'bacs' && !empty($order_products_markup)) : ?>
            <h3>Detalles de la orden</h3>
            <?php echo $order_products_markup; ?>
        <?php endif; ?>
    </div>
<?php else : ?>
    <h2>Orden no encontrada.</h2>
<?php endif; ?>

</div>

<?php if ($order): ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const flipCard = document.querySelector('.order-address-flip-card .flip-card');
        if (flipCard) {
            const supportsHover = window.matchMedia('(hover: hover)').matches;
            const updatePressedState = function () {
                flipCard.setAttribute('aria-pressed', flipCard.classList.contains('is-flipped') ? 'true' : 'false');
            };

            updatePressedState();

            if (!supportsHover) {
                flipCard.addEventListener('click', function(event) {
                    if (event.target.closest('a')) {
                        return;
                    }
                    event.preventDefault();
                    flipCard.classList.toggle('is-flipped');
                    updatePressedState();
                });
            }

            flipCard.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    flipCard.classList.toggle('is-flipped');
                    updatePressedState();
                }
            });
        }

    });
    </script>
<?php endif; ?>

<?php


wp_footer();
?>
</body>
</html>
