<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_Ajax
 *
 * Gestiona todas las peticiones AJAX del plugin.
 */
class SGA_Ajax {

    public function __construct() {
        // Hooks AJAX para usuarios logueados
        add_action('wp_ajax_aprobar_para_matriculacion', array($this, 'ajax_aprobar_para_matriculacion'));
        add_action('wp_ajax_aprobar_seleccionados', array($this, 'ajax_aprobar_seleccionados'));
        add_action('wp_ajax_exportar_excel', array($this, 'ajax_exportar_excel'));
        add_action('wp_ajax_exportar_moodle_csv', array($this, 'ajax_exportar_moodle_csv'));
        add_action('wp_ajax_sga_exportar_registro_llamadas', array($this, 'ajax_exportar_registro_llamadas'));
        add_action('wp_ajax_sga_print_invoice', array($this, 'ajax_sga_print_invoice'));
        add_action('wp_ajax_sga_get_student_profile_data', array($this, 'ajax_get_student_profile_data'));
        add_action('wp_ajax_sga_update_student_profile_data', array($this, 'ajax_update_student_profile_data'));
        add_action('wp_ajax_sga_send_bulk_email', array($this, 'ajax_send_bulk_email'));
        add_action('wp_ajax_sga_get_report_chart_data', array($this, 'ajax_get_report_chart_data'));
        add_action('wp_ajax_sga_test_api_connection', array($this, 'ajax_test_api_connection'));
        add_action('wp_ajax_sga_test_incoming_webhook', array($this, 'ajax_test_incoming_webhook'));
        add_action('wp_ajax_sga_update_call_status', array($this, 'ajax_update_call_status'));
        add_action('wp_ajax_sga_marcar_llamado', array($this, 'ajax_sga_marcar_llamado'));
        add_action('wp_ajax_sga_get_panel_view_html', array($this, 'ajax_get_panel_view_html'));
        add_action('wp_ajax_sga_check_pending_inscriptions', array($this, 'ajax_check_pending_inscriptions'));


        // Hooks AJAX para usuarios no logueados (ej. imprimir factura desde el correo)
        add_action('wp_ajax_nopriv_sga_print_invoice', array($this, 'ajax_sga_print_invoice'));
    }

    /**
     * AJAX: Verifica el número de inscripciones pendientes para la notificación en tiempo real.
     */
    public function ajax_check_pending_inscriptions() {
        if (!check_ajax_referer('sga_pending_nonce', 'security', false)) {
            wp_send_json_error(['message' => 'Error de seguridad.'], 403);
        }
        if (!current_user_can('edit_estudiantes')) {
            wp_send_json_error(['message' => 'No tienes permisos.'], 403);
        }
        
        $count = SGA_Utils::_get_pending_inscriptions_count();

        wp_send_json_success(['count' => $count]);
    }

    /**
     * AJAX: Marca a un estudiante como llamado por el usuario actual.
     */
    public function ajax_sga_marcar_llamado() {
        if (!isset($_POST['post_id']) || !isset($_POST['row_index']) || !isset($_POST['_ajax_nonce'])) {
            wp_send_json_error(['message' => 'Parámetros inválidos.']);
        }
        
        $post_id = intval($_POST['post_id']);
        $row_index = intval($_POST['row_index']);
        $comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';
        
        check_ajax_referer('sga_marcar_llamado_' . $post_id . '_' . $row_index);

        if (!current_user_can('edit_estudiantes')) {
            wp_send_json_error(['message' => 'No tienes permisos.']);
        }

        $current_user = wp_get_current_user();
        $call_log = get_post_meta($post_id, '_sga_call_log', true);
        if (!is_array($call_log)) {
            $call_log = [];
        }

        $timestamp = current_time('timestamp');
        $call_log[$row_index] = [
            'user_id' => $current_user->ID,
            'user_name' => $current_user->display_name,
            'timestamp' => $timestamp,
            'comment' => $comment
        ];

        update_post_meta($post_id, '_sga_call_log', $call_log);
        
        $html = 'Llamado por <strong>' . esc_html($current_user->display_name) . '</strong><br><small>' . esc_html(date_i18n('d/m/Y H:i', $timestamp)) . '</small>';
        if (!empty($comment)) {
            $html .= '<p class="sga-call-comment"><em>' . esc_html($comment) . '</em></p>';
        }
        
        $student_post = get_post($post_id);
        $cursos_inscritos = get_field('cursos_inscritos', $post_id);
        $curso_actual = $cursos_inscritos[$row_index] ?? null;
        $course_name = $curso_actual ? $curso_actual['nombre_curso'] : 'Desconocido';

        $log_content = "Estudiante: {$student_post->post_title} (ID: {$post_id}) fue marcado como llamado.";
        if (!empty($comment)) {
            $log_content .= " Comentario: " . $comment;
        }
        SGA_Utils::_log_activity('Inscripción Marcada Como Llamada', $log_content, $current_user->ID);

        // Crear el CPT 'sga_llamada'
        $call_post_id = wp_insert_post([
            'post_type'    => 'sga_llamada',
            'post_title'   => 'Llamada a ' . $student_post->post_title . ' por ' . $current_user->display_name,
            'post_status'  => 'publish',
            'post_author'  => $current_user->ID,
            'post_content' => $comment
        ]);

        if ($call_post_id && !is_wp_error($call_post_id)) {
            update_post_meta($call_post_id, '_student_id', $post_id);
            update_post_meta($call_post_id, '_student_name', $student_post->post_title);
            update_post_meta($call_post_id, '_course_name', $course_name);
            update_post_meta($call_post_id, '_row_index', $row_index);
        }

        wp_send_json_success(['html' => $html]);
    }

