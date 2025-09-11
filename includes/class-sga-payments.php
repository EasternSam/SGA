<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_Payments
 *
 * Gestiona todo lo relacionado con las pasarelas de pago (Azul, Cardnet),
 * incluyendo el renderizado de la página de pagos y el manejo de la respuesta.
 */
class SGA_Payments {

    public function __construct() {
        add_action('init', array($this, 'handle_azul_response'));
        add_action('init', array($this, 'handle_cardnet_response'));
    }

    /**
     * Renderiza el contenido de la página de pagos [sga_pagina_pagos].
     */
    public function render_pagos_page() {
        ob_start();
        
        $options = get_option('sga_payment_options');
        $active_gateway = $options['active_gateway'] ?? 'azul';
        $cardnet_env = '';
        $cardnet_public_key = '';

        if ($active_gateway === 'cardnet') {
            $cardnet_env = $options['cardnet_environment'] ?? 'sandbox';
            $cardnet_public_key = $options['cardnet_public_key'] ?? '';
            $pwcheckout_url = $cardnet_env === 'production' 
                ? "https://servicios.cardnet.com.do/servicios/tokens/v1/Scripts/PWCheckout.js?key={$cardnet_public_key}"
                : "https://lab.cardnet.com.do/servicios/tokens/v1/Scripts/PWCheckout.js?key={$cardnet_public_key}";
            
            echo '<script type="text/javascript" src="' . esc_url($pwcheckout_url) . '"></script>';
        }

        ?>
        <style>
            .sga-pagos-container { max-width: 900px; margin: 40px auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
            .sga-pagos-container h2 { text-align: center; color: #333; font-size: 24px; margin-bottom: 5px; }
            .sga-pagos-container h3 { text-align: center; color: #555; font-size: 18px; margin-top: 0; margin-bottom: 40px; font-weight: 400; }
            .sga-portal-wrapper { display: flex; flex-wrap: wrap; gap: 30px; }
            .sga-portal-left { flex: 1; min-width: 320px; }
            .sga-portal-right { flex: 1; min-width: 320px; }
            .sga-payment-form-container { background-color: #fff; border: 1px solid #e0e0e0; border-radius: 16px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); padding: 25px 35px 35px 35px; }
            .sga-form-icon { width: 80px; height: 80px; margin: -70px auto 15px; background-color: #141f53; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border: 4px solid #fff; }
            .sga-form-icon svg { color: white; width: 40px; height: 40px; }
            .sga-form-group { margin-bottom: 20px; }
            .sga-form-group label { display: block; font-weight: 600; color: #555; margin-bottom: 8px; font-size: 14px; }
            .sga-form-group label span { color: #dc2626; }
            .sga-form-group select, .sga-form-group input { width: 100%; padding: 12px; font-size: 16px; border: 1px solid #ccc; border-radius: 8px; box-sizing: border-box; }
            .sga-form-group input[readonly] { background-color: #f4f4f4; cursor: not-allowed; }
            .sga-form-group .monto-wrapper { position: relative; }
            .sga-form-group .monto-wrapper .help-icon { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background-color: #6b7280; color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-weight: bold; cursor: help; }
            .sga-submit-button { width: 100%; padding: 14px 20px; font-size: 16px; background-color: #141f53; color: white; border: none; border-radius: 8px; cursor: pointer; transition: background-color 0.3s; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; }
            .sga-submit-button:hover { background-color: #0041a3; }
            .sga-submit-button:disabled { background-color: #a0a0a0; cursor: not-allowed; }
            #sga-payment-message { text-align: center; padding: 15px; border-radius: 8px; margin-top: 20px; display: none; font-size: 16px; }
            #sga-payment-message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            #sga-payment-message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
            #sga-payment-message.processing { background-color: #cce5ff; color: #004085; border: 1px solid #b8daff; }
            .sga-id-form-wrapper { max-width: 550px; }
            .sga-id-form { display: flex; flex-direction: column; gap: 15px; }
            .sga-id-form input[type="text"], .sga-id-form select { padding: 12px; font-size: 16px; border: 1px solid #ccc; border-radius: 5px; }
            .sga-id-form button { padding: 12px 20px; font-size: 16px; background-color: #0052cc; color: white; border: none; border-radius: 5px; cursor: pointer; transition: background-color 0.3s; }
            .sga-dashboard-section { background-color: #fff; border: 1px solid #e0e0e0; border-radius: 16px; padding: 25px; }
            .sga-dashboard-section h4 { border-bottom: 2px solid #141f53; padding-bottom: 8px; margin-top: 0; margin-bottom: 15px; font-size: 18px; color: #141f53; }
            .sga-history-table-wrapper { max-height: 220px; overflow-y: auto; }
            .sga-history-table { width: 100%; border-collapse: collapse; font-size: 14px; }
            .sga-history-table thead th { position: sticky; top: 0; background-color: #f8f9fa; z-index: 1; }
            .sga-history-table th, .sga-history-table td { border-bottom: 1px solid #ddd; padding: 10px 8px; text-align: left; }
            .sga-history-table th { font-weight: 600; }
            .sga-history-table tr:last-child td { border-bottom: none; }
            .sga-history-table .print-button { padding: 4px 8px; font-size: 12px; }
        </style>
        <?php
        if (!isset($_GET['identificador']) || empty($_GET['identificador'])) {
            ?>
            <div class="sga-pagos-container sga-id-form-wrapper">
                <div class="sga-payment-form-container">
                    <div class="sga-form-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0z" /></svg>
                    </div>
                    <h2>Portal de Pagos</h2>
                    <p style="text-align: center; font-size: 16px; margin-bottom: 25px; color: #555;">Por favor, identifícate para continuar.</p>
                    <form method="GET" action="" class="sga-id-form">
                        <select name="tipo_id" required>
                            <option value="cedula">Soy estudiante nuevo (Cédula)</option>
                            <option value="matricula">Soy estudiante activo (Matrícula)</option>
                        </select>
                        <input type="text" name="identificador" placeholder="Escribe tu identificador sin guiones" required>
                        <button type="submit">Consultar</button>
                    </form>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        $tipo_id = sanitize_key($_GET['tipo_id']);
        $identificador = sanitize_text_field($_GET['identificador']);
        $estudiante_post = null;

        if ($tipo_id === 'cedula') {
            $estudiante_query = get_posts(array('post_type' => 'estudiante', 'meta_key' => 'cedula', 'meta_value' => $identificador, 'posts_per_page' => 1));
            if ($estudiante_query) $estudiante_post = $estudiante_query[0];
        } elseif ($tipo_id === 'matricula') {
            $todos_estudiantes = get_posts(array('post_type' => 'estudiante', 'posts_per_page' => -1));
            if ($todos_estudiantes && function_exists('get_field')) {
                foreach ($todos_estudiantes as $est) {
                    $cursos = get_field('cursos_inscritos', $est->ID);
                    if ($cursos) {
                        foreach ($cursos as $curso) {
                            if (isset($curso['matricula']) && $curso['matricula'] == $identificador) {
                                $estudiante_post = $est;
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        if (!$estudiante_post) {
            echo '<div class="sga-pagos-container"><h3>No se encontró un estudiante con el identificador proporcionado.</h3> <a href="' . esc_url(get_permalink()) . '">Intentar de nuevo</a></div>';
            return ob_get_clean();
        }
        
        $payment_items = [];
        $cursos_inscritos = get_field('cursos_inscritos', $estudiante_post->ID);
        if ($cursos_inscritos) {
            foreach ($cursos_inscritos as $index => $curso) {
                if (isset($curso['estado']) && $curso['estado'] == 'Inscrito') {
                    $curso_post_query = get_posts(['post_type' => 'curso', 'title' => $curso['nombre_curso'], 'posts_per_page' => 1]);
                    if ($curso_post_query) {
                        $precio_local_str = get_field('precio_del_curso', $curso_post_query[0]->ID);
                        $precio_limpio = preg_replace('/[^0-9.]/', '', $precio_local_str);
                        if (is_numeric($precio_limpio) && $precio_limpio > 0) {
                            $payment_items['inscripciones'][] = [
                                'id' => 'insc-'.$index,
                                'custom_id' => 'inscription:' . $estudiante_post->ID . ':' . $index,
                                'label' => $curso['nombre_curso'] . ' (' . $curso['horario'] . ')',
                                'description' => 'Inscripción: ' . $curso['nombre_curso'],
                                'amount_local' => $precio_local_str,
                                'amount_numeric' => $precio_limpio
                            ];
                        }
                    }
                }
            }
        }

        if ($tipo_id === 'matricula') {
            $conceptos_pago = get_posts(['post_type' => 'sga_concepto_pago', 'posts_per_page' => -1]);
            if($conceptos_pago) {
                foreach($conceptos_pago as $concepto) {
                    $precio_local_str = get_post_meta($concepto->ID, '_sga_precio', true);
                    $precio_limpio = preg_replace('/[^0-9.]/', '', $precio_local_str);
                    if (is_numeric($precio_limpio) && $precio_limpio > 0) {
                        $payment_items['generales'][] = [
                            'id' => 'gen-'.$concepto->ID,
                            'custom_id' => 'general:' . $concepto->ID . ':' . $estudiante_post->ID,
                            'label' => $concepto->post_title,
                            'description' => $concepto->post_title,
                            'amount_local' => $precio_local_str,
                            'amount_numeric' => $precio_limpio
                        ];
                    }
                }
            }
        }
        ?>
        <div class="sga-pagos-container">
            <h2>Portal de Pagos</h2>
            <h3>Bienvenido/a, <strong><?php echo esc_html($estudiante_post->post_title); ?></strong></h3>
            <div class="sga-portal-wrapper <?php echo ($tipo_id === 'matricula') ? 'has-history' : ''; ?>">
                <div class="sga-portal-left">
                    <div class="sga-payment-form-container">
                        <div class="sga-form-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>
                        </div>
                        <form id="sga-payment-form" method="post">
                             <input type="hidden" name="PWToken" id="PWToken" />
                            <div class="sga-form-group">
                                <label for="sga_tipo_pago">Tipo de Pagos <span>*</span></label>
                                <select id="sga_tipo_pago">
                                    <option value="">-- Seleccionar --</option>
                                    <?php if (!empty($payment_items['inscripciones'])): ?>
                                        <option value="inscripciones">Inscripciones Pendientes</option>
                                    <?php endif; ?>
                                    <?php if (!empty($payment_items['generales'])): ?>
                                        <option value="generales">Servicios Generales</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="sga-form-group">
                                <label for="sga_servicio">Servicio/Concepto <span>*</span></label>
                                <select id="sga_servicio" name="sga_servicio" disabled>
                                    <option value="">-- Seleccionar tipo de pago --</option>
                                </select>
                            </div>
                            <div class="sga-form-group">
                                <label for="sga_monto">Monto a Pagar <span>*</span></label>
                                <div class="monto-wrapper">
                                    <input type="text" id="sga_monto" readonly placeholder="RD$0.00">
                                    <span class="help-icon" title="El monto se calcula automáticamente al seleccionar un servicio.">?</span>
                                </div>
                            </div>
                            <button type="button" id="sga-proceed-payment" class="sga-submit-button" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v1h14V4a1 1 0 0 0-1-1H2zm13 4H1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7z"/><path d="M2 10a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1z"/></svg>
                                <span>Pagar con Tarjeta</span>
                            </button>
                        </form>
                    </div>
                    <div id="sga-payment-message"></div>
                </div>
                <?php if ($tipo_id === 'matricula'): ?>
                    <div class="sga-portal-right">
                        <?php $this->_render_payment_history($estudiante_post); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentItems = <?php echo json_encode($payment_items); ?>;
            const activeGateway = '<?php echo $active_gateway; ?>';
            const tipoPagoSelect = document.getElementById('sga_tipo_pago');
            const servicioSelect = document.getElementById('sga_servicio');
            const montoInput = document.getElementById('sga_monto');
            const proceedButton = document.getElementById('sga-proceed-payment');
            const paymentForm = document.getElementById('sga-payment-form');
            const pwTokenInput = document.getElementById('PWToken');
            let selectedItem = null;
            let cardnetInitialized = false;
            let serviceSelected = false;

            function updateButtonState() {
                if (activeGateway === 'cardnet') {
                    proceedButton.disabled = !(serviceSelected && cardnetInitialized);
                } else {
                    proceedButton.disabled = !serviceSelected;
                }
            }

            tipoPagoSelect.addEventListener('change', function() {
                const selectedType = this.value;
                servicioSelect.innerHTML = '<option value="">-- Seleccionar --</option>';
                montoInput.value = '';
                selectedItem = null;
                serviceSelected = false;
                updateButtonState();

                if (selectedType && paymentItems[selectedType]) {
                    paymentItems[selectedType].forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.label;
                        servicioSelect.appendChild(option);
                    });
                    servicioSelect.disabled = false;
                } else {
                    servicioSelect.disabled = true;
                }
            });

            servicioSelect.addEventListener('change', function() {
                const selectedType = tipoPagoSelect.value;
                const selectedId = this.value;
                
                selectedItem = null;
                montoInput.value = '';
                serviceSelected = false;

                if (selectedId && selectedType && paymentItems[selectedType]) {
                    selectedItem = paymentItems[selectedType].find(item => item.id === selectedId);
                    if (selectedItem) {
                        montoInput.value = selectedItem.amount_local;
                        serviceSelected = true;
                        
                        if (activeGateway === 'cardnet' && typeof PWCheckout !== 'undefined') {
                            PWCheckout.SetProperties({
                                "name": "<?php echo esc_js(get_bloginfo('name')); ?>",
                                "description": selectedItem.description,
                                "currency": "DOP",
                                "amount": selectedItem.amount_numeric,
                                "form_id": "sga-payment-form",
                                "checkout_card": 1,
                                "autoSubmit": "false"
                            });
                        }
                    }
                }
                updateButtonState();
            });

            function submitPaymentFormToServer() {
                if (!selectedItem) return;
                
                const commonFields = {
                    'CustomOrderId': selectedItem.custom_id,
                    'Amount': selectedItem.amount_numeric,
                    'Itbis': '0.00',
                    'OrderNumber': 'SGA-' + Date.now(),
                    'sga_action': 'initiate_payment'
                };

                for (const key in commonFields) {
                    let input = paymentForm.querySelector(`[name="${key}"]`);
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        paymentForm.appendChild(input);
                    }
                    input.value = commonFields[key];
                }
                
                paymentForm.action = window.location.href;
                paymentForm.submit();
            }

            if (activeGateway === 'cardnet') {
                const checkCardnetLibrary = setInterval(function() {
                    if (typeof PWCheckout !== 'undefined') {
                        clearInterval(checkCardnetLibrary);
                        
                        PWCheckout.Bind("tokenCreated", function(token) {
                            if (token && token.TokenId) {
                                pwTokenInput.value = token.TokenId;
                                submitPaymentFormToServer();
                            } else {
                                alert('Error al obtener el token de la tarjeta. Por favor, intente de nuevo.');
                            }
                        });

                        PWCheckout.AddActionButton('sga-proceed-payment');

                        cardnetInitialized = true;
                        updateButtonState();
                    }
                }, 100);

            } else { // Azul Gateway
                proceedButton.addEventListener('click', function() {
                    if (!selectedItem) {
                        alert('Por favor, selecciona un servicio válido.');
                        return;
                    }
                    submitPaymentFormToServer();
                });
            }
        });
        </script>
        <?php
        
        // This part handles the form submission to initiate payment
        if (isset($_POST['sga_action']) && $_POST['sga_action'] === 'initiate_payment') {
            $options = get_option('sga_payment_options');
            $active_gateway = $options['active_gateway'] ?? 'azul';
            $payment_data = [
                'OrderNumber'     => sanitize_text_field($_POST['OrderNumber']),
                'Amount'          => number_format(floatval($_POST['Amount']), 2, '.', ''),
                'Itbis'           => number_format(floatval($_POST['Itbis']), 2, '.', ''),
                'CustomOrderId'   => sanitize_text_field($_POST['CustomOrderId']),
                'TrxToken'        => sanitize_text_field($_POST['PWToken'] ?? ''),
            ];

            if ($active_gateway === 'azul') {
                $this->_redirect_to_azul($payment_data, $options);
            } elseif ($active_gateway === 'cardnet') {
                $this->_process_cardnet_purchase($payment_data, $options);
            } else {
                echo '<div id="sga-payment-message" class="error" style="display:block;">No se ha configurado una pasarela de pago válida.</div>';
            }
        }

        return ob_get_clean();
    }

    /**
     * Prepares and sends the user to the Azul payment gateway.
     */
    private function _redirect_to_azul($payment_data, $options) {
        $merchant_id = $options['azul_merchant_id'] ?? '';
        $auth_key = $options['azul_auth_key'] ?? '';
        $environment = $options['azul_environment'] ?? 'sandbox';

        if (empty($merchant_id) || empty($auth_key)) {
            echo '<div id="sga-payment-message" class="error" style="display:block;">La pasarela de pago Azul no está configurada. Contacte a la administración.</div>';
            return;
        }
        
        $hash_string = $merchant_id . $auth_key . $payment_data['OrderNumber'] . $payment_data['Amount'] . $payment_data['Itbis'];
        $auth_hash = hash('sha512', $hash_string);
        
        $gateway_url = $environment === 'live' 
            ? 'https://pagos.azul.com.do/payment/main' 
            : 'https://pruebas.azul.com.do/payment/main';

        $response_url = add_query_arg('sga_azul_response', '1', site_url('/'));

        ?>
        <div id="sga-payment-message" class="processing" style="display:block;">Redirigiendo a la pasarela de pago segura de Azul...</div>
        <form id="azul-redirect-form" action="<?php echo esc_url($gateway_url); ?>" method="post">
            <input type="hidden" name="MerchantId" value="<?php echo esc_attr($merchant_id); ?>">
            <input type="hidden" name="OrderNumber" value="<?php echo esc_attr($payment_data['OrderNumber']); ?>">
            <input type="hidden" name="Amount" value="<?php echo esc_attr($payment_data['Amount']); ?>">
            <input type="hidden" name="Itbis" value="<?php echo esc_attr($payment_data['Itbis']); ?>">
            <input type="hidden" name="AuthHash" value="<?php echo esc_attr($auth_hash); ?>">
            <input type="hidden" name="ResponseUrl" value="<?php echo esc_url($response_url); ?>">
            <input type="hidden" name="CustomOrderId" value="<?php echo esc_attr($payment_data['CustomOrderId']); ?>">
            <input type="hidden" name="UseCustomField1" value="1">
            <input type="hidden" name="CustomField1" value="<?php echo esc_attr($payment_data['CustomOrderId']); ?>">
        </form>
        <script> document.getElementById('azul-redirect-form').submit(); </script>
        <?php
    }

    /**
     * Processes Cardnet purchase via server-to-server API call using the token.
     */
    private function _process_cardnet_purchase($payment_data, $options) {
        $private_key = $options['cardnet_private_key'] ?? '';
        $environment = $options['cardnet_environment'] ?? 'sandbox';

        if (empty($private_key) || empty($payment_data['TrxToken'])) {
            wp_redirect(add_query_arg(['payment_status' => 'failed', 'message' => 'Configuración de Cardnet incompleta o token no recibido.'], site_url('/pagos/')));
            exit;
        }

        $api_url = $environment === 'production' 
            ? 'https://servicios.cardnet.com.do/servicios/tokens/v1/api/Purchase'
            : 'https://lab.cardnet.com.do/servicios/tokens/v1/api/Purchase';
        
        // Cardnet expects amount as integer (cents)
        $amount_in_cents = intval(floatval($payment_data['Amount']) * 100);

        $request_body = json_encode([
            'TrxToken' => $payment_data['TrxToken'],
            'Order' => $payment_data['OrderNumber'],
            'Amount' => $amount_in_cents,
            'Currency' => 'DOP',
            'Capture' => true,
            'DataDo' => [
                'Invoice' => $payment_data['OrderNumber']
            ]
        ]);

        $response = wp_remote_post($api_url, [
            'method'    => 'POST',
            'headers'   => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($private_key . ':')
            ],
            'body'      => $request_body,
            'timeout'   => 45,
        ]);
        
        if (is_wp_error($response)) {
            SGA_Utils::_log_activity('Error de Conexión Cardnet', 'Fallo al conectar con la API Purchase. Orden: ' . $payment_data['OrderNumber'] . ' Error: ' . $response->get_error_message());
            wp_redirect(add_query_arg(['payment_status' => 'failed', 'message' => 'Error de conexión con la pasarela.'], site_url('/pagos/')));
            exit;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        $_POST['TransactionId'] = $payment_data['OrderNumber'];
        $_POST['Amount'] = $payment_data['Amount'];
        $_POST['CustomField1'] = $payment_data['CustomOrderId'];
        
        if (isset($response_body['Transaction']['Status']) && $response_body['Transaction']['Status'] === 'Approved') {
            $_POST['ResponseCode'] = '00';
            $_POST['AuthorizationCode'] = $response_body['Transaction']['ApprovalCode'] ?? 'N/A';
            $_POST['ResponseMessage'] = 'Aprobada';
        } else {
            $_POST['ResponseCode'] = $response_body['Transaction']['Steps'][0]['ResponseCode'] ?? '99';
            $_POST['AuthorizationCode'] = '';
            $_POST['ResponseMessage'] = $response_body['Transaction']['Steps'][0]['ResponseMessage'] ?? 'Rechazada';
        }
        
        // Manually trigger the handler
        $this->handle_cardnet_response(true);
    }


    /**
     * Maneja la respuesta POST de la pasarela de Azul.
     */
    public function handle_azul_response() {
        if (!isset($_GET['sga_azul_response'])) return;

        $order_number = $_POST['OrderNumber'] ?? '';
        $amount = $_POST['Amount'] ?? '';
        $authorization_code = $_POST['AuthorizationCode'] ?? '';
        $response_code = $_POST['ResponseCode'] ?? '';
        $response_message = $_POST['ResponseMessage'] ?? '';
        $date_time = $_POST['DateTime'] ?? '';
        $rrn = $_POST['RRN'] ?? '';
        $custom_order_id = $_POST['CustomField1'] ?? '';
        $azul_transaction_id = $_POST['AzulTransactionId'] ?? '';
        $response_hash = $_POST['AuthHash'] ?? '';

        $options = get_option('sga_payment_options');
        $merchant_id = $options['azul_merchant_id'] ?? '';
        $auth_key = $options['azul_auth_key'] ?? '';
        
        $local_hash_string = $merchant_id . $auth_key . $order_number . $amount . $authorization_code . $response_code . $date_time . $rrn . $custom_order_id . $azul_transaction_id;
        $local_hash = hash('sha512', $local_hash_string);

        if (strtolower($local_hash) !== strtolower($response_hash)) {
            SGA_Utils::_log_activity('Error de Seguridad Azul', 'El AuthHash de respuesta no coincide. Orden: ' . $order_number);
            wp_die('Error de seguridad. La transacción no pudo ser verificada.');
        }

        if ($response_code === '00') {
            $this->process_successful_transaction($custom_order_id, $azul_transaction_id, $amount, 'Azul');
            $redirect_url = add_query_arg(['payment_status' => 'success', 'order' => $order_number], site_url('/pagos/'));
        } else {
            SGA_Utils::_log_activity('Pago Rechazado Azul', 'Orden: ' . $order_number . ' - Mensaje: ' . $response_message);
            $redirect_url = add_query_arg(['payment_status' => 'failed', 'message' => urlencode($response_message)], site_url('/pagos/'));
        }
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Maneja la respuesta de la pasarela de Cardnet.
     */
    public function handle_cardnet_response($is_server_call = false) {
        if (!isset($_GET['sga_cardnet_response']) && !$is_server_call) return;
        
        $response_code = $_POST['ResponseCode'] ?? '';
        $order_number = $_POST['TransactionId'] ?? '';
        $amount = $_POST['Amount'] ?? '';
        $authorization_code = $_POST['AuthorizationCode'] ?? '';
        $custom_order_id = $_POST['CustomField1'] ?? '';
        $response_message = $_POST['ResponseMessage'] ?? 'Error desconocido';

        if ($response_code === '00') {
            $this->process_successful_transaction($custom_order_id, $authorization_code, $amount, 'Cardnet');
            $redirect_url = add_query_arg(['payment_status' => 'success', 'order' => $order_number], site_url('/pagos/'));
        } else {
            SGA_Utils::_log_activity('Pago Rechazado Cardnet', 'Orden: ' . $order_number . ' - Mensaje: ' . $response_message . ' (' . $response_code . ')');
            $redirect_url = add_query_arg(['payment_status' => 'failed', 'message' => urlencode($response_message)], site_url('/pagos/'));
        }
        
        wp_redirect($redirect_url);
        exit;
    }


    /**
     * Procesa una transacción exitosa (común para todas las pasarelas).
     */
    private function process_successful_transaction($custom_order_id, $transaction_id, $amount, $gateway_name) {
        $parts = explode(':', $custom_order_id);
        if (count($parts) < 2) return;

        $payment_type = $parts[0];
        $student_id = null;
        $description = '';

        if ($payment_type === 'inscription' && isset($parts[1], $parts[2])) {
            $student_id = intval($parts[1]);
            $row_index = intval($parts[2]);
            $cursos = get_field('cursos_inscritos', $student_id);
            $curso_aprobado = $cursos[$row_index] ?? null;
            if ($curso_aprobado) {
                $description = 'Inscripción: ' . $curso_aprobado['nombre_curso'];
            }
        } elseif ($payment_type === 'general' && isset($parts[1], $parts[2])) {
            $concepto_id = intval($parts[1]);
            $student_id = intval($parts[2]);
            $concepto_post = get_post($concepto_id);
            if ($concepto_post) {
                $description = $concepto_post->post_title;
            }
        }

        if ($student_id) {
            $student_post = get_post($student_id);
            $payment_data = [
                'transaction_id' => $transaction_id,
                'amount'         => number_format(floatval($amount), 2),
                'currency'       => 'DOP',
                'description'    => $description,
                'gateway'        => $gateway_name,
                'student_id'     => $student_id,
                'student_name'   => $student_post->post_title,
                'student_email'  => get_field('email', $student_id),
                'student_cedula' => get_field('cedula', $student_id),
            ];

            $this->_handle_successful_payment($payment_data);

            if ($payment_type === 'inscription') {
                SGA_Utils::_aprobar_estudiante($student_id, $row_index, $payment_data['student_cedula'], $payment_data['student_name'], true, $payment_data);
            }
        }
    }


    /**
     * Procesa un pago exitoso: registra el CPT y envía el recibo.
     */
    private function _handle_successful_payment($payment_data) {
        $pago_post_id = wp_insert_post([
            'post_type'   => 'sga_pago',
            'post_title'  => 'Pago de ' . $payment_data['student_name'] . ' para ' . $payment_data['description'],
            'post_status' => 'publish',
        ]);

        if ($pago_post_id && !is_wp_error($pago_post_id)) {
            update_post_meta($pago_post_id, '_student_id', $payment_data['student_id']);
            update_post_meta($pago_post_id, '_student_name', $payment_data['student_name']);
            update_post_meta($pago_post_id, '_payment_description', $payment_data['description']);
            update_post_meta($pago_post_id, '_payment_amount', $payment_data['amount']);
            update_post_meta($pago_post_id, '_payment_currency', $payment_data['currency']);
            update_post_meta($pago_post_id, '_transaction_id', $payment_data['transaction_id']);
            update_post_meta($pago_post_id, '_gateway', $payment_data['gateway']);

            $invoice_data = [
                'invoice_id'          => $pago_post_id,
                'payment_date'        => get_the_date('Y-m-d H:i:s', $pago_post_id),
                'student_name'        => $payment_data['student_name'],
                'student_cedula'      => $payment_data['student_cedula'],
                'payment_description' => $payment_data['description'],
                'amount'              => $payment_data['amount'],
                'currency'            => $payment_data['currency'],
                'transaction_id'      => $payment_data['transaction_id'],
            ];
            $reports_handler = new SGA_Reports();
            $invoice_pdf = $reports_handler->_generate_payment_invoice_pdf($invoice_data);
            if ($invoice_pdf && !empty($payment_data['student_email'])) {
                SGA_Utils::_send_payment_receipt_email(
                    $payment_data['student_email'],
                    $invoice_pdf['pdf_data'],
                    $invoice_pdf['title'],
                    $invoice_pdf['filename']
                );
            }
            return true;
        }
        return false;
    }

    /**
     * Renderiza la tabla del historial de pagos para la página de pagos.
     */
    private function _render_payment_history($estudiante_post) {
        ?>
        <div class="sga-dashboard-section">
            <h4>Historial de Pagos</h4>
            <?php
            $pagos_query = get_posts([
                'post_type' => 'sga_pago',
                'posts_per_page' => -1,
                'meta_key' => '_student_id',
                'meta_value' => $estudiante_post->ID,
                'orderby' => 'date',
                'order' => 'DESC'
            ]);
            if ($pagos_query) {
                ?>
                <div class="sga-history-table-wrapper">
                    <table class="sga-history-table">
                        <thead><tr><th>Fecha</th><th>Concepto</th><th>Monto</th><th>Acción</th></tr></thead>
                        <tbody>
                            <?php foreach($pagos_query as $pago):
                                $print_url = add_query_arg([
                                    'action' => 'sga_print_invoice',
                                    'payment_id' => $pago->ID,
                                    '_wpnonce' => wp_create_nonce('sga_print_invoice_' . $pago->ID)
                                ], admin_url('admin-ajax.php'));
                            ?>
                            <tr>
                                <td><?php echo get_the_date('Y-m-d', $pago->ID); ?></td>
                                <td><?php echo esc_html(get_post_meta($pago->ID, '_payment_description', true)); ?></td>
                                <td><?php echo esc_html(get_post_meta($pago->ID, '_payment_amount', true)); ?> <?php echo esc_html(get_post_meta($pago->ID, '_payment_currency', true)); ?></td>
                                <td><a href="<?php echo esc_url($print_url); ?>" class="button button-secondary print-button" target="_blank">Imprimir</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
            } else {
                echo '<p>No tienes pagos registrados en tu historial.</p>';
            }
            ?>
        </div>
        <?php
    }
}

