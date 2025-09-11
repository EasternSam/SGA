<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_Integration
 *
 * Gestiona las integraciones con plugins de terceros, como Fluent Forms.
 */
class SGA_Integration {

    public function __construct() {
        add_action('fluentform/submission_inserted', array($this, 'procesar_inscripcion_y_crear_perfil'), 10, 3);
    }

    /**
     * Procesa el envío de un formulario de inscripción de Fluent Forms.
     */
    public function procesar_inscripcion_y_crear_perfil($entryId, $formData, $form) {
        if ($form->id != 3) return; // Asegúrate de que este es el ID correcto de tu formulario

        $cedula = isset($formData['cedula_o_identificacion']) ? sanitize_text_field($formData['cedula_o_identificacion']) : '';
        if (empty($cedula)) return;

        $curso_inscrito = isset($formData['nombre_del_curso']) ? sanitize_text_field($formData['nombre_del_curso']) : '';
        $horario_inscrito = isset($formData['horario_seleccionado']) ? sanitize_text_field($formData['horario_seleccionado']) : '';
        $nombre = isset($formData['names']['first_name']) ? sanitize_text_field($formData['names']['first_name']) : '';
        $apellido = isset($formData['names']['last_name']) ? sanitize_text_field($formData['names']['last_name']) : '';
        $email = isset($formData['email']) ? sanitize_email($formData['email']) : '';
        $telefono = isset($formData['phone']) ? sanitize_text_field($formData['phone']) : '';
        $direccion = isset($formData['address_1']['address_line_1']) ? sanitize_text_field($formData['address_1']['address_line_1']) : '';

        // Verificación de Cupos
        $curso_post_query = get_posts(array('post_type' => 'curso', 'title' => $curso_inscrito, 'posts_per_page' => 1));
        if ($curso_post_query && function_exists('get_field')) {
            $horarios = get_field('horarios_del_curso', $curso_post_query[0]->ID);
            if ($horarios) {
                foreach ($horarios as $horario) {
                    $horario_completo = $horario['dias_de_la_semana'] . ' ' . $horario['hora'];
                    if ($horario_completo === $horario_inscrito) {
                        $total_cupos = !empty($horario['numero_de_cupos']) ? intval($horario['numero_de_cupos']) : 0;
                        if ($total_cupos > 0) {
                            $cupos_ocupados = SGA_Utils::_get_cupos_ocupados($curso_inscrito, $horario_inscrito);
                            if ($cupos_ocupados >= $total_cupos) {
                                SGA_Utils::_log_activity('Intento de Inscripción a Curso Lleno', "Se bloqueó una inscripción para '{$curso_inscrito}' en el horario '{$horario_inscrito}' porque los cupos están agotados ({$cupos_ocupados}/{$total_cupos}).");
                                SGA_Utils::_send_course_full_notification_email($nombre . ' ' . $apellido, $email, $curso_inscrito, $horario_inscrito);
                                return;
                            }
                        }
                        break;
                    }
                }
            }
        }

        $estudiante_existente = get_posts(array('post_type' => 'estudiante', 'meta_key' => 'cedula', 'meta_value' => $cedula, 'posts_per_page' => 1));

        if ($estudiante_existente) {
            $post_id = $estudiante_existente[0]->ID;
            update_field('email', $email, $post_id);
            update_field('telefono', $telefono, $post_id);
            update_field('direccion', $direccion, $post_id);
            $log_title = 'Inscripción Actualizada';
        } else {
            $post_id = wp_insert_post(array(
                'post_title' => $nombre . ' ' . $apellido,
                'post_type' => 'estudiante',
                'post_status' => 'publish'
            ));
            update_field('cedula', $cedula, $post_id);
            update_field('nombre', $nombre, $post_id);
            update_field('apellido', $apellido, $post_id);
            update_field('email', $email, $post_id);
            update_field('telefono', $telefono, $post_id);
            update_field('direccion', $direccion, $post_id);
            $log_title = 'Nuevo Estudiante Creado';
        }

        if (function_exists('add_row')) {
            add_row('cursos_inscritos', array(
                'nombre_curso' => $curso_inscrito,
                'horario' => $horario_inscrito,
                'fecha_inscripcion' => date('Y-m-d H:i:s'),
                'estado' => 'Inscrito'
            ), $post_id);
        }

        SGA_Utils::_send_pending_payment_email($nombre . ' ' . $apellido, $email, $cedula, $curso_inscrito, $horario_inscrito);
        SGA_Utils::_log_activity($log_title, "Estudiante: {$nombre} {$apellido} (Cédula: {$cedula}) se ha inscrito en el curso '{$curso_inscrito}'.", 0);
    }
}
