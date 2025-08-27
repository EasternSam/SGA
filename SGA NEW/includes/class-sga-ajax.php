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
        add_action('wp_ajax_sga_print_invoice', array($this, 'ajax_sga_print_invoice'));
        add_action('wp_ajax_sga_get_student_profile_data', array($this, 'ajax_get_student_profile_data'));
        add_action('wp_ajax_sga_update_student_profile_data', array($this, 'ajax_update_student_profile_data'));
        add_action('wp_ajax_sga_send_bulk_email', array($this, 'ajax_send_bulk_email'));

        // Hooks AJAX para usuarios no logueados (ej. imprimir factura desde el correo)
        add_action('wp_ajax_nopriv_sga_print_invoice', array($this, 'ajax_sga_print_invoice'));
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
        $body_html = wp_kses_post(stripslashes_deep($_POST['body']));

        $recipients = [];
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
                if ($add_email && !in_array($email, $recipients)) {
                    $recipients[] = $email;
                }
            }
        }

        if (empty($recipients)) {
            wp_send_json_error(['message' => 'No se encontraron destinatarios para los criterios seleccionados.']);
        }

        $email_template = SGA_Utils::_get_email_template($subject, $body_html);
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        $sent_count = 0;
        foreach ($recipients as $recipient) {
            if (wp_mail($recipient, $subject, $email_template, $headers)) {
                $sent_count++;
            }
        }

        SGA_Utils::_log_activity('Correo Masivo Enviado', "Se enviaron {$sent_count} de " . count($recipients) . " correos al grupo '{$recipient_group}'.");
        wp_send_json_success(['message' => "Proceso completado. Se enviaron {$sent_count} correos."]);
    }
}
