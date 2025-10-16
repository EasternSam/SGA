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
        add_action('admin_menu', array($this, 'remove_menus_for_sga_roles'), 999); // Prioridad alta para limpiar el menú
        add_action('admin_init', array($this, 'register_plugin_settings'));
        add_action('admin_footer', array($this, 'add_realtime_notification_script'));
        
        // Hooks para la pantalla de bienvenida y redirección
        add_filter('login_redirect', array($this, 'redirect_sga_users_on_login'), 10, 3);
        add_action('admin_init', array($this, 'force_redirect_from_dashboard'));
        add_action('admin_head', array($this, 'custom_welcome_screen_styles'));
    }

    /**
     * Helper para verificar si el usuario actual tiene uno de los roles SGA.
     * @return bool
     */
    private function sga_user_has_sga_role() {
        $user = wp_get_current_user();
        if (!$user->ID) return false;
        $sga_roles = ['gestor_academico', 'agente', 'gestor_de_cursos'];
        $user_roles = (array) $user->roles;
        return !empty(array_intersect($sga_roles, $user_roles));
    }

    /**
     * Redirige a los usuarios con roles SGA a la página de bienvenida al iniciar sesión.
     */
    public function redirect_sga_users_on_login($redirect_to, $requested_redirect_to, $user) {
        if (isset($user->roles) && is_array($user->roles)) {
            if ($this->sga_user_has_sga_role()) {
                return admin_url('admin.php?page=sga_welcome');
            }
        }
        return $redirect_to;
    }

    /**
     * Fuerza la redirección desde el escritorio principal si un usuario SGA intenta acceder a él.
     */
    public function force_redirect_from_dashboard() {
        global $pagenow;
        if ($pagenow === 'index.php' && $this->sga_user_has_sga_role()) {
            if (!isset($_GET['page']) || $_GET['page'] !== 'sga_welcome') {
                wp_redirect(admin_url('admin.php?page=sga_welcome'));
                exit;
            }
        }
    }

    /**
     * Añade el menú principal y los submenús del plugin al panel de administración.
     */
    public function add_admin_menu_pages() {
        if ($this->sga_user_has_sga_role()) {
            // --- Menú para Roles SGA (Agente, Gestor de Cursos, etc.) ---
            add_menu_page(
                'Bienvenido',
                'Bienvenido',
                'read',
                'sga_welcome',
                array($this, 'render_sga_welcome_page'),
                'dashicons-admin-home',
                2
            );
        } else {
            // --- Menú para Administradores ---
            $pending_count = SGA_Utils::_get_pending_inscriptions_count();
            $notification_bubble = $pending_count > 0 ? ' <span class="awaiting-mod">' . $pending_count . '</span>' : '';

            add_menu_page(
                'Gestión Académica',
                'Gestión Académica' . $notification_bubble,
                'edit_estudiantes',
                'sga_dashboard',
                null, // Se usará el shortcode para el panel
                'dashicons-welcome-learn-more',
                25
            );
            add_submenu_page(
                'sga_dashboard',
                'Panel Principal',
                'Panel Principal',
                'edit_estudiantes',
                'sga_panel_redirect', // Slug falso para redirigir
                array($this, 'redirect_to_gestion_page')
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
                'edit_posts',
                'edit.php?post_type=curso'
            );
             add_submenu_page(
                'sga_dashboard',
                'Conceptos de Pago',
                'Conceptos de Pago',
                'manage_options',
                'edit.php?post_type=sga_concepto_pago'
            );
            add_submenu_page(
                'sga_dashboard',
                'Registro de Actividad',
                'Registro de Actividad',
                'manage_options',
                'edit.php?post_type=gestion_log'
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
    }

    /**
     * Elimina todos los menús no esenciales para los roles SGA.
     */
    public function remove_menus_for_sga_roles() {
        if ($this->sga_user_has_sga_role()) {
            remove_menu_page('index.php'); // Escritorio
            remove_menu_page('edit.php'); // Entradas
            remove_menu_page('upload.php'); // Medios
            remove_menu_page('edit.php?post_type=page'); // Páginas
            remove_menu_page('edit-comments.php'); // Comentarios
            remove_menu_page('themes.php'); // Apariencia
            remove_menu_page('plugins.php'); // Plugins
            remove_menu_page('users.php'); // Usuarios
            remove_menu_page('tools.php'); // Herramientas
            remove_menu_page('options-general.php'); // Ajustes
            remove_menu_page('profile.php'); // Perfil (se puede acceder desde la barra superior)

            // Limpieza adicional para menús de otros plugins
            global $menu;
            $allowed_slugs = ['sga_welcome', 'profile.php', 'wp-menu-separator1', 'wp-menu-separator2', 'wp-menu-separator-last'];
            foreach ($menu as $key => $item) {
                $slug = $item[2];
                if (!in_array($slug, $allowed_slugs)) {
                    remove_menu_page($slug);
                }
            }
        }
    }

    /**
     * Renderiza la página de bienvenida personalizada.
     */
    public function render_sga_welcome_page() {
        $user = wp_get_current_user();
        $logo_url = wp_get_attachment_url(5754); // Obtener la URL del logo desde la mediateca
        ?>
        <div class="sga-welcome-screen">
            <div class="sga-welcome-content">
                <?php if ($logo_url) : ?>
                    <img src="<?php echo esc_url($logo_url); ?>" alt="Logo CENTU" class="sga-welcome-logo"/>
                <?php endif; ?>
                <h1>¡Bienvenido, <?php echo esc_html($user->display_name); ?>!</h1>
                <p>Estás en el portal de gestión académica. Haz clic en el botón para acceder al panel principal.</p>
                <a href="<?php echo esc_url(home_url('/gestion')); ?>" class="sga-welcome-button">
                    Acceder al Panel de Gestión
                </a>
            </div>
        </div>
        <?php
    }

     /**
     * Añade CSS para ocultar la UI de WordPress en la pantalla de bienvenida.
     */
    public function custom_welcome_screen_styles() {
        if (isset($_GET['page']) && $_GET['page'] === 'sga_welcome') {
            echo '
            <style>
                /* Ocultar elementos de la UI de WordPress */
                #adminmenumain, #wpadminbar, #wpfooter, .notice, #wp-auth-check-wrap {
                    display: none !important;
                }
                /* Ajustar el contenedor de contenido */
                #wpcontent {
                    margin-left: 0 !important;
                    padding-left: 0 !important;
                }
                html.wp-toolbar {
                    padding-top: 0 !important;
                }
                /* Estilos de la pantalla de bienvenida */
                .sga-welcome-screen {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    background-color: #f0f2f5;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                }
                .sga-welcome-content {
                    text-align: center;
                    background: #ffffff;
                    padding: 50px 60px;
                    border-radius: 16px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                    max-width: 500px;
                }
                .sga-welcome-logo {
                    max-width: 200px;
                    margin-bottom: 30px;
                }
                .sga-welcome-content h1 {
                    font-size: 28px;
                    color: #141f53;
                    margin: 0 0 15px 0;
                }
                .sga-welcome-content p {
                    font-size: 16px;
                    color: #64748b;
                    margin: 0 0 30px 0;
                    line-height: 1.6;
                }
                .sga-welcome-button {
                    display: inline-block;
                    background-color: #4f46e5;
                    color: #ffffff;
                    padding: 15px 30px;
                    font-size: 16px;
                    font-weight: 600;
                    text-decoration: none;
                    border-radius: 8px;
                    transition: all 0.3s ease;
                }
                .sga-welcome-button:hover {
                    background-color: #141f53;
                    transform: translateY(-3px);
                    box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
                }
            </style>';
        }
    }
    
     /**
     * Redirige el submenú del panel a la página de gestión.
     */
    public function redirect_to_gestion_page() {
        wp_redirect(esc_url(home_url('/gestion')));
        exit;
    }

    /**
     * Añade el script para notificaciones en tiempo real en el footer del admin.
     */
    public function add_realtime_notification_script() {
        if ($this->sga_user_has_sga_role() || !current_user_can('edit_estudiantes')) return;

        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var sgaMenuLink = $('#toplevel_page_sga_dashboard > a');
            
            function checkPendingInscriptions() {
                $.post(ajaxurl, {
                    action: 'sga_check_pending_inscriptions',
                    security: '<?php echo wp_create_nonce("sga_pending_nonce"); ?>'
                }).done(function(response) {
                    if (response.success) {
                        var count = parseInt(response.data.count, 10);
                        var bubble = sgaMenuLink.find('.awaiting-mod');

                        if (count > 0) {
                            if (bubble.length) {
                                bubble.text(count);
                            } else {
                                sgaMenuLink.append(' <span class="awaiting-mod">' + count + '</span>');
                            }
                        } else {
                            bubble.remove();
                        }
                    }
                });
            }
            
            checkPendingInscriptions();
            setInterval(checkPendingInscriptions, 30000); 
        });
        </script>
        <?php
    }

    /**
     * Registra los grupos de opciones del plugin.
     */
    public function register_plugin_settings() {
        register_setting('sga_report_options_group', 'sga_report_options', array($this, 'sanitize_report_options'));
        register_setting('sga_main_settings_group', 'sga_payment_options', array($this, 'sanitize_payment_options'));
        register_setting('sga_main_settings_group', 'sga_integration_options', array($this, 'sanitize_integration_options'));
    }

    public function sanitize_report_options($input) {
        $sanitized_input = [];
        if (isset($input['recipient_email'])) $sanitized_input['recipient_email'] = sanitize_email($input['recipient_email']);
        $sanitized_input['enable_weekly'] = isset($input['enable_weekly']) ? 1 : 0;
        $sanitized_input['enable_monthly'] = isset($input['enable_monthly']) ? 1 : 0;
        return $sanitized_input;
    }
    public function sanitize_payment_options($input) {
        $sanitized_input = [];
        $sanitized_input['enable_payments'] = isset($input['enable_payments']) ? 1 : 0;
        if (isset($input['local_currency_symbol'])) $sanitized_input['local_currency_symbol'] = sanitize_text_field($input['local_currency_symbol']);
        if (isset($input['active_gateway'])) $sanitized_input['active_gateway'] = in_array($input['active_gateway'], ['azul', 'cardnet']) ? $input['active_gateway'] : 'azul';
        if (isset($input['azul_merchant_id'])) $sanitized_input['azul_merchant_id'] = sanitize_text_field($input['azul_merchant_id']);
        if (isset($input['azul_auth_key'])) $sanitized_input['azul_auth_key'] = sanitize_text_field($input['azul_auth_key']);
        if (isset($input['azul_environment'])) $sanitized_input['azul_environment'] = in_array($input['azul_environment'], ['sandbox', 'live']) ? $input['azul_environment'] : 'sandbox';
        if (isset($input['cardnet_public_key'])) $sanitized_input['cardnet_public_key'] = sanitize_text_field($input['cardnet_public_key']);
        if (isset($input['cardnet_private_key'])) $sanitized_input['cardnet_private_key'] = sanitize_text_field($input['cardnet_private_key']);
        if (isset($input['cardnet_environment'])) $sanitized_input['cardnet_environment'] = in_array($input['cardnet_environment'], ['sandbox', 'production']) ? $input['cardnet_environment'] : 'sandbox';
        return $sanitized_input;
    }
    public function sanitize_integration_options($input) {
        $sanitized_input = [];
        if (isset($input['webhook_url'])) $sanitized_input['webhook_url'] = esc_url_raw($input['webhook_url']);
        if (isset($input['student_query_url'])) $sanitized_input['student_query_url'] = esc_url_raw($input['student_query_url']);
        if (isset($input['api_secret_key'])) $sanitized_input['api_secret_key'] = sanitize_text_field($input['api_secret_key']);
        $sanitized_input['disable_auto_enroll'] = isset($input['disable_auto_enroll']) ? 1 : 0;
        return $sanitized_input;
    }

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
                    $this->render_debug_settings_tab();
                }
                ?>
            </form>
        </div>
        <?php
    }
    private function render_payment_settings_tab() {
        $options = get_option('sga_payment_options', []);
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
        <?php
    }
    private function render_integration_settings_tab() {
        $options = get_option('sga_integration_options', []);
        ?>
        <h2>Integración con Sistema Interno (Webhooks y API)</h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="webhook_url">URL del Webhook (Saliente)</label></th>
                <td>
                    <input type="url" id="webhook_url" name="sga_integration_options[webhook_url]" value="<?php echo esc_attr($options['webhook_url'] ?? ''); ?>" class="regular-text" />
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="student_query_url">URL de Consulta de Estudiantes (Entrante)</label></th>
                <td>
                    <input type="url" id="student_query_url" name="sga_integration_options[student_query_url]" value="<?php echo esc_attr($options['student_query_url'] ?? ''); ?>" class="regular-text" />
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="api_secret_key">Clave Secreta de la API</label></th>
                <td>
                    <input type="password" id="api_secret_key" name="sga_integration_options[api_secret_key]" value="<?php echo esc_attr($options['api_secret_key'] ?? ''); ?>" class="regular-text" />
                </td>
            </tr>
             <tr valign="top">
                <th scope="row">Matriculación Automática por Webhook</th>
                <td>
                    <label for="disable_auto_enroll">
                        <input type="checkbox" id="disable_auto_enroll" name="sga_integration_options[disable_auto_enroll]" value="1" <?php checked(1, $options['disable_auto_enroll'] ?? 0, true); ?> />
                        Desactivar matriculación automática
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }
    private function render_reports_settings_tab() {
         $options = get_option('sga_report_options', []);
        ?>
        <h2>Reportes Automáticos</h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="recipient_email">Correo Receptor</label></th>
                <td>
                    <input type="email" id="recipient_email" name="sga_report_options[recipient_email]" value="<?php echo esc_attr($options['recipient_email']); ?>" class="regular-text" />
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Reporte Semanal</th>
                <td>
                    <label for="enable_weekly"><input type="checkbox" id="enable_weekly" name="sga_report_options[enable_weekly]" value="1" <?php checked(1, $options['enable_weekly'], true); ?> /> Activar reporte semanal.</label>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Reporte Mensual</th>
                <td>
                    <label for="enable_monthly"><input type="checkbox" id="enable_monthly" name="sga_report_options[enable_monthly]" value="1" <?php checked(1, $options['enable_monthly'], true); ?> /> Activar reporte mensual.</label>
                </td>
            </tr>
        </table>
        <?php
    }
    private function render_debug_settings_tab() {
        ?>
        <h2>Herramientas de Debug para la Integración</h2>
        <div id="sga-debug-wrapper">
            <div id="sga-debug-tools">
                <h3>Simulador de Webhook Entrante</h3>
                <table class="form-table">
                    <tr><th><label for="sga-sim-cedula">Cédula</label></th><td><input type="text" id="sga-sim-cedula" /></td></tr>
                    <tr><th><label for="sga-sim-curso">Curso</label></th><td><input type="text" id="sga-sim-curso" /></td></tr>
                    <tr><th>Acción</th><td><button type="button" id="sga-test-webhook-btn">Simular</button></td></tr>
                </table>
                <h3>Prueba de Conexión Saliente</h3>
                 <table>
                     <tr><th>Probar</th><td><button type="button" id="sga-test-connection-btn">Iniciar Prueba</button></td></tr>
                 </table>
                 <div id="sga-test-results"></div>
            </div>
            <div id="sga-debug-log">
                 <h3>Registro de la API</h3>
                <textarea readonly><?php
                    $log_query = new WP_Query(['post_type' => 'gestion_log', 'posts_per_page' => 50, 'orderby' => 'date', 'order' => 'DESC', 'meta_query' => [['key' => '_is_api_log', 'value' => '1']]]);
                    if ($log_query->have_posts()) { while($log_query->have_posts()) { $log_query->the_post(); echo '[' . get_the_date('Y-m-d H:i:s') . '] ' . esc_html(get_the_title()) . "\n" . esc_html(get_the_content()) . "\n\n"; } wp_reset_postdata(); }
                    else { echo 'No hay registros de la API.'; }
                ?></textarea>
            </div>
        </div>
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
}