    /**
     * AJAX: Actualiza el estado de la llamada para una inscripción específica.
     */
    public function ajax_update_call_status() {
        if (!isset($_POST['post_id']) || !isset($_POST['row_index']) || !isset($_POST['status']) || !isset($_POST['_ajax_nonce'])) {
            wp_send_json_error(['message' => 'Parámetros inválidos.']);
        }

        $post_id = intval($_POST['post_id']);
        $row_index = intval($_POST['row_index']);
        $status = sanitize_key($_POST['status']);

        check_ajax_referer('sga_update_call_status_' . $post_id . '_' . $row_index);

        if (!current_user_can('edit_estudiantes')) {
            wp_send_json_error(['message' => 'No tienes permisos para esta acción.']);
        }

        $call_statuses = get_post_meta($post_id, '_sga_call_statuses', true);
        if (!is_array($call_statuses)) {
            $call_statuses = [];
        }
        $call_statuses[$row_index] = $status;
        update_post_meta($post_id, '_sga_call_statuses', $call_statuses);

        $student = get_post($post_id);
        SGA_Utils::_log_activity(
            'Estado de Llamada Actualizado',
            "Se actualizó el estado de llamada a '{$status}' para el estudiante: {$student->post_title} (Inscripción #{$row_index})."
        );

        wp_send_json_success(['message' => 'Estado actualizado.']);
    }

    /**
     * AJAX: Genera y descarga un archivo Excel con el registro de llamadas.
     */
    public function ajax_exportar_registro_llamadas() {
        check_ajax_referer('export_calls_nonce', '_wpnonce');
        $reports_handler = new SGA_Reports();
        $reports_handler->exportar_registro_llamadas();
    }

