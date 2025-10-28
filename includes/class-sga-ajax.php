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
        add_action('wp_ajax_sga_print_student_profile', array($this, 'ajax_print_student_profile')); // <- HOOK EXISTENTE
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
        add_action('wp_ajax_sga_distribute_inscriptions', array($this, 'ajax_distribute_pending_inscriptions'));


        // Hooks AJAX para usuarios no logueados (ej. imprimir factura desde el correo)
        add_action('wp_ajax_nopriv_sga_print_invoice', array($this, 'ajax_sga_print_invoice'));
    }

    /**
     * AJAX: Reparte las inscripciones pendientes no asignadas entre los agentes seleccionados.
     */
    public function ajax_distribute_pending_inscriptions() {
        if (!check_ajax_referer('sga_distribute_nonce', 'security', false)) {
            wp_send_json_error(['message' => 'Error de seguridad.'], 403);
        }
        if (!current_user_can('manage_options') && !current_user_can('gestor_academico')) {
            wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción.'], 403);
        }

        $agent_ids = isset($_POST['agent_ids']) ? array_map('intval', $_POST['agent_ids']) : [];
        if (empty($agent_ids)) {
            wp_send_json_error(['message' => 'No se seleccionaron agentes.']);
        }
        
        // 1. Recolectar TODAS las inscripciones pendientes ('Inscrito').
        // Se incluyen todas para aplicar la lógica de prioridad y sobreescritura.
        $all_pending_inscriptions = [];
        $estudiantes = get_posts(['post_type' => 'estudiante', 'posts_per_page' => -1]);

        foreach ($estudiantes as $estudiante) {
            $cursos = get_field('cursos_inscritos', $estudiante->ID);
            
            if ($cursos) {
                foreach ($cursos as $index => $curso) {
                    if (isset($curso['estado']) && $curso['estado'] === 'Inscrito') {
                        $all_pending_inscriptions[] = [
                            'student_id' => $estudiante->ID,
                            'row_index'  => $index,
                            'course_name' => $curso['nombre_curso'],
                        ];
                    }
                }
            }
        }

        if (empty($all_pending_inscriptions)) {
            wp_send_json_success(['message' => 'No hay inscripciones pendientes para repartir.']);
        }

        $assignments_log = [];
        $total_assigned = 0;
        
        // 2. Procesar el reparto con lógica de prioridad
        foreach ($all_pending_inscriptions as $inscription) {
            $student_id = $inscription['student_id'];
            $row_index = $inscription['row_index'];
            $agent_to_assign = null;
            
            // A. PRIORIDAD 1: Agente de la última llamada
            $call_log = get_post_meta($student_id, '_sga_call_log', true);
            $call_info = $call_log[$row_index] ?? null;

            if ($call_info && isset($call_info['user_id'])) {
                $calling_agent_id = intval($call_info['user_id']);
                
                // Si el agente que llamó está en la lista de agentes seleccionados, se le asigna.
                if (in_array($calling_agent_id, $agent_ids)) {
                    $agent_to_assign = $calling_agent_id;
                }
            }
            
            // B. PRIORIDAD 2: Rotación forzada si no se encontró un agente llamador válido
            if (!$agent_to_assign) {
                // Usa _get_next_agent_from_list para obtener el siguiente agente rotativo
                $agent_to_assign = SGA_Utils::_get_next_agent_from_list($agent_ids);
            }
            
            // C. Asignar y registrar el log
            if ($agent_to_assign) {
                SGA_Utils::_assign_inscription_to_agent($student_id, $row_index, $agent_to_assign);
                
                if (!isset($assignments_log[$agent_to_assign])) $assignments_log[$agent_to_assign] = 0;
                $assignments_log[$agent_to_assign]++;
                $total_assigned++;
            }
        }


        // 3. Registrar la actividad final.
        $log_details = "Se repartieron {$total_assigned} inscripciones pendientes entre " . count($agent_ids) . " agentes. Resumen:\n";
        foreach ($assignments_log as $agent_id => $count) {
            $agent_info = get_userdata($agent_id);
            $agent_name = $agent_info ? $agent_info->display_name : "ID Desconocido ({$agent_id})";
            $log_details .= "- " . $agent_name . ": " . $count . " inscripciones.\n";
        }
        SGA_Utils::_log_activity('Inscripciones Repartidas', $log_details);

        wp_send_json_success(['message' => "Proceso de reparto completado. Se asignaron {$total_assigned} inscripciones."]);
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
     * AJAX: Imprime el expediente de un estudiante en PDF.
     */
    public function ajax_print_student_profile() {
        if (!isset($_GET['student_id']) || !isset($_GET['_wpnonce'])) {
            wp_die('Parámetros inválidos.');
        }
        $student_id = intval($_GET['student_id']);
        $nonce = sanitize_text_field($_GET['_wpnonce']);
        if (!wp_verify_nonce($nonce, 'sga_print_profile_' . $student_id)) {
            wp_die('Error de seguridad.');
        }
        // FIX: Cambiado 'read_estudiante' a 'read' para permitir a todos los usuarios conectados.
        if (!current_user_can('read')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        $reports_handler = new SGA_Reports();
        $pdf_data = $reports_handler->_generate_student_profile_pdf($student_id);

        if ($pdf_data && is_array($pdf_data) && !empty($pdf_data['pdf_data'])) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $pdf_data['filename'] . '"');
            echo $pdf_data['pdf_data'];
            SGA_Utils::_log_activity('Expediente Descargado', "El expediente del estudiante ID: {$student_id} fue descargado.");
        } else {
            wp_die('No se pudo generar el PDF. Verifique que la librería Dompdf esté instalada.');
        }
        wp_die();
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
        wp_send_json_success(['message' => "Proceso completado. Se asignaron {$sent_count} correos."]);
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

    /**
     * AJAX: Obtiene los datos para el gráfico de inscripciones en el panel de reportes.
     */
    public function ajax_get_report_chart_data() {
        if (!check_ajax_referer('sga_chart_nonce', '_ajax_nonce', false)) {
            wp_send_json_error(['message' => 'Error de seguridad.'], 403);
        }
        // Usamos la capacidad personalizada 'sga_access_reportes'
        if (!current_user_can('sga_access_reportes')) {
            wp_send_json_error(['message' => 'No tienes permisos para ver reportes.'], 403);
        }

        $labels = [];
        $counts_by_month = [];

        // Inicializar los últimos 12 meses
        for ($i = 11; $i >= 0; $i--) {
            $month_key = date('Y-m', strtotime("-$i months"));
            // Usar 'M Y' para formato "Oct 2024" y forzar localización en español
            $labels[] = date_i18n('M Y', strtotime($month_key . '-01'));
            $counts_by_month[$month_key] = 0;
        }

        // Consultar a todos los estudiantes
        $estudiantes = get_posts([
            'post_type' => 'estudiante',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);

        if ($estudiantes && function_exists('get_field')) {
            foreach ($estudiantes as $estudiante_id) {
                $cursos = get_field('cursos_inscritos', $estudiante_id);
                if ($cursos) {
                    foreach ($cursos as $curso) {
                        // Usar 'fecha_inscripcion' que se guarda en el repeater
                        if (!empty($curso['fecha_inscripcion'])) {
                            $inscription_date = strtotime($curso['fecha_inscripcion']);
                            if ($inscription_date) {
                                $month_key = date('Y-m', $inscription_date);
                                // Contar solo si el mes está en nuestro rango de 12 meses
                                if (array_key_exists($month_key, $counts_by_month)) {
                                    $counts_by_month[$month_key]++;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Preparar el array de datos final en el orden correcto
        $data = array_values($counts_by_month);

        wp_send_json_success(['labels' => $labels, 'data' => $data]);
    }
}
