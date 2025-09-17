<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_Admin
 *
 * Gestiona todo lo relacionado con el panel de administración de WordPress:
 * menús, submenús, páginas de ajustes y renderizado de las vistas de admin.
 */
class SGA_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu_pages'));
        add_action('admin_init', array($this, 'register_plugin_settings'));
    }

    /**
     * Añade el menú principal y los submenús del plugin al panel de administración.
     */
    public function add_admin_menu_pages() {
        add_menu_page(
            'Gestión Académica',
            'Gestión Académica',
            'edit_estudiantes',
            'sga_dashboard',
            array($this, 'render_admin_approval_page'),
            'dashicons-welcome-learn-more',
            25
        );
        add_submenu_page(
            'sga_dashboard',
            'Aprobar Inscripciones',
            'Aprobar Inscripciones',
            'edit_estudiantes',
            'sga_dashboard' // This makes it the default page
        );
        add_submenu_page(
            'sga_dashboard',
            'Estudiantes',
            'Estudiantes',
            'edit_estudiantes',
            'edit.php?post_type=estudiante'
        );
         add_submenu_page(
            'sga_dashboard',
            'Cursos',
            'Cursos',
            'edit_posts', // Assuming gestor can edit courses
            'edit.php?post_type=curso'
        );
        add_submenu_page(
            'sga_dashboard',
            'Registro de Actividad',
            'Registro de Actividad',
            'manage_options', // Only admins should see this
            'edit.php?post_type=gestion_log'
        );
        // --- Separator ---
        add_submenu_page(
            'sga_dashboard',
            null, // No title for separator
            '<span style="display:block; margin:1px 0 1px -5px; padding:0; height:1px; line-height:1px; background:#4f5458;"></span>',
            'manage_options',
            '#'
        );
        add_submenu_page(
            'sga_dashboard',
            'Ajustes',
            'Ajustes',
            'manage_options',
            'sga-settings',
            array($this, 'render_main_settings_page')
        );
    }

    /**
     * Registra los grupos de opciones del plugin.
     */
    public function register_plugin_settings() {
        // Report settings
        register_setting('sga_report_options_group', 'sga_report_options', array($this, 'sanitize_report_options'));
        // Payment and Integration settings in one group
        register_setting('sga_main_settings_group', 'sga_payment_options', array($this, 'sanitize_payment_options'));
        register_setting('sga_main_settings_group', 'sga_integration_options', array($this, 'sanitize_integration_options'));
    }

    /**
     * Sanitiza las opciones de reportes.
     */
    public function sanitize_report_options($input) {
        $sanitized_input = [];
        if (isset($input['recipient_email'])) $sanitized_input['recipient_email'] = sanitize_email($input['recipient_email']);
        $sanitized_input['enable_weekly'] = isset($input['enable_weekly']) ? 1 : 0;
        $sanitized_input['enable_monthly'] = isset($input['enable_monthly']) ? 1 : 0;
        return $sanitized_input;
    }

    /**
     * Sanitiza las opciones de pagos.
     */
    public function sanitize_payment_options($input) {
        $sanitized_input = [];
        // General
        $sanitized_input['enable_payments'] = isset($input['enable_payments']) ? 1 : 0;
        if (isset($input['local_currency_symbol'])) $sanitized_input['local_currency_symbol'] = sanitize_text_field($input['local_currency_symbol']);
        if (isset($input['active_gateway'])) $sanitized_input['active_gateway'] = in_array($input['active_gateway'], ['azul', 'cardnet']) ? $input['active_gateway'] : 'azul';
        // Azul
        if (isset($input['azul_merchant_id'])) $sanitized_input['azul_merchant_id'] = sanitize_text_field($input['azul_merchant_id']);
        if (isset($input['azul_auth_key'])) $sanitized_input['azul_auth_key'] = sanitize_text_field($input['azul_auth_key']);
        if (isset($input['azul_environment'])) $sanitized_input['azul_environment'] = in_array($input['azul_environment'], ['sandbox', 'live']) ? $input['azul_environment'] : 'sandbox';
        // Cardnet
        if (isset($input['cardnet_public_key'])) $sanitized_input['cardnet_public_key'] = sanitize_text_field($input['cardnet_public_key']);
        if (isset($input['cardnet_private_key'])) $sanitized_input['cardnet_private_key'] = sanitize_text_field($input['cardnet_private_key']);
        if (isset($input['cardnet_environment'])) $sanitized_input['cardnet_environment'] = in_array($input['cardnet_environment'], ['sandbox', 'production']) ? $input['cardnet_environment'] : 'sandbox';

        return $sanitized_input;
    }
    
    /**
     * Sanitiza las opciones de integración.
     */
    public function sanitize_integration_options($input) {
        $sanitized_input = [];
        if (isset($input['webhook_url'])) $sanitized_input['webhook_url'] = esc_url_raw($input['webhook_url']);
        if (isset($input['student_query_url'])) $sanitized_input['student_query_url'] = esc_url_raw($input['student_query_url']);
        if (isset($input['api_secret_key'])) $sanitized_input['api_secret_key'] = sanitize_text_field($input['api_secret_key']);
        return $sanitized_input;
    }
    
    /**
     * Renderiza la página principal de ajustes con pestañas.
     */
    public function render_main_settings_page() {
        if (!current_user_can('manage_options')) wp_die(__('No tienes permisos para acceder a esta página.'));
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'pagos';
        ?>
        <div class="wrap">
            <h1>Ajustes de Gestión Académica</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=sga-settings&tab=pagos" class="nav-tab <?php echo $active_tab == 'pagos' ? 'nav-tab-active' : ''; ?>">Pagos y Moneda</a>
                <a href="?page=sga-settings&tab=integracion" class="nav-tab <?php echo $active_tab == 'integracion' ? 'nav-tab-active' : ''; ?>">Integración con Sistema Interno (API)</a>
                <a href="?page=sga-settings&tab=reportes" class="nav-tab <?php echo $active_tab == 'reportes' ? 'nav-tab-active' : ''; ?>">Reportes Automáticos</a>
                <a href="?page=sga-settings&tab=debug" class="nav-tab <?php echo $active_tab == 'debug' ? 'nav-tab-active' : ''; ?>">Debug de la API</a>
            </h2>
            <form method="post" action="options.php">
                <?php
                if ($active_tab == 'pagos') {
                    settings_fields('sga_main_settings_group');
                    $this->render_payment_settings_tab();
                    submit_button('Guardar Cambios');
                } elseif ($active_tab == 'integracion') {
                    settings_fields('sga_main_settings_group');
                    $this->render_integration_settings_tab();
                    submit_button('Guardar Cambios');
                } elseif ($active_tab == 'reportes') {
                    settings_fields('sga_report_options_group');
                    $this->render_reports_settings_tab();
                    submit_button('Guardar Cambios');
                } elseif ($active_tab == 'debug') {
                    // No options group needed for debug tab
                    $this->render_debug_settings_tab();
                    // No submit button needed here
                }
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Renderiza la pestaña de ajustes de pagos.
     */
    private function render_payment_settings_tab() {
        $options = get_option('sga_payment_options', [
            'enable_payments' => 1, 'local_currency_symbol' => 'DOP', 'active_gateway' => 'cardnet',
            'azul_merchant_id' => '', 'azul_auth_key' => '', 'azul_environment' => 'sandbox',
            'cardnet_public_key' => '', 'cardnet_private_key' => '', 'cardnet_environment' => 'sandbox',
        ]);
        ?>
        <h2>Configuración de Pagos Online</h2>
        <p>Activa o desactiva los pagos en línea y configura las credenciales de tu pasarela.</p>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Pagos Online</th>
                <td>
                    <label for="enable_payments">
                        <input type="checkbox" id="enable_payments" name="sga_payment_options[enable_payments]" value="1" <?php checked(1, $options['enable_payments'] ?? 1, true); ?> />
                        Activar el portal de pagos en línea
                    </label>
                    <p class="description">Si se desactiva, las inscripciones requerirán aprobación manual y el portal de pagos no estará disponible.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="local_currency_symbol">Símbolo Moneda Local</label></th>
                <td>
                    <input type="text" id="local_currency_symbol" name="sga_payment_options[local_currency_symbol]" value="<?php echo esc_attr($options['local_currency_symbol'] ?? 'DOP'); ?>" class="small-text" placeholder="DOP" />
                    <p class="description">El símbolo de la moneda para mostrar en la web (ej. DOP, RD$).</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="active_gateway">Pasarela de Pago Activa</label></th>
                <td>
                    <select id="active_gateway" name="sga_payment_options[active_gateway]">
                        <option value="azul" <?php selected($options['active_gateway'] ?? 'azul', 'azul'); ?>>Azul</option>
                        <option value="cardnet" <?php selected($options['active_gateway'] ?? 'azul', 'cardnet'); ?>>Cardnet</option>
                    </select>
                </td>
            </tr>
        </table>
        <hr>
        <h3>Pasarela de Pago: Azul</h3>
        <table class="form-table">
            <tr valign="top"><th scope="row">... (campos de Azul) ...</th><td>...</td></tr>
        </table>
        <hr>
        <h3>Pasarela de Pago: Cardnet</h3>
        <table class="form-table">
            <tr valign="top"><th scope="row">... (campos de Cardnet) ...</th><td>...</td></tr>
        </table>
        <?php
    }
    
    /**
     * Renderiza la pestaña de ajustes de integración.
     */
    private function render_integration_settings_tab() {
        $options = get_option('sga_integration_options', [
            'webhook_url' => '', 'student_query_url' => '', 'api_secret_key' => ''
        ]);
        ?>
        <h2>Integración con Sistema Interno (Webhooks y API)</h2>
        <p>Configura la comunicación entre este plugin y tu sistema de gestión interno.</p>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="webhook_url">URL del Webhook (Saliente)</label></th>
                <td>
                    <input type="url" id="webhook_url" name="sga_integration_options[webhook_url]" value="<?php echo esc_attr($options['webhook_url'] ?? ''); ?>" class="regular-text" placeholder="https://api.sistemainterno.com/nueva-inscripcion" />
                    <p class="description">**Función:** Notificar a tu sistema interno cuando un nuevo estudiante se inscribe en la web.<br>El SGA enviará una petición <code>POST</code> a esta URL con los datos de la inscripción.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="student_query_url">URL de Consulta de Estudiantes (Entrante)</label></th>
                <td>
                    <input type="url" id="student_query_url" name="sga_integration_options[student_query_url]" value="<?php echo esc_attr($options['student_query_url'] ?? ''); ?>" class="regular-text" placeholder="https://api.sistemainterno.com/students" />
                    <p class="description">**Función:** Permite al SGA buscar si un estudiante ya existe en tu sistema interno antes de crear uno nuevo.<br>El SGA hará una petición <code>GET</code> a esta URL, añadiendo la cédula al final (ej: <code>.../students?cedula=0010020034</code>).</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="api_secret_key">Clave Secreta de la API</label></th>
                <td>
                    <input type="password" id="api_secret_key" name="sga_integration_options[api_secret_key]" value="<?php echo esc_attr($options['api_secret_key'] ?? ''); ?>" class="regular-text" />
                    <p class="description">**Función:** Asegura toda la comunicación entre el SGA y tu sistema interno.<br>Esta clave se enviará en la cabecera <code>X-SGA-Signature</code> en cada petición.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Renderiza la pestaña de ajustes de reportes.
     */
    private function render_reports_settings_tab() {
         $options = get_option('sga_report_options', [
            'recipient_email' => get_option('admin_email'), 'enable_weekly' => 0, 'enable_monthly' => 0,
        ]);
        ?>
        <h2>Reportes Automáticos</h2>
        <p>Configura los reportes programados que se enviarán al correo especificado.</p>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="recipient_email">Correo Receptor</label></th>
                <td>
                    <input type="email" id="recipient_email" name="sga_report_options[recipient_email]" value="<?php echo esc_attr($options['recipient_email']); ?>" class="regular-text" />
                    <p class="description">El correo donde se recibirán todos los reportes automáticos.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Reporte Semanal</th>
                <td>
                    <label for="enable_weekly"><input type="checkbox" id="enable_weekly" name="sga_report_options[enable_weekly]" value="1" <?php checked(1, $options['enable_weekly'], true); ?> /> Activar reporte semanal de matriculados y pendientes.</label>
                    <p class="description">Se enviará cada lunes a las 2:00 AM.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Reporte Mensual</th>
                <td>
                    <label for="enable_monthly"><input type="checkbox" id="enable_monthly" name="sga_report_options[enable_monthly]" value="1" <?php checked(1, $options['enable_monthly'], true); ?> /> Activar reporte mensual completo.</label>
                    <p class="description">Se enviará el primer día de cada mes a las 2:00 AM.</p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Renderiza la pestaña de debug de la API.
     */
    private function render_debug_settings_tab() {
        ?>
        <h2>Herramientas de Debug para la Integración</h2>
        <p>Utiliza estas herramientas para verificar la conexión con tu sistema interno y ver los registros de comunicación.</p>

        <div id="sga-debug-wrapper">
            <div id="sga-debug-tools">
                <h3>Simulador de Webhook Entrante</h3>
                <p>Simula una petición de tu sistema interno al SGA para marcar una inscripción como pagada y matricular al estudiante.</p>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="sga-sim-cedula">Cédula del Estudiante</label></th>
                        <td><input type="text" id="sga-sim-cedula" class="regular-text" placeholder="Ej: 0010020034" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="sga-sim-curso">Nombre del Curso</label></th>
                        <td><input type="text" id="sga-sim-curso" class="regular-text" placeholder="Ej: Diseño Gráfico Avanzado" /></td>
                    </tr>
                    <tr valign="top">
                         <th scope="row">Acción</th>
                        <td>
                            <button type="button" id="sga-test-webhook-btn" class="button button-primary">Simular Recepción de Pago</button>
                            <span class="spinner" style="float: none; vertical-align: middle;"></span>
                        </td>
                    </tr>
                </table>
                <hr>
                <h3>Prueba de Conexión Saliente</h3>
                <p>Verifica que el SGA puede comunicarse con las URLs de tu sistema interno.</p>
                 <table class="form-table">
                     <tr valign="top">
                        <th scope="row">Probar Conexión</th>
                        <td>
                            <button type="button" id="sga-test-connection-btn" class="button button-secondary">Iniciar Prueba de Conexión</button>
                            <span class="spinner" style="float: none; vertical-align: middle;"></span>
                        </td>
                    </tr>
                 </table>
                 <div id="sga-test-results" style="margin-top: 15px; padding: 12px; border: 1px solid #dcdcde; background: #f6f7f7; display: none; border-radius: 4px;"></div>
            </div>

            <div id="sga-debug-log">
                 <h3>Registro de la API</h3>
                <textarea readonly style="width: 100%; height: 500px; background: #282c34; color: #abb2bf; font-family: monospace; font-size: 12px; white-space: pre; border-radius: 4px; padding: 10px;"><?php
                    $log_query = new WP_Query([
                        'post_type' => 'gestion_log',
                        'posts_per_page' => 50,
                        'orderby' => 'date',
                        'order' => 'DESC',
                        'meta_query' => [
                            [
                                'key' => '_is_api_log',
                                'value' => '1',
                                'compare' => '=',
                            ],
                        ],
                    ]);
                    if ($log_query->have_posts()) {
                        while($log_query->have_posts()) {
                            $log_query->the_post();
                            echo '[' . get_the_date('Y-m-d H:i:s') . '] ' . esc_html(get_the_title()) . "\n";
                            echo esc_html(get_the_content()) . "\n\n";
                        }
                        wp_reset_postdata();
                    } else {
                        echo 'No hay registros de la API todavía.';
                    }
                ?></textarea>
                <p class="description">Muestra las últimas 50 entradas del registro relacionadas con la API. <a href="<?php echo admin_url('edit.php?post_type=gestion_log'); ?>">Ver todos los registros</a>.</p>
            </div>
        </div>
        <style>
            #sga-debug-wrapper { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
            #sga-debug-tools, #sga-debug-log { background: #fff; padding: 20px; border: 1px solid #dcdcde; border-radius: 4px; }
            @media (max-width: 960px) { #sga-debug-wrapper { grid-template-columns: 1fr; } }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Test Conexión Saliente
            $('#sga-test-connection-btn').on('click', function() {
                var btn = $(this);
                var spinner = btn.next('.spinner');
                var resultsDiv = $('#sga-test-results');

                btn.prop('disabled', true);
                spinner.addClass('is-active');
                resultsDiv.slideUp().html('');

                $.post(ajaxurl, {
                    action: 'sga_test_api_connection',
                    _ajax_nonce: '<?php echo wp_create_nonce("sga_test_api_nonce"); ?>'
                }).done(function(response) {
                    if (response.success) {
                        resultsDiv.css('color', 'green').html('<strong>Prueba completada.</strong> Los resultados han sido añadidos al registro. Recarga la página para ver el log actualizado.').slideDown();
                    } else {
                        resultsDiv.css('color', 'red').html('<strong>Error:</strong> ' + response.data.message).slideDown();
                    }
                }).fail(function() {
                    resultsDiv.css('color', 'red').html('<strong>Error:</strong> Hubo un fallo de comunicación al intentar realizar la prueba.').slideDown();
                }).always(function() {
                    btn.prop('disabled', false);
                    spinner.removeClass('is-active');
                    setTimeout(function() { location.reload(); }, 3000);
                });
            });

            // Simular Webhook Entrante
            $('#sga-test-webhook-btn').on('click', function() {
                var btn = $(this);
                var spinner = btn.next('.spinner');
                var resultsDiv = $('#sga-test-results');

                var cedula = $('#sga-sim-cedula').val();
                var curso = $('#sga-sim-curso').val();

                if (!cedula || !curso) {
                    alert('Por favor, ingresa la cédula y el nombre del curso para la simulación.');
                    return;
                }

                btn.prop('disabled', true);
                spinner.addClass('is-active');
                resultsDiv.slideUp().html('');

                 $.post(ajaxurl, {
                    action: 'sga_test_incoming_webhook',
                    _ajax_nonce: '<?php echo wp_create_nonce("sga_test_api_nonce"); ?>',
                    cedula: cedula,
                    curso: curso
                }).done(function(response) {
                    var message = '';
                    if (response.success) {
                        message = '<strong>Simulación Exitosa.</strong><br>';
                        message += 'Código de Respuesta: ' + response.data.response_code + '<br>';
                        message += 'Cuerpo de la Respuesta: ' + JSON.stringify(response.data.response_body, null, 2);
                        resultsDiv.css('color', 'green').html('<pre>' + message + '</pre>').slideDown();
                    } else {
                        message = '<strong>Error en la Simulación:</strong> ' + response.data.message;
                        resultsDiv.css('color', 'red').html(message).slideDown();
                    }
                }).fail(function() {
                     resultsDiv.css('color', 'red').html('<strong>Error:</strong> Hubo un fallo de comunicación al intentar realizar la simulación.').slideDown();
                }).always(function() {
                    btn.prop('disabled', false);
                    spinner.removeClass('is-active');
                    setTimeout(function() { location.reload(); }, 5000);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Renderiza la página nativa de WP para aprobar inscripciones.
     */
    public function render_admin_approval_page() {
        $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $course_filter = isset($_GET['curso_filtro']) ? sanitize_text_field($_GET['curso_filtro']) : '';
        $inscripciones_pendientes = [];
        $cursos_disponibles_en_pendientes = [];
        $estudiantes_query = get_posts(array('post_type' => 'estudiante', 'posts_per_page' => -1));

        if ($estudiantes_query && function_exists('get_field')) {
            foreach ($estudiantes_query as $estudiante) {
                $cursos = get_field('cursos_inscritos', $estudiante->ID);
                if ($cursos) {
                    foreach ($cursos as $index => $curso) {
                        if (isset($curso['estado']) && $curso['estado'] == 'Inscrito') {
                            if (!isset($cursos_disponibles_en_pendientes[$curso['nombre_curso']])) {
                                $cursos_disponibles_en_pendientes[$curso['nombre_curso']] = $curso['nombre_curso'];
                            }
                            $match_curso = empty($course_filter) || $curso['nombre_curso'] === $course_filter;
                            $cedula = get_field('cedula', $estudiante->ID);
                            $email = get_field('email', $estudiante->ID);
                            $texto_busqueda_fila = $estudiante->post_title . ' ' . $cedula . ' ' . $email;
                            $match_busqueda = empty($search_term) || stripos($texto_busqueda_fila, $search_term) !== false;
                            if ($match_curso && $match_busqueda) {
                                $inscripciones_pendientes[] = ['estudiante' => $estudiante, 'curso' => $curso, 'row_index' => $index];
                            }
                        }
                    }
                }
            }
        }
        ksort($cursos_disponibles_en_pendientes);
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Aprobar Inscripciones Pendientes</h1>
            <?php
            if (isset($_GET['approved'])) {
                $count = intval($_GET['approved']);
                $message = sprintf(_n('%s estudiante aprobado exitosamente.', '%s estudiantes aprobados exitosamente.', $count, 'sga-plugin'), number_format_i18n($count));
                echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
            }
            ?>
            <form method="get">
                <input type="hidden" name="page" value="sga_dashboard">
                <p class="search-box">
                    <label class="screen-reader-text" for="post-search-input">Buscar Inscripción:</label>
                    <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($search_term); ?>">
                    <input type="submit" id="search-submit" class="button" value="Buscar Inscripción">
                </p>
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <label for="filter-by-course" class="screen-reader-text">Filtrar por curso</label>
                        <select name="curso_filtro" id="filter-by-course">
                            <option value="">Todos los cursos</option>
                            <?php foreach ($cursos_disponibles_en_pendientes as $nombre_curso) : ?>
                                <option value="<?php echo esc_attr($nombre_curso); ?>" <?php selected($course_filter, $nombre_curso); ?>>
                                    <?php echo esc_html($nombre_curso); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="submit" name="filter_action" id="post-query-submit" class="button" value="Filtrar">
                    </div>
                    <br class="clear">
                </div>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="sga_approve_bulk">
                <?php wp_nonce_field('sga_approve_bulk_nonce', '_wpnonce_bulk_approve'); ?>
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-top" class="screen-reader-text">Seleccionar acción en lote</label>
                        <select name="bulk_action" id="bulk-action-selector-top">
                            <option value="-1">Acciones en lote</option>
                            <option value="aprobar_lote">Aprobar</option>
                        </select>
                        <input type="submit" id="doaction" class="button action" value="Aplicar">
                    </div>
                    <br class="clear">
                </div>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td id="cb" class="manage-column column-cb check-column"><input id="cb-select-all-1" type="checkbox"></td>
                            <th>Nombre</th><th>Cédula</th><th>Email</th><th>Teléfono</th><th>Curso</th><th>Horario</th><th>Estado</th><th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($inscripciones_pendientes)) : ?>
                            <?php
                            foreach ($inscripciones_pendientes as $data) :
                                $estudiante = $data['estudiante'];
                                $curso = $data['curso'];
                                $index = $data['row_index'];
                                $nonce = wp_create_nonce('sga_approve_nonce_' . $estudiante->ID . '_' . $index);
                                $link = admin_url('admin-post.php?action=sga_approve_single&post_id=' . $estudiante->ID . '&row_index=' . $index . '&_wpnonce=' . $nonce);
                                ?>
                                <tr>
                                    <th scope="row" class="check-column"><input id="cb-select-<?php echo esc_attr($estudiante->ID . '-' . $index); ?>" type="checkbox" name="inscripciones_a_aprobar[]" value="<?php echo esc_attr($estudiante->ID . ':' . $index); ?>"></th>
                                    <td><?php echo esc_html($estudiante->post_title); ?></td>
                                    <td><?php echo esc_html(get_field('cedula', $estudiante->ID)); ?></td>
                                    <td><?php echo esc_html(get_field('email', $estudiante->ID)); ?></td>
                                    <td><?php echo esc_html(get_field('telefono', $estudiante->ID)); ?></td>
                                    <td><?php echo esc_html($curso['nombre_curso']); ?></td>
                                    <td><?php echo esc_html($curso['horario']); ?></td>
                                    <td><span style="color: #f59e0b; font-weight: bold;">Inscrito</span></td>
                                    <td><a href="<?php echo esc_url($link); ?>" class="button button-primary">Aprobar</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="9">No se encontraron inscripciones pendientes con los filtros aplicados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
        <?php
    }
}

