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
        $user = wp_get_current_user();
        if (in_array('gestor_academico', (array) $user->roles)) {
            remove_menu_page('edit.php');
            remove_menu_page('upload.php');
            remove_menu_page('edit.php?post_type=page');
            remove_menu_page('edit-comments.php');
            remove_menu_page('themes.php');
            remove_menu_page('plugins.php');
            remove_menu_page('users.php');
            remove_menu_page('tools.php');
            remove_menu_page('options-general.php');
        }

        add_menu_page(
            'SGA by Sam',
            'SGA by Sam',
            'edit_posts',
            'sga_main_menu',
            null,
            'dashicons-welcome-learn-more',
            25
        );
        add_submenu_page(
            'sga_main_menu',
            'Aprobar Inscripciones',
            'Aprobar Inscripciones',
            'edit_estudiantes',
            'sga-aprobar-inscripciones',
            array($this, 'render_admin_approval_page')
        );
        add_submenu_page(
            'sga_main_menu',
            'Ajustes de Reportes',
            'Ajustes de Reportes',
            'manage_options',
            'sga-reportes-settings',
            array($this, 'render_reports_settings_page')
        );
        add_submenu_page(
            'sga_main_menu',
            'Ajustes de Pagos',
            'Ajustes de Pagos',
            'manage_options',
            'sga-pagos-settings',
            array($this, 'render_payment_settings_page')
        );
    }

    /**
     * Registra los grupos de opciones del plugin.
     */
    public function register_plugin_settings() {
        register_setting('sga_report_options_group', 'sga_report_options', array($this, 'sanitize_report_options'));
        register_setting('sga_payment_options_group', 'sga_payment_options', array($this, 'sanitize_payment_options'));
    }

    /**
     * Sanitiza las opciones de reportes antes de guardarlas.
     */
    public function sanitize_report_options($input) {
        $sanitized_input = [];
        if (isset($input['recipient_email'])) $sanitized_input['recipient_email'] = sanitize_email($input['recipient_email']);
        $sanitized_input['enable_weekly'] = isset($input['enable_weekly']) ? 1 : 0;
        $sanitized_input['enable_monthly'] = isset($input['enable_monthly']) ? 1 : 0;
        return $sanitized_input;
    }

    /**
     * Sanitiza las opciones de pagos antes de guardarlas.
     */
    public function sanitize_payment_options($input) {
        $sanitized_input = [];
        // General
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
     * Renderiza la página de ajustes de pagos.
     */
    public function render_payment_settings_page() {
        if (!current_user_can('manage_options')) wp_die(__('No tienes permisos para acceder a esta página.'));
        $options = get_option('sga_payment_options', [
            'local_currency_symbol' => 'DOP',
            'active_gateway' => 'cardnet',
            'azul_merchant_id' => '',
            'azul_auth_key' => '',
            'azul_environment' => 'sandbox',
            'cardnet_public_key' => 'J_eHXPYlDo9wlFpFXjgalm_I56ONV7HQ',
            'cardnet_private_key' => '9kYH2uY5zoTD-WBMEoc0KNRQYrC7crPRJ7zPegg3suXguw_8L-rZDQ',
            'cardnet_environment' => 'sandbox',
        ]);
        ?>
        <div class="wrap">
            <h1>Ajustes de Pagos Online</h1>
            <p>Configura las credenciales para aceptar pagos y selecciona la pasarela activa.</p>
            <form method="post" action="options.php">
                <?php settings_fields('sga_payment_options_group'); ?>
                
                <h2>Configuración General</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="local_currency_symbol">Símbolo Moneda Local</label></th>
                        <td>
                            <input type="text" id="local_currency_symbol" name="sga_payment_options[local_currency_symbol]" value="<?php echo esc_attr($options['local_currency_symbol'] ?? 'DOP'); ?>" class="small-text" placeholder="DOP" />
                            <p class="description">El símbolo de la moneda en la que están los precios de tus cursos (ej. DOP, RD$).</p>
                        </td>
                    </tr>
                     <tr valign="top">
                        <th scope="row"><label for="active_gateway">Pasarela de Pago Activa</label></th>
                        <td>
                            <select id="active_gateway" name="sga_payment_options[active_gateway]">
                                <option value="azul" <?php selected($options['active_gateway'] ?? 'azul', 'azul'); ?>>Azul</option>
                                <option value="cardnet" <?php selected($options['active_gateway'] ?? 'azul', 'cardnet'); ?>>Cardnet</option>
                            </select>
                            <p class="description">Selecciona la pasarela de pago que se usará en el portal de pagos.</p>
                        </td>
                    </tr>
                </table>
                <hr>

                <h2>Pasarela de Pago: Azul</h2>
                <table class="form-table">
                     <tr valign="top">
                        <th scope="row"><label for="azul_environment">Entorno de Azul</label></th>
                        <td>
                            <select id="azul_environment" name="sga_payment_options[azul_environment]">
                                <option value="sandbox" <?php selected($options['azul_environment'], 'sandbox'); ?>>Sandbox (Pruebas)</option>
                                <option value="live" <?php selected($options['azul_environment'], 'live'); ?>>Live (Producción)</option>
                            </select>
                            <p class="description">Usa 'Sandbox' para probar. Cambia a 'Live' para aceptar pagos reales.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="azul_merchant_id">Azul Merchant ID</label></th>
                        <td>
                            <input type="text" id="azul_merchant_id" name="sga_payment_options[azul_merchant_id]" value="<?php echo esc_attr($options['azul_merchant_id']); ?>" class="regular-text" />
                            <p class="description">ID de comercio proporcionado por Azul.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="azul_auth_key">Azul Authentication Key</label></th>
                        <td>
                            <input type="password" id="azul_auth_key" name="sga_payment_options[azul_auth_key]" value="<?php echo esc_attr($options['azul_auth_key']); ?>" class="regular-text" />
                            <p class="description">Llave de autenticación proporcionada por Azul. Esta clave se guarda de forma segura.</p>
                        </td>
                    </tr>
                </table>
                <hr>

                <h2>Pasarela de Pago: Cardnet</h2>
                <p>Configura las credenciales para la pasarela de pago Cardnet (Tokenización).</p>
                <table class="form-table">
                     <tr valign="top">
                        <th scope="row"><label for="cardnet_environment">Entorno de Cardnet</label></th>
                        <td>
                            <select id="cardnet_environment" name="sga_payment_options[cardnet_environment]">
                                <option value="sandbox" <?php selected($options['cardnet_environment'] ?? 'sandbox', 'sandbox'); ?>>Desarrollo (Pruebas)</option>
                                <option value="production" <?php selected($options['cardnet_environment'] ?? 'sandbox', 'production'); ?>>Producción</option>
                            </select>
                            <p class="description">Usa 'Desarrollo' para probar. Cambia a 'Producción' para aceptar pagos reales.</p>
                        </td>
                    </tr>
                     <tr valign="top">
                        <th scope="row"><label for="cardnet_public_key">Cardnet Public Account Key</label></th>
                        <td>
                            <input type="text" id="cardnet_public_key" name="sga_payment_options[cardnet_public_key]" value="<?php echo esc_attr($options['cardnet_public_key'] ?? ''); ?>" class="regular-text" />
                            <p class="description">Llave pública para el formulario de checkout (PWCheckout.js). La de pruebas es: J_eHXPYlDo9wlFpFXjgalm_I56ONV7HQ</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="cardnet_private_key">Cardnet Private Account Key</label></th>
                        <td>
                            <input type="password" id="cardnet_private_key" name="sga_payment_options[cardnet_private_key]" value="<?php echo esc_attr($options['cardnet_private_key'] ?? ''); ?>" class="regular-text" />
                            <p class="description">Llave privada (Private Account Key) para las llamadas a la API (server-to-server). La de pruebas es: 9kYH2uY5zoTD-WBMEoc0KNRQYrC7crPRJ7zPegg3suXguw_8L-rZDQ</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Guardar Cambios'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Renderiza la página de ajustes de reportes.
     */
    public function render_reports_settings_page() {
        if (!current_user_can('manage_options')) wp_die(__('No tienes permisos para acceder a esta página.'));
        $options = get_option('sga_report_options', [
            'recipient_email' => get_option('admin_email'),
            'enable_weekly' => 0,
            'enable_monthly' => 0,
        ]);
        ?>
        <div class="wrap">
            <h1>Ajustes del Sistema de Reportes</h1>
            <?php settings_errors(); ?>
            <div id="poststuff"><div id="post-body" class="metabox-holder columns-2">
                <div id="post-body-content"><div class="meta-box-sortables ui-sortable">
                    <div class="postbox">
                        <h2 class="hndle"><span>Reportes Automáticos</span></h2>
                        <div class="inside">
                            <p>Configura los reportes automáticos que se enviarán al correo especificado.</p>
                            <form method="post" action="options.php">
                                <?php settings_fields('sga_report_options_group'); ?>
                                <table class="form-table">
                                    <tr valign="top">
                                        <th scope="row"><label for="recipient_email">Correo Receptor</label></th>
                                        <td><input type="email" id="recipient_email" name="sga_report_options[recipient_email]" value="<?php echo esc_attr($options['recipient_email']); ?>" class="regular-text" />
                                        <p class="description">El correo donde se recibirán todos los reportes (automáticos y manuales).</p></td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row">Reporte Semanal</th>
                                        <td><label for="enable_weekly"><input type="checkbox" id="enable_weekly" name="sga_report_options[enable_weekly]" value="1" <?php checked(1, $options['enable_weekly'], true); ?> /> Activar reporte semanal de matriculados y pendientes.</label>
                                        <p class="description">Se enviará cada lunes a las 2:00 AM.</p></td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row">Reporte Mensual</th>
                                        <td><label for="enable_monthly"><input type="checkbox" id="enable_monthly" name="sga_report_options[enable_monthly]" value="1" <?php checked(1, $options['enable_monthly'], true); ?> /> Activar reporte mensual completo.</label>
                                        <p class="description">Se enviará el primer día de cada mes a las 2:00 AM.</p></td>
                                    </tr>
                                </table>
                                <?php submit_button('Guardar Cambios'); ?>
                            </form>
                        </div>
                    </div>
                    <div class="postbox">
                        <h2 class="hndle"><span>Generar Reporte Manual</span></h2>
                        <div class="inside">
                            <p>Genera un reporte instantáneo y envíalo por correo o descárgalo como un archivo PDF.</p>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="sga_generate_manual_report">
                                <?php wp_nonce_field('sga_manual_report_nonce', '_wpnonce_manual_report'); ?>
                                <table class="form-table">
                                    <tr valign="top">
                                        <th scope="row"><label for="report_type">Tipo de Reporte</label></th>
                                        <td>
                                            <select name="report_type" id="report_type">
                                                <option value="matriculados">Estudiantes Matriculados</option>
                                                <option value="pendientes">Inscripciones Pendientes</option>
                                                <option value="cursos">Lista de Cursos Activos</option>
                                                <option value="log">Registro de Actividad (Últimos 30 días)</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row">Acción</th>
                                        <td>
                                            <button type="submit" name="report_action" value="email" class="button button-primary">Enviar por Correo</button>
                                            <button type="submit" name="report_action" value="download" class="button button-secondary">Descargar PDF</button>
                                        </td>
                                    </tr>
                                </table>
                            </form>
                        </div>
                    </div>
                </div></div>
            </div></div>
        </div>
        <?php
    }
    
    /**
     * Renderiza la página nativa de WP para aprobar inscripciones.
     */
    public function render_admin_approval_page() {
        // La lógica de procesamiento de acciones (aprobación individual y en lote) se ha movido a la clase SGA_Reports
        // para centralizar el manejo de acciones de admin-post. Esta función ahora solo renderiza la tabla.

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
                <input type="hidden" name="page" value="sga-aprobar-inscripciones">
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