    /**
     * AJAX: Obtiene los datos para el gráfico de reportes de los últimos 7 meses.
     */
    public function ajax_get_report_chart_data() {
        check_ajax_referer('sga_chart_nonce');
        if (!current_user_can('edit_estudiantes')) {
            wp_send_json_error(['message' => 'No tienes permisos.']);
        }
    
        $monthly_counts = [];
        $labels = [];
        $data = [];
        
        // Asegura que el locale esté disponible para los nombres de los meses en español.
        $date_format_obj = new IntlDateFormatter('es_ES', IntlDateFormatter::FULL, IntlDateFormatter::FULL, null, null, 'MMM');
    
        // Prepara los contenedores para los últimos 7 meses (incluyendo el actual)
        for ($i = 6; $i >= 0; $i--) {
            $date = new DateTime("first day of -$i months");
            $key = $date->format('Y-m');
            $monthly_counts[$key] = 0;
            $labels[] = ucfirst($date_format_obj->format($date));
        }
    
        $estudiantes_query = get_posts(array(
            'post_type' => 'estudiante',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
    
        if ($estudiantes_query && function_exists('get_field')) {
            foreach ($estudiantes_query as $estudiante_id) {
                $cursos = get_field('cursos_inscritos', $estudiante_id);
                if ($cursos) {
                    foreach ($cursos as $curso) {
                        // Contar tanto 'Inscrito' como 'Matriculado' como una inscripción
                        if (isset($curso['fecha_inscripcion']) && !empty($curso['fecha_inscripcion'])) {
                            try {
                                $inscripcion_date = new DateTime($curso['fecha_inscripcion']);
                                $inscripcion_key = $inscripcion_date->format('Y-m');
                                if (array_key_exists($inscripcion_key, $monthly_counts)) {
                                    $monthly_counts[$inscripcion_key]++;
                                }
                            } catch (Exception $e) {
                                // Ignorar fechas con formato inválido para no romper el proceso
                            }
                        }
                    }
                }
            }
        }
    
        // Llena el array de datos en el orden correcto
        foreach ($monthly_counts as $count) {
            $data[] = $count;
        }
    
        wp_send_json_success(['labels' => $labels, 'data' => $data]);
    }

    /**
     * AJAX: Aprueba una única inscripción.
     */
    public function ajax_aprobar_para_matriculacion() {
        check_ajax_referer('aprobar_nonce');
        if (!isset($_POST['post_id'], $_POST['row_index'], $_POST['cedula'], $_POST['nombre'])) {
            wp_send_json_error('Faltan datos.');
        }
        $resultado = SGA_Utils::_aprobar_estudiante(
            intval($_POST['post_id']),
            intval($_POST['row_index']),
            sanitize_text_field($_POST['cedula']),
            sanitize_text_field($_POST['nombre'])
        );
        if ($resultado) {
            wp_send_json_success($resultado);
        } else {
            wp_send_json_error('No se pudo aprobar al estudiante.');
        }
    }

    /**
     * AJAX: Aprueba un lote de inscripciones seleccionadas.
     */
    public function ajax_aprobar_seleccionados() {
        check_ajax_referer('aprobar_bulk_nonce');
        if (!isset($_POST['seleccionados']) || !is_array($_POST['seleccionados'])) {
            wp_send_json_error(array('message' => 'No se seleccionaron estudiantes o el formato es incorrecto.'));
        }

        $seleccionados = $_POST['seleccionados'];
        $aprobados = [];
        $fallidos = [];

        foreach ($seleccionados as $sel) {
            if (!isset($sel['post_id']) || !isset($sel['row_index'])) continue;
            $post_id = intval($sel['post_id']);
            $row_index = intval($sel['row_index']);
            $estudiante_post = get_post($post_id);

            if (!$estudiante_post || 'estudiante' !== $estudiante_post->post_type) {
                $fallidos[] = ['post_id' => $post_id, 'row_index' => $row_index, 'nombre' => 'ID Inválido', 'reason' => 'El estudiante no fue encontrado.'];
                continue;
            }

            $nombre = $estudiante_post->post_title;
            $cedula = get_field('cedula', $post_id);
            $resultado = SGA_Utils::_aprobar_estudiante($post_id, $row_index, $cedula, $nombre);
            if ($resultado) {
                $aprobados[] = $resultado;
            } else {
                $fallidos[] = ['post_id' => $post_id, 'row_index' => $row_index, 'nombre' => $nombre, 'reason' => 'La función _aprobar_estudiante falló.'];
            }
        }

        if (!empty($aprobados) || !empty($fallidos)) {
            wp_send_json_success(array('approved' => $aprobados, 'failed' => $fallidos));
        } else {
            wp_send_json_error(array('message' => 'No se pudo procesar ningún estudiante.'));
        }
    }
    
    /**
     * AJAX: Genera y descarga un archivo Excel con los estudiantes matriculados.
     */
    public function ajax_exportar_excel() {
        check_ajax_referer('export_nonce', '_wpnonce');
        $reports_handler = new SGA_Reports();
        $reports_handler->exportar_excel();
    }

    /**
     * AJAX: Genera y descarga un archivo CSV para importar en Moodle.
     */
    public function ajax_exportar_moodle_csv() {
        check_ajax_referer('export_nonce', '_wpnonce');
        $reports_handler = new SGA_Reports();
        $reports_handler->exportar_moodle_csv();
    }

    /**
     * AJAX: Imprime una factura en PDF.
     */
    public function ajax_sga_print_invoice() {
        $reports_handler = new SGA_Reports();
        $reports_handler->ajax_sga_print_invoice();
    }

    /**
     * AJAX: Obtiene los datos del perfil de un estudiante y devuelve el HTML.
     */
    public function ajax_get_student_profile_data() {
        check_ajax_referer('sga_get_profile_nonce');
        if (!current_user_can('edit_estudiantes')) {
            wp_send_json_error(['message' => 'No tienes permisos.']);
        }
        if (!isset($_POST['student_id'])) {
            wp_send_json_error(['message' => 'ID de estudiante no proporcionado.']);
        }

        $student_id = intval($_POST['student_id']);
        $student_post = get_post($student_id);

        if (!$student_post || 'estudiante' !== $student_post->post_type) {
            wp_send_json_error(['message' => 'Estudiante no encontrado.']);
        }

        $html = SGA_Utils::_get_student_profile_html($student_post);
        wp_send_json_success(['html' => $html]);
    }

    /**
     * AJAX: Actualiza los datos del perfil de un estudiante.
     */
    public function ajax_update_student_profile_data() {
        check_ajax_referer('sga_update_profile_nonce');
        if (!current_user_can('edit_estudiantes')) {
            wp_send_json_error(['message' => 'No tienes permisos.']);
        }
        if (!isset($_POST['student_id']) || !isset($_POST['profile_data'])) {
            wp_send_json_error(['message' => 'Datos incompletos.']);
        }

        $student_id = intval($_POST['student_id']);
        $profile_data = $_POST['profile_data'];

        wp_update_post([
            'ID' => $student_id,
            'post_title' => sanitize_text_field($profile_data['nombre_completo'])
        ]);

        update_field('cedula', sanitize_text_field($profile_data['cedula']), $student_id);
        update_field('email', sanitize_email($profile_data['email']), $student_id);
        update_field('telefono', sanitize_text_field($profile_data['telefono']), $student_id);
        update_field('direccion', sanitize_text_field($profile_data['direccion']), $student_id);

        if (isset($profile_data['cursos']) && is_array($profile_data['cursos'])) {
            foreach ($profile_data['cursos'] as $curso_data) {
                $row_index = intval($curso_data['row_index']);
                $estado = sanitize_text_field($curso_data['estado']);
                update_sub_field(['cursos_inscritos', $row_index + 1, 'estado'], $estado, $student_id);
            }
        }
        
        SGA_Utils::_log_activity('Perfil Actualizado', 'Se actualizó el perfil del estudiante ID: ' . $student_id);
        wp_send_json_success(['message' => 'Perfil actualizado.']);
    }

    /**
     * AJAX: Envía correos masivos a grupos de estudiantes.
     */
    public function ajax_send_bulk_email() {
        check_ajax_referer('sga_send_bulk_email_nonce');
        if (!current_user_can('edit_estudiantes')) {
            wp_send_json_error(['message' => 'No tienes permisos.']);
        }

        set_time_limit(0);

        $recipient_group = sanitize_key($_POST['recipient_group']);
        $curso_especifico = sanitize_text_field($_POST['curso']);
        $subject = sanitize_text_field($_POST['subject']);
        $body_template = wp_kses_post(stripslashes_deep($_POST['body']));
        
        $recipients_data = [];

        if ($recipient_group === 'especificos') {
            $specific_student_ids_json = isset($_POST['student_ids']) ? stripslashes($_POST['student_ids']) : '[]';
            $specific_student_ids = json_decode($specific_student_ids_json, true);
            if (!is_array($specific_student_ids)) {
                $specific_student_ids = [];
            }
            $specific_student_ids = array_map('intval', $specific_student_ids);

            if (!empty($specific_student_ids)) {
                foreach ($specific_student_ids as $student_id) {
                    $email = get_field('email', $student_id);
                    if (is_email($email)) {
                        $recipients_data[$student_id] = $email;
                    }
                }
            }
        } else {
            $estudiantes = get_posts(['post_type' => 'estudiante', 'posts_per_page' => -1]);

            if ($estudiantes && function_exists('get_field')) {
                foreach ($estudiantes as $estudiante) {
                    $email = get_field('email', $estudiante->ID);
                    if (!is_email($email)) continue;

                    $cursos_inscritos = get_field('cursos_inscritos', $estudiante->ID) ?: [];
                    $is_matriculado = false;
                    $is_pendiente = false;
                    $is_in_curso = false;

                    foreach ($cursos_inscritos as $curso) {
                        if ($curso['estado'] === 'Matriculado') $is_matriculado = true;
                        if ($curso['estado'] === 'Inscrito') $is_pendiente = true;
                        if ($recipient_group === 'por_curso' && $curso['nombre_curso'] === $curso_especifico) {
                            $is_in_curso = true;
                        }
                    }

                    $add_email = false;
                    switch ($recipient_group) {
                        case 'todos': $add_email = true; break;
                        case 'matriculados': if ($is_matriculado) $add_email = true; break;
                        case 'pendientes': if ($is_pendiente) $add_email = true; break;
                        case 'por_curso': if ($is_in_curso) $add_email = true; break;
                    }
                    if ($add_email) {
                        $recipients_data[$estudiante->ID] = $email;
                    }
                }
            }
        }

        if (empty($recipients_data)) {
            wp_send_json_error(['message' => 'No se encontraron destinatarios para los criterios seleccionados.']);
        }
        
        $sent_count = 0;
        foreach ($recipients_data as $student_id => $recipient_email) {
            $personalized_body = SGA_Utils::_replace_dynamic_tags($body_template, $student_id, $recipient_group, $curso_especifico);
            $email_template = SGA_Utils::_get_email_template($subject, $personalized_body);
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            
            if (wp_mail($recipient_email, $subject, $email_template, $headers)) {
                $sent_count++;
            }
        }

        SGA_Utils::_log_activity('Correo Masivo Enviado', "Se enviaron {$sent_count} de " . count($recipients_data) . " correos al grupo '{$recipient_group}'.");
        wp_send_json_success(['message' => "Proceso completado. Se enviaron {$sent_count} correos."]);
    }

    /**
     * AJAX: Simula un webhook entrante desde el sistema interno para matricular a un estudiante.
     */
    public function ajax_test_incoming_webhook() {
        check_ajax_referer('sga_test_api_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No tienes permisos.']);
        }

        $cedula = sanitize_text_field($_POST['cedula']);
        $curso = sanitize_text_field($_POST['curso']);

        if (empty($cedula) || empty($curso)) {
            wp_send_json_error(['message' => 'La cédula y el nombre del curso son obligatorios.']);
        }

        $integration_options = get_option('sga_integration_options', []);
        $api_secret = $integration_options['api_secret_key'] ?? '';

        $endpoint_url = get_rest_url(null, 'sga/v1/update-student-status/');

        $body = json_encode([
            'cedula' => $cedula,
            'status' => 'pagado',
            'curso_nombre' => $curso
        ]);

        $response = wp_remote_post($endpoint_url, [
            'method'    => 'POST',
            'headers'   => [
                'Content-Type' => 'application/json',
                'X-SGA-Signature' => $api_secret,
                // Agregamos una cookie para que la API sepa que es una petición autenticada desde el admin
                'Cookie' => implode('; ', array_map(function($k, $v) { return "$k=$v"; }, array_keys($_COOKIE), $_COOKIE))
            ],
            'body'      => $body,
            'timeout'   => 30,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Error de WP_Error: ' . $response->get_error_message()]);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        wp_send_json_success([
            'message' => 'Simulación completada.',
            'response_code' => $response_code,
            'response_body' => json_decode($response_body)
        ]);
    }

