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
        
        // Registrar el AJAX handler para la prueba de conexión SALIENTE (WP -> Laravel)
        add_action('wp_ajax_sga_test_laravel_v1_connection', array($this, 'ajax_test_laravel_v1_connection'));
        
        // --- AÑADIDO: Registrar el AJAX handler para la simulación ENTRANTE (WP -> WP) ---
        add_action('wp_ajax_sga_test_incoming_webhook', array($this, 'ajax_test_incoming_webhook'));
        
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
        // ROL AGENTE INFOTEP AÑADIDO AQUÍ
        $sga_roles = ['gestor_academico', 'agente', 'agente_infotep', 'gestor_de_cursos'];
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
        
        // --- INICIO DE LA CORRECCIÓN ---
        // Asegurarnos de que la clase SGA_Utils esté disponible antes de usarla
        if (!class_exists('SGA_Utils')) {
            $utils_file = plugin_dir_path(__FILE__) . 'class-sga-utils.php';
            if (file_exists($utils_file)) {
                require_once $utils_file;
            } else {
                // Si el archivo no existe, no podemos continuar.
                return; 
            }
        }
        // --- FIN DE LA CORRECCIÓN ---

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
            
            // *** INICIO MODIFICACIÓN: Obtener conteos divididos y usar el total ***
            $pending_counts = SGA_Utils::_get_pending_inscriptions_counts();
            $total_pending = $pending_counts['total']; // Usar el total para la burbuja
            $notification_bubble = $total_pending > 0 ? ' <span class="awaiting-mod">' . $total_pending . '</span>' : '';
            // *** FIN MODIFICACIÓN ***

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
                        // *** INICIO MODIFICACIÓN: Esperar 'total' en lugar de 'count' ***
                        var count = parseInt(response.data.total, 10);
                        // *** FIN MODIFICACIÓN ***
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
     *
     * MODIFICADO: Añadido registro para sga_api_url y sga_api_key.
     */
    public function register_plugin_settings() {
        register_setting('sga_report_options_group', 'sga_report_options', array($this, 'sanitize_report_options'));
        
        // Grupo principal para Pestaña Pagos e Integración
        register_setting('sga_main_settings_group', 'sga_payment_options', array($this, 'sanitize_payment_options'));
        register_setting('sga_main_settings_group', 'sga_integration_options', array($this, 'sanitize_integration_options'));
        
        // --- AÑADIDOS ---
        // Registrar las opciones de API (saliente) para que se guarden desde el mismo grupo
        register_setting('sga_main_settings_group', 'sga_api_url', 'esc_url_raw');
        register_setting('sga_main_settings_group', 'sga_api_key', 'sanitize_text_field');
        // --- FIN AÑADIDOS ---
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

    /**
     * Sanitiza las opciones de integración.
     *
     * MODIFICADO: Eliminadas las claves de API, que ahora se guardan por separado.
     * Solo se mantiene 'disable_auto_enroll'.
     */
    public function sanitize_integration_options($input) {
        $sanitized_input = [];
        // if (isset($input['webhook_url'])) $sanitized_input['webhook_url'] = esc_url_raw($input['webhook_url']); // Eliminado
        // if (isset($input['student_query_url'])) $sanitized_input['student_query_url'] = esc_url_raw($input['student_query_url']); // Eliminado
        // if (isset($input['api_secret_key'])) $sanitized_input['api_secret_key'] = sanitize_text_field($input['api_secret_key']); // Eliminado
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
                <!-- MODIFICADO: Título de la pestaña -->
                <a href="?page=sga-settings&tab=integracion" class="nav-tab <?php echo $active_tab == 'integracion' ? 'nav-tab-active' : ''; ?>">Integración con Laravel (API)</a>
                <a href="?page=sga-settings&tab=reportes" class="nav-tab <?php echo $active_tab == 'reportes' ? 'nav-tab-active' : ''; ?>">Reportes Automáticos</a>
                <a href="?page=sga-settings&tab=debug" class="nav-tab <?php echo $active_tab == 'debug' ? 'nav-tab-active' : ''; ?>">Debug de la API</a>
                <a href="?page=sga-settings&tab=mantenimiento" class="nav-tab <?php echo $active_tab == 'mantenimiento' ? 'nav-tab-active' : ''; ?>">Mantenimiento</a>
            </h2>
            <form method="post" action="options.php">
                <?php
                if ($active_tab == 'pagos') {
                    settings_fields('sga_main_settings_group');
                    $this->render_payment_settings_tab();
                    submit_button('Guardar Cambios');
                } elseif ($active_tab == 'integracion') {
                    // Este grupo ahora guarda 'sga_payment_options', 'sga_integration_options', 'sga_api_url' y 'sga_api_key'
                    settings_fields('sga_main_settings_group'); 
                    $this->render_integration_settings_tab();
                    submit_button('Guardar Cambios');
                } elseif ($active_tab == 'reportes') {
                    settings_fields('sga_report_options_group');
                    $this->render_reports_settings_tab();
                    submit_button('Guardar Cambios');
                } elseif ($active_tab == 'debug') {
                    $this->render_debug_settings_tab();
                } elseif ($active_tab == 'mantenimiento') {
                    // Esta pestaña no guarda opciones de WP, usa AJAX
                    $this->render_maintenance_settings_tab();
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

    /**
     * Renderiza la pestaña de Integración.
     *
     * MODIFICADO: Reemplazados los campos por sga_api_url y sga_api_key.
     */
    private function render_integration_settings_tab() {
        // Obtener las opciones guardadas
        $api_url = get_option('sga_api_url');
        $api_key = get_option('sga_api_key');
        $options = get_option('sga_integration_options', []);
        ?>
        <h2>Integración con Sistema Laravel (SGA Padre)</h2>
        <p>Configura la conexión entre este plugin (WordPress) y el sistema principal (Laravel).</p>
        
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="sga_api_url">URL de la API de Laravel (Saliente)</label></th>
                <td>
                    <input type="url" id="sga_api_url" name="sga_api_url" value="<?php echo esc_attr($api_url ?? ''); ?>" class="regular-text" placeholder="https://tu-url-ngrok.ngrok-free.app/api/v1" />
                    <p class="description">
                        La URL base del sistema Laravel, incluyendo el prefijo <code>/api/v1</code>.
                        <br>Esta es la URL que <strong>WordPress usará para llamar a Laravel</strong> (ej. para obtener cursos).
                    </p>
                </td>
            </tr>
            
            <tr valign="top">
                <th scope="row"><label for="sga_api_key">Clave API (Token)</label></th>
                <td>
                    <input type="password" id="sga_api_key" name="sga_api_key" value="<?php echo esc_attr($api_key ?? ''); ?>" class="regular-text" />
                    <p class="description">
                        Esta clave se usa para <strong>ambas direcciones</strong>:
                        <br>1. <strong>Saliente (WP -> Laravel):</strong> Es el Token de API generado en Laravel Sanctum (ej: <code>1|aBc...</code>).
                        <br>2. <strong>Entrante (Laravel -> WP):</strong> Es la clave secreta que Laravel debe enviar en la cabecera <code>X-SGA-Signature</code> para llamara a este WordPress.
                    </p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row">Matriculación Automática (Entrante)</th>
                <td>
                    <label for="disable_auto_enroll">
                        <input type="checkbox" id="disable_auto_enroll" name="sga_integration_options[disable_auto_enroll]" value="1" <?php checked(1, $options['disable_auto_enroll'] ?? 0, true); ?> />
                        Desactivar matriculación automática
                    </label>
                    <p class="description">Si está marcado, cuando Laravel envíe un webhook de "pagado", la inscripción NO se aprobará automáticamente y requerirá aprobación manual.</p>
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
                <h3>Simulador de Webhook Entrante (Laravel -> WP)</h3>
                <table class="form-table">
                    <tr><th><label for="sga-sim-cedula">Cédula</label></th><td><input type="text" id="sga-sim-cedula" /></td></tr>
                    <tr><th><label for="sga-sim-curso">Curso</label></th><td><input type="text" id="sga-sim-curso" /></td></tr>
                    <tr><th>Acción</th><td><button type="button" id="sga-test-webhook-btn">Simular</button></td></tr>
                </table>
                <h3>Prueba de Conexión Saliente (WP -> Laravel)</h3>
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
            // Test Conexión Saliente (WP -> Laravel v1)
            $('#sga-test-connection-btn').on('click', function() {
                var btn = $(this);
                var spinner = btn.next('.spinner');
                var resultsDiv = $('#sga-test-results');

                btn.prop('disabled', true);
                spinner.addClass('is-active');
                resultsDiv.slideUp().html('');

                $.post(ajaxurl, {
                    // --- MODIFICADO: Llamar a la nueva acción AJAX ---
                    action: 'sga_test_laravel_v1_connection', 
                    // --- MODIFICADO: Usar un nuevo nonce ---
                    _ajax_nonce: '<?php echo wp_create_nonce("sga_test_laravel_v1_nonce"); ?>' 
                }).done(function(response) {
                    // --- MODIFICADO: Interpretar la nueva respuesta ---
                    if (response.success) {
                        // response.data contendrá el JSON de Laravel: {status: 'success', message: '¡Conexión...'}
                        var response_msg = response.data.message || JSON.stringify(response.data);
                        resultsDiv.css('color', 'green').html('<strong>Éxito:</strong> ' + response_msg).slideDown();
                    } else {
                        // response.data.message contendrá el error
                        var error_msg = response.data.message || 'Error desconocido.';
                        resultsDiv.css('color', 'red').html('<strong>Error:</strong> ' + error_msg).slideDown();
                    }
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    // --- MODIFICADO: Añadir logs de consola ---
                    console.error('SGA Debug: Fallo la prueba de conexión saliente.');
                    console.error('AJAX Status:', textStatus, 'Error:', errorThrown);
                    console.error('Server Response (XHR):', jqXHR);
                    resultsDiv.css('color', 'red').html('<strong>Error:</strong> Hubo un fallo de comunicación AJAX. <strong>Revisa la consola del navegador (F12) para más detalles.</strong>').slideDown();
                }).always(function() {
                    btn.prop('disabled', false);
                    spinner.removeClass('is-active');
                    // Recargar la página para ver el log actualizado
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
                    // Reemplazar alert() por un mensaje en el div
                    resultsDiv.css('color', 'red').html('<strong>Error:</strong> Por favor, ingresa la cédula y el nombre del curso para la simulación.').slideDown();
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
                }).fail(function(jqXHR, textStatus, errorThrown) {
                     // --- MODIFICADO: Añadir logs de consola ---
                     console.error('SGA Debug: Fallo la simulación de webhook entrante.');
                     console.error('AJAX Status:', textStatus, 'Error:', errorThrown);
                     console.error('Server Response (XHR):', jqXHR);
                     resultsDiv.css('color', 'red').html('<strong>Error:</strong> Hubo un fallo de comunicación al intentar realizar la simulación. <strong>Revisa la consola (F12).</strong>').slideDown();
                }).always(function() {
                     // --- MODIFICADO: Habilitar el spinner y el botón ---
                     btn.prop('disabled', false);
                     spinner.removeClass('is-active');
                     // Recargar la página para ver el log actualizado
                     setTimeout(function() { location.reload(); }, 5000);
                });
            });
        });
        </script>
        <?php
    }

    // --- MODIFICACIÓN: Esta función ahora usa SGA_API_Client ---
    /**
     * Función AJAX para el botón "Iniciar Prueba" de la pestaña Debug.
     *
     * Prueba la conexión con el endpoint /api/v1/test de Laravel.
     */
    public function ajax_test_laravel_v1_connection() {
        check_ajax_referer('sga_test_laravel_v1_nonce', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción.']);
        }

        // Cargar SGA_Utils (para loguear)
        if (!class_exists('SGA_Utils')) {
            $utils_file = plugin_dir_path(__FILE__) . 'class-sga-utils.php';
            if (file_exists($utils_file)) {
                require_once $utils_file;
            } else {
                wp_send_json_error(['message' => 'Error fatal: No se pudo cargar class-sga-utils.php.']);
                return;
            }
        }

        // --- INICIO DE LA CORRECCIÓN ---
        // Cargar la NUEVA clase de cliente API
        if (!class_exists('SGA_API_Client')) { // <-- USAR NUEVO NOMBRE DE CLASE
            $client_file = plugin_dir_path(__FILE__) . 'class-sga-api-client.php'; // <-- USAR NUEVO ARCHIVO
            if (file_exists($client_file)) {
                require_once $client_file;
            } else {
                // Ahora podemos usar SGA_Utils para loguear el error
                SGA_Utils::_log_activity('API Test (v1): Error Fatal', 'No se pudo cargar class-sga-api-client.php.', 0, true);
                wp_send_json_error(['message' => 'Error fatal: No se pudo cargar class-sga-api-client.php.']);
                return;
            }
        }
        // --- FIN DE LA CORRECCIÓN ---


        // Usamos la nueva clase de Cliente API
        $api_client = new SGA_API_Client(); // <-- USAR NUEVO NOMBRE DE CLASE
        $response = $api_client->test_connection(); // Llama a /api/v1/test

        if (is_wp_error($response)) {
            // Error de WordPress (ej. cURL timeout, no se pudo conectar)
            $error_message = $response->get_error_message();
            SGA_Utils::_log_activity('API Test (v1): Error de WP', $error_message, 0, true);
            wp_send_json_error(['message' => $error_message]);
            return;
        }

        // Comprobar si la respuesta de la API fue un éxito
        if (isset($response['status']) && $response['status'] === 'success') {
            // Éxito: Laravel respondió { "status": "success", "message": "¡Conexión..." }
            SGA_Utils::_log_activity('API Test (v1): Éxito', json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, true);
            wp_send_json_success($response);
        } else {
            // La API respondió, pero con un error (ej. 401 Unauthorized, 404, 500)
            $log_message = 'La API de Laravel respondió, pero no fue exitosa: ' . json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            SGA_Utils::_log_activity('API Test (v1): Fallo', $log_message, 0, true);
            
            $error_text = 'La API respondió con un error. ';
            if (isset($response['message'])) {
                 $error_text .= $response['message'];
            }
            // Si es un 401, el token está mal
            if (strpos(json_encode($response), '401') !== false || (isset($response['message']) && strpos($response['message'], 'Unauthenticated') !== false)) {
                 $error_text = 'Error 401: No autenticado. Revisa que tu Clave API (Token) sea correcta.';
            }
            // Si es un 404, la URL está mal
            if (strpos(json_encode($response), '404') !== false || (isset($response['message']) && strpos($response['message'], 'Not Found') !== false)) {
                 $error_text = 'Error 404: No encontrado. Revisa que la URL de la API sea correcta y termine en /api/v1';
            }

            wp_send_json_error(['message' => $error_text, 'response' => $response]);
        }
    }
    // --- FIN MODIFICACIÓN ---

    // --- INICIO NUEVA FUNCIÓN (HANDLER PARA EL BOTÓN "SIMULAR") ---
    
    /**
     * Función AJAX para el botón "Simular" de la pestaña Debug.
     *
     * Simula una llamada ENTRANTE (como si viniera de Laravel) al endpoint
     * /wp-json/sga/v1/update-student-status/ de este mismo WordPress.
     */
    public function ajax_test_incoming_webhook() {
        check_ajax_referer('sga_test_api_nonce', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción.']);
        }

        // Cargar SGA_Utils (para loguear)
        if (!class_exists('SGA_Utils')) {
            $utils_file = plugin_dir_path(__FILE__) . 'class-sga-utils.php';
            if (file_exists($utils_file)) {
                require_once $utils_file;
            } else {
                wp_send_json_error(['message' => 'Error fatal: No se pudo cargar class-sga-utils.php.']);
                return;
            }
        }
        
        $cedula = sanitize_text_field($_POST['cedula'] ?? '');
        $curso = sanitize_text_field($_POST['curso'] ?? '');

        if (empty($cedula) || empty($curso)) {
            wp_send_json_error(['message' => 'La Cédula y el Curso son obligatorios para simular.']);
            return;
        }

        // Obtener la clave API (la misma que usamos para salir)
        $api_key = get_option('sga_api_key');
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'No hay Clave API guardada en los Ajustes.']);
            return;
        }
        
        // El endpoint que creamos en class-sga-api.php
        $url = home_url('/wp-json/sga/v1/update-student-status/');

        $body_data = [
            'cedula' => $cedula,
            'curso_nombre' => $curso,
            'status' => 'pagado' // Simulamos un pago
        ];

        $args = [
            'method'  => 'POST',
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'X-SGA-Signature' => $api_key // Aquí está la clave de seguridad
            ],
            'body'    => json_encode($body_data),
            'timeout' => 15,
            // 'cookies' => $_COOKIE // Añadir cookies para pasar el permission_callback de is_user_logged_in
        ];
        
        // NOTA: Para que la simulación funcione, necesitamos que la llamada AJAX
        // sea autenticada O que la API Key sea correcta.
        // Como estamos haciendo una llamada de servidor a servidor (wp_remote_post),
        // la cookie de admin no se pasa por defecto.
        // PERO, nuestro 'check_api_permission' en class-sga-api.php
        // permite el acceso si la 'X-SGA-Signature' es correcta.
        // Así que esta simulación prueba la clave API perfectamente.

        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            // Error de WordPress (ej. cURL timeout, no se pudo conectar)
            $error_message = $response->get_error_message();
            SGA_Utils::_log_activity('API Test (Simulación): Error de WP', $error_message, 0, true);
            wp_send_json_error(['message' => $error_message]);
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($response_body, true);

        // Registrar el intento
        $log_content = "Simulación de Webhook Entrante (Laravel -> WP)\n";
        $log_content .= "URL: $url\n";
        $log_content .= "Cuerpo Enviado: " . json_encode($body_data) . "\n";
        $log_content .= "Respuesta (Código: $response_code): " . $response_body;
        SGA_Utils::_log_activity('API Test (Simulación)', $log_content, 0, true);

        if ($response_code >= 200 && $response_code < 300) {
            // Éxito
            wp_send_json_success([
                'response_code' => $response_code,
                'response_body' => $decoded_body
            ]);
        } else {
            // Error de la API (403, 404, 500)
            $error_message = $decoded_body['message'] ?? $response_body;
            if ($response_code === 403) {
                $error_message = 'Error 403: Clave de API inválida. (X-SGA-Signature)';
            }
            wp_send_json_error([
                'message' => $error_message,
                'response_code' => $response_code,
                'response_body' => $decoded_body
            ]);
        }
    }
    // --- FIN NUEVA FUNCIÓN ---


    private function render_maintenance_settings_tab() {
        ?>
        <h2>Mantenimiento de Datos</h2>
        <p>Herramientas para limpiar y optimizar la base de datos del plugin SGA.</p>
        <style>
            #sga-cleanup-duplicates-section {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-left: 4px solid #d63638;
                padding: 20px;
                margin-top: 20px;
                max-width: 600px;
            }
            #sga-cleanup-duplicates-section h3 {
                margin-top: 0;
            }
            #sga-cleanup-duplicates-section .button-danger {
                background: #d63638;
                border-color: #b32d2e;
                color: #fff;
            }
            #sga-cleanup-duplicates-section .button-danger:hover {
                background: #b32d2e;
                border-color: #b32d2e;
            }
            #sga-cleanup-duplicates-section .button-danger:disabled {
                background: #e0e0e0;
                border-color: #c3c4c7;
                color: #a7aaad;
            }
            #sga-cleanup-confirm-text {
                margin-right: 10px;
            }
            #sga-cleanup-results {
                margin-top: 15px;
                padding: 10px;
                border: 1px solid #c3c4c7;
                display: none;
            }
        </style>
        
        <div id="sga-cleanup-duplicates-section">
            <h3>Limpiar Inscripciones Duplicadas</h3>
            <p><strong>¡Atención!</strong> Esta acción es permanente y no se puede deshacer.</p>
            <p>El sistema buscará estudiantes que estén inscritos varias veces en el <strong>mismo curso</strong> y con el <strong>mismo estado</strong>. Conservará la inscripción más reciente (basada en la fecha) y eliminará las demás.</p>
            <p>Esto es útil para limpiar datos antiguos creados antes de que el sistema bloqueara duplicados automáticamente.</p>
            
            <p>Para confirmar, escribe <strong>BORRAR</strong> en el campo de abajo y presiona el botón.</p>
            <input type="text" id="sga-cleanup-confirm-text" placeholder="Escribe BORRAR" autocomplete="off">
            <button type="button" id="sga-cleanup-duplicates-btn" class="button button-danger" disabled>Ejecutar Limpieza de Duplicados</button>
            <span class="spinner" style="vertical-align: middle;"></span>

            <div id="sga-cleanup-results" style="display:none; margin-top: 15px; padding: 10px; border-width: 1px; border-style: solid;"></div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Habilitar/deshabilitar botón de limpieza
            $('#sga-cleanup-confirm-text').on('keyup', function() {
                if ($(this).val() === 'BORRAR') {
                    $('#sga-cleanup-duplicates-btn').prop('disabled', false);
                } else {
                    $('#sga-cleanup-duplicates-btn').prop('disabled', true);
                }
            });

            // Acción AJAX para limpiar duplicados
            $('#sga-cleanup-duplicates-btn').on('click', function() {
                var btn = $(this);
                var spinner = btn.next('.spinner');
                var resultsDiv = $('#sga-cleanup-results');
                var confirmText = $('#sga-cleanup-confirm-text');

                btn.prop('disabled', true);
                spinner.addClass('is-active');
                resultsDiv.slideUp().html('').css('color', 'inherit').css('border-color', '#c3c4c7');

                $.post(ajaxurl, {
                    action: 'sga_cleanup_duplicates',
                    _ajax_nonce: '<?php echo wp_create_nonce("sga_cleanup_duplicates_nonce"); ?>'
                }).done(function(response) {
                    if (response.success) {
                        var message = '<strong>Éxito:</strong> Se eliminaron ' + response.data.deleted_count + ' inscripciones duplicadas.';
                        resultsDiv.css('color', 'green').css('border-color', 'green').html(message).slideDown();
                    } else {
                        resultsDiv.css('color', 'red').css('border-color', 'red').html('<strong>Error:</strong> ' + response.data.message).slideDown();
                    }
                }).fail(function() {
                    resultsDiv.css('color', 'red').css('border-color', 'red').html('<strong>Error:</strong> Fallo de comunicación AJAX.').slideDown();
                }).always(function() {
                    spinner.removeClass('is-active');
                    confirmText.val(''); // Resetear campo de confirmación
                    // Dejamos el botón deshabilitado hasta que vuelvan a escribir
                });
            });
        });
        </script>
        <?php
    }
}