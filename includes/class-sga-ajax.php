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
        add_action('wp_ajax_sga_print_student_profile', array($this, 'ajax_print_student_profile')); // <- HOOK EXISTENTE (Descarga PDF)
        add_action('wp_ajax_sga_get_student_profile_data', array($this, 'ajax_get_student_profile_data'));
        add_action('wp_ajax_sga_update_student_profile_data', array($this, 'ajax_update_student_profile_data'));
        add_action('wp_ajax_sga_send_bulk_email', array($this, 'ajax_send_bulk_email'));
        add_action('wp_ajax_sga_get_report_chart_data', array($this, 'ajax_get_report_chart_data'));
        add_action('wp_ajax_sga_test_api_connection', array($this, 'ajax_test_api_connection'));
        add_action('wp_ajax_sga_test_incoming_webhook', array($this, 'ajax_test_incoming_webhook'));
        // add_action('wp_ajax_sga_update_call_status', array($this, 'ajax_update_call_status')); // <-- ELIMINADO: Esta acción ya no se usa
        add_action('wp_ajax_sga_marcar_llamado', array($this, 'ajax_sga_marcar_llamado'));
        add_action('wp_ajax_sga_edit_llamado_comment', array($this, 'ajax_sga_edit_llamado_comment'));
        add_action('wp_ajax_sga_get_panel_view_html', array($this, 'ajax_get_panel_view_html'));
        add_action('wp_ajax_sga_check_pending_inscriptions', array($this, 'ajax_check_pending_inscriptions'));
        add_action('wp_ajax_sga_distribute_inscriptions', array($this, 'ajax_distribute_pending_inscriptions'));

        // NUEVO HOOK para renderizar HTML del expediente para impresión directa
        add_action('wp_ajax_sga_render_student_profile_for_print', array($this, 'ajax_render_student_profile_for_print'));
        
        // *** INICIO - HOOK DE PAGINACIÓN INSCRIPCIONES ***
        add_action('wp_ajax_sga_get_paginated_inscriptions', array($this, 'ajax_get_paginated_inscriptions'));
        // *** FIN - HOOK DE PAGINACIÓN INSCRIPCIONES ***

        // *** INICIO - NUEVO HOOK DE PAGINACIÓN DE ESTUDIANTES ***
        add_action('wp_ajax_sga_get_paginated_students', array($this, 'ajax_get_paginated_students'));
        // *** FIN - NUEVO HOOK DE PAGINACIÓN DE ESTUDIANTES ***

        // Hooks AJAX para usuarios no logueados (ej. imprimir factura desde el correo)
        add_action('wp_ajax_nopriv_sga_print_invoice', array($this, 'ajax_sga_print_invoice'));
    }

    /**
     * AJAX: Renderiza el HTML del expediente para impresión directa (no PDF).
     */
    public function ajax_render_student_profile_for_print() {
        if (!isset($_POST['student_id']) || !isset($_POST['_ajax_nonce'])) {
            wp_send_json_error(['message' => 'Parámetros inválidos.'], 400);
        }

        $student_id = intval($_POST['student_id']);
        $nonce = sanitize_text_field($_POST['_ajax_nonce']);

        if (!wp_verify_nonce($nonce, 'sga_render_print_profile_' . $student_id)) {
            wp_send_json_error(['message' => 'Error de seguridad.'], 403);
        }
        
        if (!current_user_can('read')) {
            wp_send_json_error(['message' => 'No tienes permisos para acceder a esta acción.'], 403);
        }

        // Utilizamos la nueva función para obtener el HTML con estilos de impresión
        $html_content = SGA_Utils::_get_student_profile_print_html($student_id);

        if ($html_content) {
            SGA_Utils::_log_activity('Expediente para Impresión', "El expediente del estudiante ID: {$student_id} fue cargado para impresión.", get_current_user_id());
            wp_send_json_success(['html' => $html_content]);
        } else {
            wp_send_json_error(['message' => 'No se pudo generar el HTML del expediente.'], 500);
        }
    }


    /**
     * AJAX: Reparte las inscripciones pendientes no asignadas entre los agentes seleccionados,
     * respetando la prioridad del agente que ya llamó.
     */
    public function ajax_distribute_pending_inscriptions() {
        // CORRECCIÓN: Usar check_ajax_referer con die=false y manejar el error
        if (check_ajax_referer('sga_distribute_nonce', 'security', false) === false) {
             wp_send_json_error(['message' => 'Error de seguridad (Nonce distribute).'], 403);
        }
        if (!current_user_can('manage_options') && !current_user_can('gestor_academico')) {
            wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción.'], 403);
        }

        $agent_ids = isset($_POST['agent_ids']) ? array_map('intval', $_POST['agent_ids']) : [];
        if (empty($agent_ids)) {
            wp_send_json_error(['message' => 'No se seleccionaron agentes.']);
        }
        
        // --- INICIO MODIFICACIÓN: Separar agentes seleccionados por rol ---
        $infotep_agent_ids = [];
        $general_agent_ids = [];
        
        foreach ($agent_ids as $agent_id) {
            $user_data = get_userdata($agent_id);
            if ($user_data && in_array('agente_infotep', (array)$user_data->roles)) {
                $infotep_agent_ids[] = $agent_id;
            } else if ($user_data && in_array('agente', (array)$user_data->roles)) {
                // Asumimos que cualquier otro agente (que no sea infotep) es general
                $general_agent_ids[] = $agent_id;
            }
        }
        // --- FIN MODIFICACIÓN ---
        
        // 1. Recolectar TODAS las inscripciones pendientes.
        $all_pending_inscriptions = [];
        $estudiantes = get_posts(['post_type' => 'estudiante', 'posts_per_page' => -1]);
        
        // --- INICIO MODIFICACIÓN: Pre-cargar mapa de categorías de cursos ---
        $all_cursos = get_posts(array('post_type' => 'curso', 'posts_per_page' => -1, 'post_status' => array('publish', 'private')));
        $course_category_map = [];
        $course_ids_to_check = wp_list_pluck($all_cursos, 'ID');
        $infotep_slug = 'cursos-infotep'; // Asumir slug

        if (!empty($course_ids_to_check)) {
            $all_terms = wp_get_object_terms($course_ids_to_check, 'category', ['fields' => 'all_with_object_id']);
            $terms_by_course_id = [];
            foreach ($all_terms as $term) {
                if (!isset($terms_by_course_id[$term->object_id])) $terms_by_course_id[$term->object_id] = [];
                $terms_by_course_id[$term->object_id][] = $term->slug;
            }
            foreach ($all_cursos as $course_post) {
                $course_category_map[$course_post->post_title] = $terms_by_course_id[$course_post->ID] ?? [];
            }
        }
        // --- FIN MODIFICACIÓN ---

        foreach ($estudiantes as $estudiante) {
            $cursos = get_field('cursos_inscritos', $estudiante->ID);
            
            if ($cursos) {
                foreach ($cursos as $index => $curso) {
                    if (isset($curso['estado']) && $curso['estado'] === 'Inscrito') {
                        
                        // --- INICIO MODIFICACIÓN: Determinar si el curso es de Infotep ---
                        $course_name = $curso['nombre_curso'];
                        $course_categories = $course_category_map[$course_name] ?? [];
                        $is_infotep_course = in_array($infotep_slug, $course_categories);
                        // --- FIN MODIFICACIÓN ---
                        
                        $all_pending_inscriptions[] = [
                            'student_id' => $estudiante->ID,
                            'row_index'  => $index,
                            'course_name' => $course_name,
                            'is_infotep' => $is_infotep_course // Guardar esta información
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
            // --- INICIO MODIFICACIÓN: Obtener tipo de curso ---
            $is_infotep_course = $inscription['is_infotep'];
            // --- FIN MODIFICACIÓN ---
            
            $agent_to_assign = null;
            $should_apply_rotation = true;
            
            // A. PRIORIDAD 1: Agente de la última llamada
            $call_log = get_post_meta($student_id, '_sga_call_log', true);
            $call_info = $call_log[$row_index] ?? null;

            if ($call_info && isset($call_info['user_id'])) {
                $calling_agent_id = intval($call_info['user_id']);
                
                // --- INICIO MODIFICACIÓN: Revisar si el agente que llamó es del tipo correcto ---
                // Si el agente que llamó está en la lista de agentes seleccionados *Y ES DEL TIPO CORRECTO*:
                if ($is_infotep_course && in_array($calling_agent_id, $infotep_agent_ids)) {
                    $agent_to_assign = $calling_agent_id;
                    $should_apply_rotation = false; 
                } else if (!$is_infotep_course && in_array($calling_agent_id, $general_agent_ids)) {
                    $agent_to_assign = $calling_agent_id;
                    $should_apply_rotation = false;
                }
                // --- FIN MODIFICACIÓN ---
            }
            
            // B. PRIORIDAD 2: Rotación
            if ($should_apply_rotation) {
                // --- INICIO MODIFICACIÓN: Asignar al grupo correcto ---
                if ($is_infotep_course) {
                    if (!empty($infotep_agent_ids)) {
                        // Usar un sufijo de transient_key diferente para la rotación de Infotep
                        $agent_to_assign = SGA_Utils::_get_next_agent_from_list($infotep_agent_ids, 'infotep_distribute');
                    }
                } else {
                    if (!empty($general_agent_ids)) {
                        // Usar un sufijo de transient_key diferente para la rotación General
                        $agent_to_assign = SGA_Utils::_get_next_agent_from_list($general_agent_ids, 'general_distribute');
                    }
                }
                // --- FIN MODIFICACIÓN ---
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
        // CORRECCIÓN: Usar 'security' como nombre del campo nonce si no se especifica explícitamente en JS
        if (check_ajax_referer('sga_pending_nonce', 'security', false) === false) {
            wp_send_json_error(['message' => 'Error de seguridad (Nonce check).'], 403);
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
        // CORRECCIÓN: Usar check_ajax_referer con die=false y manejar el error
        if (check_ajax_referer('aprobar_nonce', '_ajax_nonce', false) === false) {
             wp_send_json_error(['message' => 'Error de seguridad (Nonce aprobar).'], 403);
        }
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
        // CORRECCIÓN: Usar check_ajax_referer con die=false y manejar el error
        if (check_ajax_referer('aprobar_bulk_nonce', '_ajax_nonce', false) === false) {
             wp_send_json_error(['message' => 'Error de seguridad (Nonce aprobar bulk).'], 403);
        }
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
     * AJAX: Genera y descarga un archivo Excel con el registro de llamadas.
     */
    public function ajax_exportar_registro_llamadas() {
        // No necesita check_ajax_referer porque ya se verifica en SGA_Reports
        $reports_handler = new SGA_Reports();
        $reports_handler->exportar_registro_llamadas();
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
        
        // Esta función ya no se usa para generar PDF. Ahora se usa 'ajax_render_student_profile_for_print' para impresión directa.
        // Mantenemos este código por si hay enlaces directos en el sistema, pero el botón principal ya no lo usa.

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
        // *** INICIO - MODIFICACIÓN DE PAGINACIÓN ***
        // No permitir que esta función cargue vistas que tienen su propio cargador AJAX.
        $view = sanitize_key($_POST['view']);
        if ($view === 'enviar_a_matriculacion' || $view === 'lista_estudiantes') {
             wp_send_json_error(['message' => 'Esta vista no se puede cargar de esta forma.']);
             return;
        }
        // *** FIN - MODIFICACIÓN DE PAGINACIÓN ***

        if (!current_user_can('edit_estudiantes')) {
            wp_send_json_error(['message' => 'No tienes permisos.']);
        }

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
    
    // *** INICIO - NUEVA FUNCIÓN AJAX DE PAGINACIÓN ***
    /**
     * AJAX: Obtiene el HTML de las tablas de inscripciones paginadas y filtradas.
     * Esta función reemplaza a `ajax_get_panel_view_html` para la vista 'enviar_a_matriculacion'.
     */
    public function ajax_get_paginated_inscriptions() {
        // --- INICIO DEBUG AVANZADO ---
        // Envolver toda la función en un try...catch para capturar errores fatales
        // (Throwable es para PHP 7+)
        try {
        // --- FIN DEBUG AVANZADO ---
        
            // 1. Seguridad y Permisos
            if (!isset($_POST['_ajax_nonce']) || !check_ajax_referer('sga_get_view_nonce', '_ajax_nonce', false)) {
                wp_send_json_error(['message' => 'Error de seguridad (Nonce).']);
            }
            if (!current_user_can('edit_estudiantes')) {
                wp_send_json_error(['message' => 'No tienes permisos.']);
            }

            // 2. Sanitizar todos los datos de entrada
            $paged_nuevas = isset($_POST['paged_nuevas']) ? absint($_POST['paged_nuevas']) : 1;
            $paged_seguimiento = isset($_POST['paged_seguimiento']) ? absint($_POST['paged_seguimiento']) : 1;
            $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
            $course = isset($_POST['course']) ? sanitize_text_field($_POST['course']) : '';
            $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';
            $agent = isset($_POST['agent']) ? sanitize_text_field($_POST['agent']) : ''; // Puede ser 'unassigned' o un ID

            // 3. Obtener datos de visibilidad del usuario (igual que en la carga inicial de la vista)
            $role_helper = new SGA_Panel_Views_Part1(); // Solo para el helper de roles
            $can_approve = $role_helper->sga_user_has_role(['administrator', 'gestor_academico']);
            
            $is_infotep_agent = $role_helper->sga_user_has_role(['agente_infotep']);
            $is_standard_agent = $role_helper->sga_user_has_role(['agente']);
            $current_user_role = $is_infotep_agent ? 'agente_infotep' : ($is_standard_agent ? 'agente' : null);
            
            $agent_visibility_ids = [];
            if ($current_user_role) {
                $same_role_agents = get_users(['role' => $current_user_role, 'fields' => 'ID']);
                $agent_visibility_ids = array_map('intval', $same_role_agents);
            }

            // 4. Construir argumentos para la función de Utils
            $args = [
                'paged_nuevas' => $paged_nuevas,
                'paged_seguimiento' => $paged_seguimiento,
                'posts_per_page' => 50, // 50 por página
                'search' => $search,
                'course' => $course,
                'status' => $status,
                'agent' => $agent,
                'current_user_role' => $current_user_role,
                'agent_visibility_ids' => $agent_visibility_ids,
                'can_approve' => $can_approve
            ];

            // 5. Llamar a la función de renderizado estática (que crearemos en el siguiente paso)
            // Esta función hará el trabajo de llamar a SGA_Utils y generar el HTML.
            // Necesita ser estática para que podamos llamarla sin instanciar la clase aquí.
            // Asumimos que `SGA_Panel_Views_Part2` ya está cargada (lo está, desde class-sga-main.php)
            $html_data = SGA_Panel_Views_Part2::get_paginated_table_html($args);

            // 6. Enviar respuesta JSON
            wp_send_json_success($html_data);

        // --- INICIO DEBUG AVANZADO ---
        } catch (Throwable $e) {
            // Si cualquier cosa falla (Error Fatal de PHP), lo capturamos
            $error_details = [
                'message' => 'Error Fatal de PHP capturado.',
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ];
            // Escribir también en el log de sga-debug por si acaso
            if (class_exists('SGA_Utils')) {
                SGA_Utils::_debug_log("¡ERROR FATAL CAPTURADO! :: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
            }
            wp_send_json_error($error_details, 500); // Enviar el error 500 pero con un JSON
        }
        // --- FIN DEBUG AVANZADO ---
    }
    // *** FIN - NUEVA FUNCIÓN AJAX DE PAGINACIÓN ***
    
    // *** INICIO - NUEVA FUNCIÓN AJAX DE PAGINACIÓN DE ESTUDIANTES ***
    /**
     * AJAX: Obtiene el HTML de la tabla de estudiantes generales paginada y filtrada.
     */
    public function ajax_get_paginated_students() {
        try {
            // 1. Seguridad y Permisos
            if (!isset($_POST['_ajax_nonce']) || !check_ajax_referer('sga_get_view_nonce', '_ajax_nonce', false)) {
                wp_send_json_error(['message' => 'Error de seguridad (Nonce).']);
            }
            if (!current_user_can('edit_estudiantes')) {
                wp_send_json_error(['message' => 'No tienes permisos.']);
            }

            // 2. Sanitizar todos los datos de entrada
            $paged = isset($_POST['paged']) ? absint($_POST['paged']) : 1;
            $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
            
            // 3. Construir argumentos para la función de Utils
            $args = [
                'paged' => $paged,
                'posts_per_page' => 50, // 50 por página (coincidir con Part2)
                'search' => $search,
            ];
            
            // 4. Llamar a la función de renderizado estática
            // Asumimos que `SGA_Panel_Views_Part2` ya está cargada
            $html_data = SGA_Panel_Views_Part2::get_paginated_students_table_html($args);

            // 5. Enviar respuesta JSON
            wp_send_json_success($html_data);

        } catch (Throwable $e) {
            // Si cualquier cosa falla (Error Fatal de PHP), lo capturamos
            $error_details = [
                'message' => 'Error Fatal de PHP capturado.',
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ];
            if (class_exists('SGA_Utils')) {
                SGA_Utils::_debug_log("¡ERROR FATAL CAPTURADO! :: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
            }
            wp_send_json_error($error_details, 500); // Enviar el error 500 pero con un JSON
        }
    }
    // *** FIN - NUEVA FUNCIÓN AJAX DE PAGINACIÓN DE ESTUDIANTES ***


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

    // --- FUNCIONES AÑADIDAS PARA CORREGIR EL ERROR 500 ---

    /**
     * AJAX: Marca una inscripción como llamada por primera vez.
     * Crea el CPT sga_llamada y actualiza el meta _sga_call_log.
     */
    public function ajax_sga_marcar_llamado() {
        // 1. Verificación de seguridad (Nonce)
        if (!isset($_POST['_ajax_nonce'], $_POST['post_id'], $_POST['row_index'])) {
            wp_send_json_error(['message' => 'Datos incompletos.'], 400);
        }
        $post_id = intval($_POST['post_id']);
        $row_index = intval($_POST['row_index']);
        $nonce = sanitize_text_field($_POST['_ajax_nonce']);

        if (!wp_verify_nonce($nonce, 'sga_marcar_llamado_' . $post_id . '_' . $row_index)) {
            wp_send_json_error(['message' => 'Error de seguridad (Nonce).'], 403);
        }

        // 2. Verificación de permisos
        if (!current_user_can('edit_estudiantes')) {
            wp_send_json_error(['message' => 'No tienes permisos para esta acción.'], 403);
        }

        $comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';
        // --- INICIO: MODIFICACIÓN SOLICITADA ---
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : 'pendiente'; // Obtener el estado del POST
        // --- FIN: MODIFICACIÓN SOLICITADA ---
        
        $user_id = get_current_user_id();
        $user_info = get_userdata($user_id);
        $student_post = get_post($post_id);
        if (!$student_post) {
            wp_send_json_error(['message' => 'Estudiante no encontrado.'], 404);
        }

        $cursos = get_field('cursos_inscritos', $post_id);
        $curso_info = $cursos[$row_index] ?? null;
        if (!$curso_info) {
            wp_send_json_error(['message' => 'Curso no encontrado en el índice.'], 404);
        }

        // 3. Crear el CPT 'sga_llamada'
        $call_log_post_id = wp_insert_post([
            'post_type'    => 'sga_llamada',
            'post_title'   => 'Llamada a ' . $student_post->post_title . ' por ' . $user_info->display_name,
            'post_content' => $comment,
            'post_status'  => 'publish',
            'post_author'  => $user_id,
        ]);

        if (is_wp_error($call_log_post_id)) {
            wp_send_json_error(['message' => 'Error al crear el registro CPT: ' . $call_log_post_id->get_error_message()], 500);
        }

        // 4. Actualizar meta del CPT
        update_post_meta($call_log_post_id, '_student_id', $post_id);
        update_post_meta($call_log_post_id, '_row_index', $row_index);
        update_post_meta($call_log_post_id, '_student_name', $student_post->post_title);
        update_post_meta($call_log_post_id, '_course_name', $curso_info['nombre_curso']);

        // 5. Actualizar meta de la inscripción (_sga_call_log)
        $call_log_meta = get_post_meta($post_id, '_sga_call_log', true);
        if (!is_array($call_log_meta)) {
            $call_log_meta = [];
        }
        $call_info = [
            'user_id'     => $user_id,
            'user_name'   => $user_info->display_name,
            'timestamp'   => time(),
            'comment'     => $comment,
            'cpt_log_id'  => $call_log_post_id,
        ];
        $call_log_meta[$row_index] = $call_info;
        update_post_meta($post_id, '_sga_call_log', $call_log_meta);
        
        // --- INICIO: MODIFICACIÓN SOLICITADA ---
        // 6. Guardar el estado de la llamada
        $call_statuses = get_post_meta($post_id, '_sga_call_statuses', true);
        if (!is_array($call_statuses)) {
            $call_statuses = [];
        }
        $call_statuses[$row_index] = $status;
        update_post_meta($post_id, '_sga_call_statuses', $call_statuses);
        // --- FIN: MODIFICACIÓN SOLICITADA ---


        // 7. Generar el HTML de respuesta
        $html_response = SGA_Utils::_get_call_log_html($post_id, $row_index, $call_info, $call_log_post_id, true);
        
        SGA_Utils::_log_activity('Llamada Registrada', "{$user_info->display_name} marcó como llamado a {$student_post->post_title} (curso {$curso_info['nombre_curso']}) y actualizó el estado a '{$status}'.", $user_id);
        
        wp_send_json_success(['html' => $html_response]);
    }

    /**
     * AJAX: Edita el comentario de una llamada ya registrada.
     * Actualiza el CPT sga_llamada y el meta _sga_call_log.
     */
    public function ajax_sga_edit_llamado_comment() {
        // 1. Verificación de seguridad
        if (!isset($_POST['_ajax_nonce'], $_POST['student_id'], $_POST['row_index'], $_POST['log_id'])) {
            wp_send_json_error(['message' => 'Datos incompletos.'], 400);
        }
        
        $student_id = intval($_POST['student_id']);
        $row_index = intval($_POST['row_index']);
        $log_id = intval($_POST['log_id']); // ID del CPT sga_llamada
        $nonce = sanitize_text_field($_POST['_ajax_nonce']);
        $comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';
        // --- INICIO: MODIFICACIÓN SOLICITADA ---
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : 'pendiente'; // Obtener el estado del POST
        // --- FIN: MODIFICACIÓN SOLICITADA ---

        // El nonce de edición se genera dinámicamente en el JS
        if (!wp_verify_nonce($nonce, 'sga_edit_llamado_' . $student_id . '_' . $row_index)) {
             wp_send_json_error(['message' => 'Error de seguridad (Nonce edición).'], 403);
        }
        
        // 2. Permisos
        if (!current_user_can('edit_estudiantes')) {
            wp_send_json_error(['message' => 'No tienes permisos para esta acción.'], 403);
        }

        // 3. Actualizar el comentario usando la función de Utils
        $user_name = wp_get_current_user()->display_name;
        $updated = SGA_Utils::_update_call_log_comment($log_id, $student_id, $row_index, $comment, $user_name);
        
        if (!$updated) {
             wp_send_json_error(['message' => 'No se pudo actualizar el comentario. El registro de llamada no fue encontrado.'], 404);
        }

        // --- INICIO: MODIFICACIÓN SOLICITADA ---
        // 4. Guardar el estado de la llamada
        $call_statuses = get_post_meta($student_id, '_sga_call_statuses', true);
        if (!is_array($call_statuses)) {
            $call_statuses = [];
        }
        $call_statuses[$row_index] = $status;
        update_post_meta($student_id, '_sga_call_statuses', $call_statuses);

        // Loggear el cambio de estado
        $student_post = get_post($student_id);
        SGA_Utils::_log_activity(
            'Llamada/Estado Actualizado', 
            "{$user_name} actualizó la llamada para {$student_post->post_title} (inscripción #{$row_index}) al estado '{$status}'.", 
            get_current_user_id()
        );
        // --- FIN: MODIFICACIÓN SOLICITADA ---

        // 5. Obtener la información actualizada
        $call_log_meta = get_post_meta($student_id, '_sga_call_log', true);
        $call_info = $call_log_meta[$row_index] ?? null;
        
        if (!$call_info) {
             wp_send_json_error(['message' => 'No se pudo recuperar la información de la llamada actualizada.'], 500);
        }
        
        // 6. Generar y enviar el nuevo HTML
        $html_response = SGA_Utils::_get_call_log_html($student_id, $row_index, $call_info, $log_id, true);

        wp_send_json_success(['html' => $html_response]);
    }
    
    // --- INICIO: MODIFICACIÓN SOLICITADA ---
    /**
     * AJAX: Actualiza el estado de la llamada para una inscripción específica.
     * ESTA FUNCIÓN HA SIDO ELIMINADA PORQUE SU LÓGICA SE MOVIÓ A
     * ajax_sga_marcar_llamado() y ajax_sga_edit_llamado_comment()
     */
    // public function ajax_update_call_status() { ... }
    // --- FIN: MODIFICACIÓN SOLICITADA ---
}

