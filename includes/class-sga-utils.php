<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_Utils
 *
 * Contiene funciones de utilidad estáticas que se pueden usar
 * en cualquier parte del plugin para evitar la duplicación de código.
 */
class SGA_Utils {

    /**
     * Registra una actividad en el CPT 'gestion_log'.
     * @param string $title Título del registro.
     * @param string $content Contenido o detalle del registro.
     * @param int|null $user_id ID del usuario que realiza la acción. Si es null, usa el usuario actual. Si es 0, es el sistema.
     * @param bool $is_api Indica si el registro es específico de la API para facilitar el filtrado.
     */
    public static function _log_activity($title, $content = '', $user_id = null, $is_api = false) {
        if (is_null($user_id)) $user_id = get_current_user_id();
        $post_id = wp_insert_post(array(
            'post_title' => $title,
            'post_content' => $content,
            'post_type' => 'gestion_log',
            'post_status' => 'publish',
        ));
        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, '_log_user_id', $user_id);
            if ($is_api) {
                update_post_meta($post_id, '_is_api_log', '1');
            }
        }
    }

    /**
     * Obtiene una lista de todos los usuarios con el rol 'agente' o un rol específico.
     * @param string $role_slug El slug del rol a buscar ('agente', 'agente_infotep', etc.).
     * @return array Array de objetos WP_User.
     */
    public static function _get_sga_agents($role_slug = 'agente') {
        $args = array(
            'role'    => $role_slug,
            'orderby' => 'display_name',
            'order'   => 'ASC'
        );
        $agents = get_users($args);
        return $agents;
    }

    /**
     * Asigna una inscripción específica a un agente.
     * @param int $student_id ID del post del estudiante.
     * @param int $row_index Índice de la fila del curso en el repeater.
     * @param int $agent_id ID del usuario del agente.
     */
    public static function _assign_inscription_to_agent($student_id, $row_index, $agent_id) {
        $assignments = get_post_meta($student_id, '_sga_agent_assignments', true);
        if (!is_array($assignments)) {
            $assignments = [];
        }
        $assignments[$row_index] = (int)$agent_id;
        update_post_meta($student_id, '_sga_agent_assignments', $assignments);
    }

    /**
     * Obtiene el siguiente agente en la rotación para asignación automática, filtrando por rol.
     * Utiliza el mismo transient para mantener la rotación del rol.
     * @param string $role_slug El slug del rol a buscar.
     * @return int|null ID del agente o null si no hay agentes.
     */
    public static function _get_next_agent_for_assignment($role_slug = 'agente') {
        $agents = self::_get_sga_agents($role_slug);
        if (empty($agents)) {
            return null;
        }

        // Usar un transient separado para la rotación de cada rol
        $transient_key = 'sga_last_assigned_agent_index_' . $role_slug;

        $last_assigned_index = get_transient($transient_key);

        if (false === $last_assigned_index || $last_assigned_index >= count($agents) - 1) {
            $next_index = 0;
        } else {
            $next_index = ($last_assigned_index + 1);
        }

        set_transient($transient_key, $next_index, DAY_IN_SECONDS);
        return $agents[$next_index]->ID;
    }

    /**
     * Obtiene el siguiente agente en la rotación basado en una lista de IDs proporcionada.
     * Utilizado para el reparto manual.
     * @param array $agent_ids Lista de IDs de agentes seleccionados para la rotación.
     * @return int|null ID del agente o null si la lista está vacía.
     */
    public static function _get_next_agent_from_list($agent_ids) {
        if (empty($agent_ids)) {
            return null;
        }

        // Asegurar que solo tengamos IDs únicos y válidos
        $valid_agent_ids = array_filter(array_unique(array_map('intval', $agent_ids)));
        if (empty($valid_agent_ids)) {
            return null;
        }

        // Usar un transient único para esta acción de reparto manual
        $transient_key = 'sga_last_distributed_agent_index';
        $agent_count = count($valid_agent_ids);

        $last_assigned_index = get_transient($transient_key);

        if (false === $last_assigned_index || $last_assigned_index >= $agent_count - 1) {
            $next_index = 0;
        } else {
            $next_index = ($last_assigned_index + 1);
        }

        set_transient($transient_key, $next_index, DAY_IN_SECONDS);

        // Necesitamos reindexar el array para usar el índice de rotación
        $indexed_agents = array_values($valid_agent_ids);
        return $indexed_agents[$next_index];
    }


    /**
     * Genera el HTML para mostrar el log de comentarios y el botón de acción.
     *
     * @param int $student_id ID del estudiante.
     * @param int $row_index Índice del curso.
     * @return string HTML generado.
     */
    public static function _get_call_log_html($student_id, $row_index) {
        $call_log_all = get_post_meta($student_id, '_sga_call_log', true);
        $comments_for_row = [];
        if (is_array($call_log_all) && isset($call_log_all[$row_index]) && is_array($call_log_all[$row_index])) {
            $comments_for_row = $call_log_all[$row_index];
        }

        $current_user_id = get_current_user_id();
        $last_comment = !empty($comments_for_row) ? end($comments_for_row) : null;
        $can_edit_last = ($last_comment && isset($last_comment['user_id']) && $last_comment['user_id'] == $current_user_id);

        ob_start();

        if (!empty($comments_for_row)) {
            echo '<div class="sga-call-comments-wrapper" style="max-height: 150px; overflow-y: auto; margin-bottom: 10px; padding-right: 5px;">'; // Contenedor scrollable
            foreach ($comments_for_row as $comment_info) {
                ?>
                <div class="sga-call-comment-block">
                    <p class="sga-call-comment-meta">
                        <strong><?php echo esc_html($comment_info['user_name'] ?? 'N/A'); ?></strong>
                        <small>(<?php echo esc_html(isset($comment_info['timestamp']) ? date_i18n('d/m/Y H:i', $comment_info['timestamp']) : 'Fecha desconocida'); ?>)</small>
                    </p>
                    <p class="sga-call-comment-text">
                        <?php echo nl2br(esc_html($comment_info['comment'] ?? '')); ?>
                        <?php if (isset($comment_info['edited']) && $comment_info['edited']) : ?>
                            <em>(editado)</em>
                        <?php endif; ?>
                    </p>
                </div>
                <?php
            }
            echo '</div>'; // Fin del contenedor scrollable
        } else {
            echo '<p style="font-size: 12px; color: var(--sga-text-light); margin-bottom: 10px;"><em>No hay comentarios previos.</em></p>';
        }

        // --- INICIO LÓGICA DE BOTÓN MODIFICADA ---
        $last_comment_text_attr = '';
        $last_author_id_attr = '';

        if (empty($comments_for_row)) {
            $button_text = 'Marcar como llamado';
            // Icono de teléfono
            $button_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-telephone-outbound" viewBox="0 0 16 16"><path d="M3.654 1.318a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.6 17.6 0 0 0 4.168 6.608 17.6 17.6 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.68.68 0 0 0-.58-.122l-2.19.547a1.75 1.75 0 0 1-1.657-.459L5.482 8.062a1.75 1.75 0 0 1-.46-1.657l.548-2.19a.68.68 0 0 0-.122-.58L3.654 1.318zM1.884.511a1.745 1.745 0 0 1 2.612.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.68.68 0 0 0 .178.643l2.457 2.457a.68.68 0 0 0 .644.178l2.189-.547a1.75 1.75 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.612l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.6 18.6 0 0 1-7.01-4.42 18.6 18.6 0 0 1-4.42-7.009c-.363-1.031-.038-2.137.703-2.877L1.885.511zM11 .5a.5.5 0 0 1 .5.5V3h2.5a.5.5 0 0 1 0 1H11.5v2.5a.5.5 0 0 1-1 0V4H8a.5.5 0 0 1 0-1h2.5V1a.5.5 0 0 1 .5-.5z"/></svg>';
            $last_author_id_attr = '0'; // No hay autor previo
        } else if ($can_edit_last) {
            $button_text = 'Editar comentario';
            // Icono de lápiz
            $button_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16"><path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/><path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/></svg>';
            $last_comment_text_attr = esc_attr($last_comment['comment'] ?? '');
            $last_author_id_attr = esc_attr($last_comment['user_id'] ?? 0);
        } else {
            $button_text = 'Añadir comentario';
            // Icono de más
            $button_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-circle" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/><path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/></svg>';
            $last_author_id_attr = esc_attr($last_comment['user_id'] ?? 0); // Pasamos el ID del último autor para que JS sepa que no puede editar.
        }
        ?>
        <button class="button button-secondary button-small sga-manage-comment-btn"
                data-last-author-id="<?php echo $last_author_id_attr; ?>"
                data-last-comment="<?php echo $last_comment_text_attr; ?>"
                data-has-comments="<?php echo empty($comments_for_row) ? '0' : '1'; ?>">
            <?php echo $button_icon; ?>
            <?php echo esc_html($button_text); ?>
        </button>
        <?php
        // --- FIN LÓGICA DE BOTÓN MODIFICADA ---

        return ob_get_clean();
    }


    /**
     * Envía el correo inicial al estudiante para que proceda con el pago.
     */
    public static function _send_pending_payment_email($student_name, $student_email, $student_cedula, $course_name, $horario) {
        if (empty($student_email) || !is_email($student_email)) {
            self::_log_activity('Error de Correo', "Intento de envío de correo de pago pendiente a dirección inválida: " . esc_html($student_email));
            return;
        }

        $payment_options = get_option('sga_payment_options');
        $payments_enabled = isset($payment_options['enable_payments']) && $payment_options['enable_payments'] == 1;

        if ($payments_enabled) {
            $precio_display = 'No especificado';
            $curso_post_query = get_posts(array('post_type' => 'curso', 'title' => $course_name, 'posts_per_page' => 1, 'post_status' => 'publish'));
            if ($curso_post_query && function_exists('get_field')) {
                $precio_del_curso = get_field('precio_del_curso', $curso_post_query[0]->ID);
                if ($precio_del_curso) {
                    $precio_display = $precio_del_curso;
                }
            }

            $subject = 'Hemos recibido tu solicitud de inscripción';
            $email_title = 'Inscripción Pendiente de Pago';
            $content_html = '<p>Hola ' . esc_html($student_name) . ',</p>';
            $content_html .= '<p>Gracias por inscribirte en CENTU. Hemos recibido tu solicitud y la hemos puesto en espera hasta que se complete el pago de la inscripción. A continuación, encontrarás los detalles de tu solicitud.</p>';
            $content_html .= '<h2>Siguiente Paso: Realizar el Pago</h2>';
            $content_html .= '<p class="last">Para completar tu inscripción y asegurar tu cupo, por favor realiza el pago a través de nuestro portal seguro. Una vez completado el pago, tu matrícula será procesada automáticamente.</p>';

            $payment_page_url = site_url('/pagos/');
            $payment_url_with_cedula = add_query_arg('identificador', urlencode($student_cedula), $payment_page_url);
            $payment_url_with_cedula = add_query_arg('tipo_id', 'cedula', $payment_url_with_cedula);

            $summary_table_title = 'Resumen de tu Solicitud';
            $summary_data = [
                'Estudiante' => $student_name,
                'Cédula' => $student_cedula,
                'Curso Solicitado' => $course_name,
                'Horario' => $horario,
                'Monto a Pagar' => $precio_display,
            ];

            $button_data = [
                'url' => $payment_url_with_cedula,
                'text' => 'Pagar Ahora'
            ];

            $body = self::_get_email_template($email_title, $content_html, $summary_table_title, $summary_data, $button_data);
            self::_log_activity('Correo Enviado', "Correo de pago pendiente enviado a {$student_name} ({$student_email}) para el curso '{$course_name}'.", 0);

        } else {
             // Payments are disabled, send manual payment instructions
            $subject = 'Hemos recibido tu solicitud de inscripción';
            $email_title = 'Solicitud de Inscripción Recibida';
            $content_html = '<p>Hola ' . esc_html($student_name) . ',</p>';
            $content_html .= '<p>Gracias por inscribirte en CENTU. Hemos recibido tu solicitud y nuestro equipo la está procesando. A continuación, encontrarás los detalles de tu solicitud.</p>';
            $content_html .= '<h2>Siguientes Pasos</h2>';
            $content_html .= '<p class="last">Nuestro equipo de admisiones se pondrá en contacto contigo a la brevedad para confirmar los detalles y guiarte con los siguientes pasos para completar tu matriculación. Tu cupo está reservado temporalmente.</p>';

            $summary_table_title = 'Resumen de tu Solicitud';
            $summary_data = [
                'Estudiante' => $student_name,
                'Cédula' => $student_cedula,
                'Curso Solicitado' => $course_name,
                'Horario' => $horario,
            ];

            $body = self::_get_email_template($email_title, $content_html, $summary_table_title, $summary_data);
            self::_log_activity('Correo Enviado', "Correo de inscripción (pagos manuales) enviado a {$student_name} ({$student_email}) para el curso '{$course_name}'.", 0);
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($student_email, $subject, $body, $headers);
    }

    /**
     * Lógica central para aprobar un estudiante, generar matrícula y enviar correo de confirmación.
     * @param int $post_id ID del post del estudiante.
     * @param int $row_index Índice de la fila del curso en el repeater.
     * @param string $cedula Cédula del estudiante.
     * @param string $nombre Nombre del estudiante.
     * @param bool $is_automatic_payment Indica si la aprobación es por pago automático/webhook.
     * @param array $payment_details Detalles del pago (opcional).
     * @return array|false Datos del estudiante aprobado o false si falla.
     */
    public static function _aprobar_estudiante($post_id, $row_index, $cedula, $nombre, $is_automatic_payment = false, $payment_details = []) {
        if (!function_exists('update_sub_field')) return false;

        $matricula = '';
        $es_primera_matricula = true;

        if (function_exists('get_field')) {
            // Check if student already has a matricula from the internal system saved
            $matricula_externa = get_post_meta($post_id, '_matricula_externa', true);
            if (!empty($matricula_externa)) {
                $matricula = $matricula_externa;
                $es_primera_matricula = false;
            } else {
                // Check other courses in this system
                $todos_los_cursos = get_field('cursos_inscritos', $post_id);
                if ($todos_los_cursos) {
                    foreach ($todos_los_cursos as $curso) {
                        if (!empty($curso['matricula'])) {
                            $matricula = $curso['matricula'];
                            $es_primera_matricula = false;
                            break;
                        }
                    }
                }
            }
        }

        if (empty($matricula)) {
            $year = date('y');
            $counter = get_option('sga_matricula_counter', 1);
            $sequence = str_pad($counter, 4, '0', STR_PAD_LEFT);
            $matricula = $year . '-' . $sequence;
            update_option('sga_matricula_counter', $counter + 1);
        }

        update_sub_field(array('cursos_inscritos', $row_index + 1, 'estado'), 'Matriculado', $post_id);
        update_sub_field(array('cursos_inscritos', $row_index + 1, 'matricula'), $matricula, $post_id);

        $email = get_field('email', $post_id);
        $cursos = get_field('cursos_inscritos', $post_id);
        $curso_aprobado = isset($cursos[$row_index]) ? $cursos[$row_index] : null;

        if ($curso_aprobado && !empty($email)) {
            $subject = "¡Has sido matriculado exitosamente!";
            $email_title = '¡Matriculación Exitosa!';
            $content_html = '<p>Hola ' . esc_html($nombre) . ',</p>';
            if ($es_primera_matricula) {
                $content_html .= '<p>Te informamos con gran alegría que tu inscripción ha sido procesada y has sido matriculado exitosamente. A continuación, encontrarás los detalles de tu matriculación.</p>';
                $content_html .= '<h2>Próximos Pasos</h2>';
                $content_html .= '<p class="last">Guarda tu número de matrícula, ya que será tu identificador principal. Pronto recibirás más información sobre el inicio de clases y acceso a nuestra plataforma. ¡Te damos la bienvenida a CENTU!</p>';
            } else {
                $content_html .= '<p>Nos complace informarte que hemos añadido un nuevo curso a tu perfil. Hemos utilizado tu número de matrícula existente para esta nueva inscripción.</p>';
                $content_html .= '<h2>Detalles de tu Nueva Inscripción</h2>';
                $content_html .= '<p class="last">Puedes ver los detalles a continuación. ¡Seguimos avanzando juntos en tu formación!</p>';
            }

            $summary_table_title = 'Resumen de tu Matriculación';
            $summary_data = [
                'Estudiante' => $nombre,
                'Cédula' => $cedula,
                'Curso Matriculado' => $curso_aprobado['nombre_curso'],
                'Horario' => $curso_aprobado['horario'],
                'Número de Matrícula' => $matricula,
            ];

            $body = self::_get_email_template($email_title, $content_html, $summary_table_title, $summary_data);
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            wp_mail($email, $subject, $body, $headers);
        }

        $log_content = "{$nombre} (Cédula: {$cedula}) fue matriculado en '{$curso_aprobado['nombre_curso']}' con la matrícula {$matricula}.";
        if ($is_automatic_payment) {
            $log_content .= " Aprobación automática por pago online o webhook.";
            if (!empty($payment_details['amount']) && !empty($payment_details['currency'])) {
                $log_content .= " Monto: " . $payment_details['amount'] . " " . $payment_details['currency'] . ".";
            }
            if (!empty($payment_details['transaction_id'])) {
                $log_content .= " Transaction ID: " . $payment_details['transaction_id'] . ".";
            }
        }
        self::_log_activity('Estudiante Matriculado', $log_content, $is_automatic_payment ? 0 : null);

        return array(
            'post_id' => $post_id,
            'row_index' => $row_index,
            'matricula' => $matricula,
            'nombre' => $nombre,
            'cedula' => $cedula,
            'email' => $email,
            'telefono' => get_field('telefono', $post_id),
            'nombre_curso' => $curso_aprobado ? $curso_aprobado['nombre_curso'] : 'N/A'
        );
    }

    /**
     * Obtiene una lista de estudiantes filtrada por término de búsqueda, curso y estado.
     * @param string $search_term Término de búsqueda (nombre, cédula, matrícula).
     * @param string $course_filter Nombre del curso para filtrar.
     * @param string $status_filter Estado del curso ('Matriculado', 'Inscrito', etc. o vacío para todos).
     * @return array Lista de estudiantes que coinciden.
     */
    public static function _get_filtered_students($search_term = '', $course_filter = '', $status_filter = 'Matriculado') {
        $filtered_students = [];
        $estudiantes = get_posts(array('post_type' => 'estudiante', 'posts_per_page' => -1));
        if ($estudiantes && function_exists('get_field')) {
            foreach ($estudiantes as $estudiante) {
                $cursos = get_field('cursos_inscritos', $estudiante->ID);
                if ($cursos) {
                    foreach ($cursos as $curso) {
                        $is_status_match = empty($status_filter) || (isset($curso['estado']) && $curso['estado'] == $status_filter);

                        if ($is_status_match) {
                            $cedula = get_field('cedula', $estudiante->ID);
                            $email = get_field('email', $estudiante->ID);
                            $telefono = get_field('telefono', $estudiante->ID);
                            $matricula = isset($curso['matricula']) ? $curso['matricula'] : '';
                            $matches_course = empty($course_filter) || $curso['nombre_curso'] === $course_filter;
                            $searchable_string = implode(' ', [$matricula, $estudiante->post_title, $cedula, $email, $telefono, $curso['nombre_curso']]);
                            $matches_search = empty($search_term) || stripos($searchable_string, $search_term) !== false;
                            if ($matches_course && $matches_search) {
                                $filtered_students[] = ['estudiante' => $estudiante, 'curso' => $curso];
                            }
                        }
                    }
                }
            }
        }
        return $filtered_students;
    }

    /**
     * Envía un correo con el recibo de pago en PDF adjunto.
     */
    public static function _send_payment_receipt_email($recipient_email, $pdf_data, $subject, $filename) {
        if (!is_email($recipient_email)) {
            self::_log_activity('Error de Recibo', 'Destinatario de recibo no válido: ' . esc_html($recipient_email));
            return false;
        }

        $email_title = 'Confirmación de Pago';
        $content_html = '<p>¡Gracias por tu pago!</p>';
        $content_html .= '<p class="last">Hemos procesado tu pago exitosamente. Adjunto a este correo encontrarás tu recibo de pago en formato PDF como comprobante.</p>';
        $body = self::_get_email_template($email_title, $content_html);

        $upload_dir = wp_upload_dir();
        $temp_file_path = trailingslashit($upload_dir['basedir']) . $filename;
        file_put_contents($temp_file_path, $pdf_data);

        $headers = array('Content-Type: text/html; charset=UTF-8');
        $attachments = array($temp_file_path);

        $sent = wp_mail($recipient_email, $subject, $body, $headers, $attachments);
        unlink($temp_file_path);

        $log_title = $sent ? 'Recibo Enviado' : 'Error al Enviar Recibo';
        self::_log_activity($log_title, "El recibo '{$subject}' fue procesado para {$recipient_email}.");
        return $sent;
    }

    /**
     * Calcula el número de cupos ocupados para un curso y horario específico.
     * @return int Número de cupos ocupados.
     */
    public static function _get_cupos_ocupados($course_name, $horario) {
        $count = 0;
        $estudiantes = get_posts(array(
            'post_type' => 'estudiante',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        if ($estudiantes && function_exists('get_field')) {
            foreach ($estudiantes as $estudiante_id) {
                $cursos_inscritos = get_field('cursos_inscritos', $estudiante_id);
                if ($cursos_inscritos) {
                    foreach ($cursos_inscritos as $curso) {
                        if ($curso['nombre_curso'] === $course_name && $curso['horario'] === $horario && ($curso['estado'] === 'Matriculado' || $curso['estado'] === 'Inscrito')) {
                            $count++;
                        }
                    }
                }
            }
        }
        return $count;
    }

    /**
     * Envía un correo de notificación cuando un curso está lleno.
     */
    public static function _send_course_full_notification_email($student_name, $student_email, $course_name, $horario) {
        if (empty($student_email) || !is_email($student_email)) {
            return;
        }
        $subject = 'Cupo Agotado para el Curso Solicitado';
        $email_title = 'Curso Lleno';
        $content_html = '<p>Hola ' . esc_html($student_name) . ',</p>';
        $content_html .= '<p>Te informamos que el horario que seleccionaste para el curso <strong>' . esc_html($course_name) . '</strong> ya ha alcanzado su capacidad máxima y no quedan cupos disponibles.</p>';
        $content_html .= '<p class="last">Lamentamos los inconvenientes. Por favor, ponte en contacto con nuestra administración para consultar sobre futuras fechas o posibles alternativas.</p>';

        $body = self::_get_email_template($email_title, $content_html);
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($student_email, $subject, $body, $headers);
    }

    /**
     * Genera el HTML para la vista de perfil de un estudiante.
     * @param WP_Post $student_post El objeto post del estudiante.
     * @return string HTML para el perfil.
     */
    public static function _get_student_profile_html($student_post) {
        if (!function_exists('get_field')) {
            return '<div class="sga-profile-error">El plugin Advanced Custom Fields (ACF) es necesario.</div>';
        }

        $student_id = $student_post->ID;
        $nombre_completo = $student_post->post_title;
        $cedula = get_field('cedula', $student_id);
        $email = get_field('email', $student_id);
        $telefono = get_field('telefono', $student_id);
        $direccion = get_field('direccion', $student_id);
        $cursos = get_field('cursos_inscritos', $student_id);

        // Determinar si el usuario actual tiene permisos para imprimir
        // FIX: Se cambió para que sea visible para todos los que accedan a esta vista.
        $can_print = true; //<- Permite que todos vean el botón

        $print_url = add_query_arg([
            'action' => 'sga_print_student_profile',
            'student_id' => $student_id,
            '_wpnonce' => wp_create_nonce('sga_print_profile_' . $student_id)
        ], admin_url('admin-ajax.php'));

        ob_start();
        ?>
        <a href="#" id="sga-profile-back-btn" class="back-link panel-nav-link">&larr; Volver a Lista de Estudiantes</a>
        <h1 class="panel-title">Perfil de Estudiante: <?php echo esc_html($nombre_completo); ?></h1>
        <div class="sga-profile-grid">
            <div class="sga-profile-card">
                <h3>Información Personal</h3>
                <div class="sga-profile-form-group">
                    <label for="sga-profile-nombre_completo">Nombre Completo</label>
                    <input type="text" id="sga-profile-nombre_completo" value="<?php echo esc_attr($nombre_completo); ?>">
                </div>
                <div class="sga-profile-form-group">
                    <label for="sga-profile-cedula">Cédula / Identificación</label>
                    <input type="text" id="sga-profile-cedula" value="<?php echo esc_attr($cedula); ?>">
                </div>
                <div class="sga-profile-form-group">
                    <label for="sga-profile-email">Correo Electrónico</label>
                    <input type="email" id="sga-profile-email" value="<?php echo esc_attr($email); ?>">
                </div>
                <div class="sga-profile-form-group">
                    <label for="sga-profile-telefono">Teléfono</label>
                    <input type="tel" id="sga-profile-telefono" value="<?php echo esc_attr($telefono); ?>">
                </div>
                <div class="sga-profile-form-group">
                    <label for="sga-profile-direccion">Dirección</label>
                    <input type="text" id="sga-profile-direccion" value="<?php echo esc_attr($direccion); ?>">
                </div>
            </div>
            <div class="sga-profile-card">
                <h3>Historial Académico</h3>
                <div class="tabla-wrapper">
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr><th>Curso</th><th>Matrícula</th><th>Horario</th><th>Estado</th></tr>
                        </thead>
                        <tbody id="sga-profile-cursos-tbody">
                            <?php if ($cursos): ?>
                                <?php foreach ($cursos as $index => $curso): ?>
                                    <tr data-row-index="<?php echo $index; ?>">
                                        <td><?php echo esc_html($curso['nombre_curso']); ?></td>
                                        <td><?php echo esc_html($curso['matricula'] ?? 'N/A'); ?></td>
                                        <td><?php echo esc_html($curso['horario']); ?></td>
                                        <td>
                                            <select class="sga-profile-curso-estado">
                                                <option value="Inscrito" <?php selected($curso['estado'], 'Inscrito'); ?>>Inscrito</option>
                                                <option value="Matriculado" <?php selected($curso['estado'], 'Matriculado'); ?>>Matriculado</option>
                                                <option value="Completado" <?php selected($curso['estado'], 'Completado'); ?>>Completado</option>
                                                <option value="Cancelado" <?php selected($curso['estado'], 'Cancelado'); ?>>Cancelado</option>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4">No hay cursos inscritos.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="sga-profile-actions">
            <?php if ($can_print) { ?>
            <!-- BOTÓN PARA IMPRIMIR EL EXPEDIENTE -->
            <a href="<?php echo esc_url($print_url); ?>" class="button button-secondary" target="_blank" style="margin-right: auto;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
                Imprimir Expediente
            </a>
            <?php } ?>
            <button id="sga-profile-save-btn" class="button button-primary" data-student-id="<?php echo $student_id; ?>">Guardar Cambios</button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Envía un correo con un reporte en PDF adjunto.
     */
    public static function _send_report_email($pdf_data, $subject, $filename) {
        $options = get_option('sga_report_options');
        $recipient = !empty($options['recipient_email']) ? $options['recipient_email'] : get_option('admin_email');
        if (!is_email($recipient)) {
            self::_log_activity('Error de Reporte', 'Destinatario no válido: ' . esc_html($recipient));
            return false;
        }

        $email_title = 'Reporte del Sistema';
        $content_html = '<p>Saludos,</p>';
        $content_html .= '<p class="last">Adjunto encontrarás el reporte solicitado: <strong>' . esc_html($subject) . '</strong>. Este correo ha sido generado automáticamente por el Sistema de Gestión Académica.</p>';
        $body = self::_get_email_template($email_title, $content_html);

        $upload_dir = wp_upload_dir();
        $temp_file_path = trailingslashit($upload_dir['basedir']) . $filename;
        file_put_contents($temp_file_path, $pdf_data);

        $headers = array('Content-Type: text/html; charset=UTF-8');
        $attachments = array($temp_file_path);

        $sent = wp_mail($recipient, $subject, $body, $headers, $attachments);
        unlink($temp_file_path);

        $log_title = $sent ? 'Reporte Enviado' : 'Error de Reporte';
        self::_log_activity($log_title, "El reporte '{$subject}' fue procesado para {$recipient}.");
        return $sent;
    }

    /**
     * Reemplaza etiquetas dinámicas en una cadena de texto con datos del estudiante.
     * @param string $content El contenido con las etiquetas.
     * @param int $student_id El ID del estudiante.
     * @param string $context_group El grupo de destinatarios (ej. 'por_curso').
     * @param string $context_course_name El nombre del curso si el contexto lo requiere.
     * @return string El contenido con las etiquetas reemplazadas.
     */
    public static function _replace_dynamic_tags($content, $student_id, $context_group = '', $context_course_name = '') {
        $student_post = get_post($student_id);
        if (!$student_post) return $content;

        $cedula = get_field('cedula', $student_id);

        $replacements = [
            '[nombre_estudiante]' => $student_post->post_title,
            '[cedula]' => $cedula ? $cedula : '',
        ];

        // Etiquetas que dependen del contexto del curso
        $matricula = 'N/A';
        $nombre_curso = 'N/A';

        if ($context_group === 'por_curso' && !empty($context_course_name)) {
            $cursos_inscritos = get_field('cursos_inscritos', $student_id);
            if ($cursos_inscritos) {
                foreach ($cursos_inscritos as $curso) {
                    if ($curso['nombre_curso'] === $context_course_name) {
                        $matricula = !empty($curso['matricula']) ? $curso['matricula'] : 'Pendiente';
                        $nombre_curso = $curso['nombre_curso'];
                        break;
                    }
                }
            }
        }

        $replacements['[nombre_curso]'] = $nombre_curso;
        $replacements['[matricula]'] = $matricula;

        foreach ($replacements as $tag => $value) {
            $content = str_replace($tag, $value, $content);
        }

        return $content;
    }

    /**
     * Obtiene el número de inscripciones pendientes.
     * @return int Cantidad de inscripciones pendientes.
     */
    public static function _get_pending_inscriptions_count() {
        $count = 0;
        $estudiantes_ids = get_posts([
            'post_type' => 'estudiante',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        if ($estudiantes_ids && function_exists('get_field')) {
            foreach ($estudiantes_ids as $estudiante_id) {
                $cursos = get_field('cursos_inscritos', $estudiante_id);
                if ($cursos) {
                    foreach ($cursos as $curso) {
                        if (isset($curso['estado']) && $curso['estado'] === 'Inscrito') {
                            $count++;
                        }
                    }
                }
            }
        }
        return $count;
    }

    /**
     * Obtiene el número de inscripciones pendientes de llamar.
     * @return int Cantidad de inscripciones pendientes de llamar.
     */
    public static function _get_pending_calls_count() {
        $count = 0;
        $estudiantes_ids = get_posts([
            'post_type' => 'estudiante',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        if ($estudiantes_ids && function_exists('get_field')) {
            foreach ($estudiantes_ids as $estudiante_id) {
                $cursos = get_field('cursos_inscritos', $estudiante_id);
                $call_statuses = get_post_meta($estudiante_id, '_sga_call_statuses', true);
                if (!is_array($call_statuses)) {
                    $call_statuses = [];
                }

                if ($cursos) {
                    foreach ($cursos as $index => $curso) {
                        if (isset($curso['estado']) && $curso['estado'] === 'Inscrito') {
                            $current_call_status = $call_statuses[$index] ?? 'pendiente';
                            if ($current_call_status === 'pendiente') {
                                $count++;
                            }
                        }
                    }
                }
            }
        }
        return $count;
    }

     /**
     * Genera la plantilla HTML base para los correos electrónicos.
     * @param string $title Título principal del correo.
     * @param string $content_html Contenido principal en HTML.
     * @param string|null $summary_table_title Título para la tabla resumen (opcional).
     * @param array|null $summary_data Datos para la tabla resumen (array asociativo 'Clave' => 'Valor') (opcional).
     * @param array|null $button_data Datos para el botón de acción ['url' => '...', 'text' => '...'] (opcional).
     * @return string HTML completo del correo.
     */
    public static function _get_email_template($title, $content_html, $summary_table_title = null, $summary_data = null, $button_data = null) {
        $logo_id = 5754; // ID de tu logo en la mediateca
        $logo_url = wp_get_attachment_url($logo_id);
        $site_name = get_bloginfo('name');
        $site_url = home_url('/');

        ob_start();
        ?>
        <!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>body{margin:0;padding:0;background-color:#f4f4f7;font-family:Arial,sans-serif;font-size:16px;line-height:1.6;color:#333}.container{max-width:600px;margin:20px auto;background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 0 15px rgba(0,0,0,0.05)}.header{background-color:#141f53;padding:30px 20px;text-align:center}.header img{max-width:180px;height:auto}.content{padding:30px 40px}.content h1{color:#141f53;font-size:24px;margin:0 0 20px}.content p{margin:0 0 15px}.content p.last{margin-bottom:0}.summary-table{width:100%;margin:25px 0;border-collapse:collapse}.summary-table th,.summary-table td{border:1px solid #e0e0e0;padding:10px 12px;text-align:left}.summary-table th{background-color:#f8f9fa;font-weight:600;width:35%}.button-container{text-align:center;margin:30px 0}.button{display:inline-block;background-color:#4f46e5;color:#ffffff;padding:12px 25px;text-decoration:none;border-radius:5px;font-weight:600;font-size:16px}.footer{background-color:#f4f4f7;padding:20px 40px;text-align:center;font-size:12px;color:#777}.footer a{color:#4f46e5;text-decoration:none}</style>
        </head><body>
            <div class="container">
                <div class="header"><?php if ($logo_url): ?><img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>"><?php endif; ?></div>
                <div class="content">
                    <h1><?php echo esc_html($title); ?></h1>
                    <?php echo wp_kses_post($content_html); ?>
                    <?php if ($summary_table_title && $summary_data): ?>
                        <h2 style="font-size: 20px; color: #141f53; margin-top: 30px; margin-bottom: 15px;"><?php echo esc_html($summary_table_title); ?></h2>
                        <table class="summary-table">
                            <?php foreach ($summary_data as $key => $value): ?>
                                <tr><th><?php echo esc_html($key); ?>:</th><td><?php echo esc_html($value); ?></td></tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                    <?php if ($button_data): ?>
                        <div class="button-container"><a href="<?php echo esc_url($button_data['url']); ?>" class="button"><?php echo esc_html($button_data['text']); ?></a></div>
                    <?php endif; ?>
                </div>
                <div class="footer">
                    &copy; <?php echo date('Y'); ?> <?php echo esc_html($site_name); ?>. Todos los derechos reservados.<br>
                    <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_url); ?></a>
                </div>
            </div>
        </body></html>
        <?php
        return ob_get_clean();
    }
}