    /**
     * AJAX: Prueba la conexión con las URLs de la API del sistema interno.
     */
    public function ajax_test_api_connection() {
        check_ajax_referer('sga_test_api_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No tienes permisos.']);
        }

        SGA_Integration::_send_webhook_test();
        SGA_Integration::_query_student_test();

        wp_send_json_success(['message' => 'Prueba de conexión iniciada.']);
    }

    /**
     * AJAX: Recarga el HTML de una vista específica del panel.
     */
    public function ajax_get_panel_view_html() {
        if (!isset($_POST['view']) || !check_ajax_referer('sga_get_view_nonce', '_ajax_nonce')) {
            wp_send_json_error(['message' => 'Parámetros inválidos o error de seguridad.']);
        }
        if (!current_user_can('edit_estudiantes')) {
            wp_send_json_error(['message' => 'No tienes permisos.']);
        }

        $view = sanitize_key($_POST['view']);
        $method_name = 'render_view_' . $view;

        $shortcode_handler = new SGA_Shortcodes();

        if (method_exists($shortcode_handler, $method_name)) {
            ob_start();
            $shortcode_handler->{$method_name}();
            $html = ob_get_clean();
            wp_send_json_success(['html' => $html]);
        } else {
            wp_send_json_error(['message' => 'La vista solicitada no existe.']);
        }
    }
}

