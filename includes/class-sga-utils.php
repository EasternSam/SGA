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
        
        // La rotación siempre se basa en el índice del array $valid_agent_ids
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

        // La URL ya no es para descarga directa de PDF, sino para abrir el modal de impresión.
        $print_nonce = wp_create_nonce('sga_render_print_profile_' . $student_id);
        $print_url = "#"; // Se maneja con JS

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
            <!-- BOTÓN PARA IMPRIMIR EL EXPEDIENTE (Abre diálogo de impresión vía JS) -->
            <button id="sga-print-expediente-btn" class="button button-secondary" style="margin-right: auto;" 
                data-student-id="<?php echo $student_id; ?>"
                data-nonce="<?php echo esc_attr($print_nonce); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
                Imprimir Expediente
            </button>
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
     * Busca y retorna el ID del último CPT 'sga_llamada' para una inscripción específica.
     * @param int $student_id ID del post del estudiante.
     * @param int $row_index Índice de la fila del curso en el repeater.
     * @return int|null ID del post sga_llamada si se encuentra, o null.
     */
    public static function _get_last_call_log_post_id($student_id, $row_index) {
        $args = array(
            'post_type'      => 'sga_llamada',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array('key' => '_student_id', 'value' => $student_id, 'compare' => '='),
                array('key' => '_row_index', 'value' => $row_index, 'compare' => '='),
            ),
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        );
        $posts = get_posts($args);
        return !empty($posts) ? $posts[0] : null;
    }

    /**
     * Actualiza el comentario en el CPT 'sga_llamada' y el meta de la inscripción.
     * @param int $call_log_post_id ID del post sga_llamada.
     * @param int $student_id ID del post del estudiante.
     * @param int $row_index Índice de la fila del curso en el repeater.
     * @param string $new_comment Nuevo comentario.
     * @param string $user_name Nombre del usuario que edita.
     * @return bool
     */
    public static function _update_call_log_comment($call_log_post_id, $student_id, $row_index, $new_comment, $user_name) {
        if (!get_post($call_log_post_id)) {
            self::_log_activity('Error de Edición de Comentario', "No se encontró el post de registro de llamada ID: {$call_log_post_id}.", null);
            return false;
        }

        // 1. Actualizar el CPT sga_llamada (post_content)
        $updated = wp_update_post(array(
            'ID'           => $call_log_post_id,
            'post_content' => sanitize_textarea_field($new_comment),
            'post_modified' => current_time('mysql'), // Asegurar que la fecha de CPT se actualice
        ));

        // 2. Actualizar el meta de la inscripción (_sga_call_log)
        $call_log = get_post_meta($student_id, '_sga_call_log', true);
        if (!is_array($call_log)) {
            $call_log = [];
        }

        if (isset($call_log[$row_index])) {
            $call_log[$row_index]['comment'] = sanitize_textarea_field($new_comment);
            // Opcional: registrar quién y cuándo editó.
            $call_log[$row_index]['last_edited_by'] = $user_name;
            $call_log[$row_index]['last_edited_timestamp'] = time();

            update_post_meta($student_id, '_sga_call_log', $call_log);
            self::_log_activity('Comentario de Llamada Editado', "El comentario de la inscripción ID: {$student_id} (fila {$row_index}) fue editado por {$user_name}.", null);
            return true;
        }

        self::_log_activity('Error de Edición de Comentario', "No se encontró el índice de la inscripción para el estudiante ID: {$student_id}.", null);
        return false;
    }

    /**
     * Genera el HTML para mostrar la información del registro de llamada en la tabla.
     * @param int $student_id ID del post del estudiante.
     * @param int $row_index Índice de la fila del curso en el repeater.
     * @param array $call_info Datos del registro de llamada (del meta _sga_call_log).
     * @param int $call_log_post_id ID del CPT sga_llamada.
     * @param bool $can_edit Determina si se deben incluir los botones de edición/añadir.
     * @return string HTML generado.
     */
    public static function _get_call_log_html($student_id, $row_index, $call_info, $call_log_post_id, $can_edit = true) {
        $html = 'Llamado por <strong>' . esc_html($call_info['user_name']) . '</strong><br><small>' . esc_html(date_i18n('d/m/Y H:i', $call_info['timestamp'])) . '</small>';
        
        $comment = $call_info['comment'] ?? '';
        
        if ($can_edit) {
            // El nonce debe ser dinámico para el contexto de edición, no de marcado
            $edit_nonce = wp_create_nonce('sga_edit_llamado_' . $student_id . '_' . $row_index);
            $edit_btn_text = empty($comment) ? '(Añadir Comentario)' : '(Editar)';

            if (!empty($comment)) {
                $html .= '<p class="sga-call-comment"><em>' . esc_html($comment) . '</em>';
            } else {
                $html .= '<p class="sga-call-comment-placeholder" style="margin: 5px 0 0 0; padding-left: 5px; border-left: 2px solid var(--sga-gray); font-size: 12px; color: var(--sga-text-light);"><em>Sin comentario.</em>';
            }
            
            $html .= '<button class="button-link sga-edit-llamado-btn" ';
            $html .= 'data-postid="' . $student_id . '" ';
            $html .= 'data-rowindex="' . $row_index . '" ';
            $html .= 'data-log-id="' . $call_log_post_id . '" '; 
            $html .= 'data-comment="' . esc_attr($comment) . '" ';
            $html .= 'data-nonce="' . $edit_nonce . '" ';
            $html .= 'style="margin-left: 5px; color: var(--sga-secondary); font-size: 12px; border: none; background: none; padding: 0; cursor: pointer; text-decoration: underline;">' . $edit_btn_text . '</button></p>';
        } else {
            if (!empty($comment)) {
                 $html .= '<p class="sga-call-comment"><em>' . esc_html($comment) . '</em></p>';
            }
        }
        
        return $html;
    }

    /**
     * Renderiza el expediente del estudiante en formato HTML puro para la impresión.
     * Este es el contenido que se debe usar en la ventana de impresión.
     * @param int $student_id ID del post del estudiante.
     * @return string|false HTML del expediente o false si el estudiante no existe.
     */
    public static function _get_student_profile_print_html($student_id) {
        $student_post = get_post($student_id);
        if (!$student_post || 'estudiante' !== $student_post->post_type) return false;

        $nombre_completo = $student_post->post_title;
        $cedula = get_field('cedula', $student_id);
        $email = get_field('email', $student_id);
        $telefono = get_field('telefono', $student_id);
        $direccion = get_field('direccion', $student_id);
        $cursos = get_field('cursos_inscritos', $student_id);

        $logo_id = 5024; // ID de ejemplo para el logo
        $logo_src = '';
        // Intenta obtener la URL del logo
        if ($logo_id && $logo_url = wp_get_attachment_url($logo_id)) {
            $logo_src = $logo_url;
        }
        
        $report_title = 'Expediente Estudiantil: ' . $nombre_completo;

        ob_start();
        ?>
        <!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
        <title><?php echo esc_html($report_title); ?></title>
        <style>
            @media print {
                /* Estilos específicos para impresión */
                body {
                    font-family: Arial, sans-serif;
                    font-size: 11pt;
                    color: #333;
                    margin: 0;
                    padding: 0;
                    -webkit-print-color-adjust: exact; /* Para imprimir colores de fondo */
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 3px solid #141f53;
                    padding-bottom: 15px;
                }
                .header img {
                    max-height: 60px;
                    margin-bottom: 10px;
                }
                h1 {
                    font-size: 20pt;
                    color: #141f53;
                    margin: 0;
                }
                .subtitle {
                    font-size: 9pt;
                    color: #555;
                    margin-top: 5px;
                }
                .section-title {
                    font-size: 14pt;
                    color: #4f46e5;
                    border-bottom: 2px solid #e0e0e0;
                    padding-bottom: 5px;
                    margin-top: 25px;
                    margin-bottom: 15px;
                }
                .data-grid {
                    display: table;
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 15px;
                }
                .data-row {
                    display: table-row;
                }
                .data-label, .data-value {
                    display: table-cell;
                    padding: 5px 0;
                    vertical-align: top;
                    font-size: 10pt;
                }
                .data-label {
                    font-weight: 700;
                    width: 25%;
                }
                .data-value {
                    width: 75%;
                }
                .curso-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                    font-size: 9pt;
                }
                .curso-table th, .curso-table td {
                    border: 1px solid #ccc;
                    padding: 8px;
                    text-align: left;
                }
                .curso-table thead th {
                    background-color: #141f53 !important;
                    color: #fff !important;
                    font-weight: 700;
                }
                .curso-table tbody tr:nth-child(even) {
                    background-color: #f8f9fa !important;
                }
                /* Ocultar elementos irrelevantes en la impresión si fuera necesario */
                .actions { display: none; }
            }
        </style>
        </head><body>
            <div class="print-container">
                <div class="header">
                    <?php if (!empty($logo_src)): ?><img src="<?php echo esc_url($logo_src); ?>" alt="Logo"><?php endif; ?>
                    <h1><?php echo esc_html($report_title); ?></h1>
                    <p class="subtitle">Generado el: <?php echo date_i18n('j \d\e F \d\e Y \a \l\a\s H:i'); ?></p>
                </div>
                
                <h2 class="section-title">Datos Personales y de Contacto</h2>
                <div class="data-grid">
                    <div class="data-row"><div class="data-label">Nombre Completo:</div><div class="data-value"><?php echo esc_html($nombre_completo); ?></div></div>
                    <div class="data-row"><div class="data-label">Cédula / ID:</div><div class="data-value"><?php echo esc_html($cedula); ?></div></div>
                    <div class="data-row"><div class="data-label">Correo Electrónico:</div><div class="data-value"><?php echo esc_html($email); ?></div></div>
                    <div class="data-row"><div class="data-label">Teléfono:</div><div class="data-value"><?php echo esc_html($telefono); ?></div></div>
                    <div class="data-row"><div class="data-label">Dirección:</div><div class="data-value"><?php echo esc_html($direccion); ?></div></div>
                </div>

                <h2 class="section-title">Historial Académico y Cursos</h2>
                <?php if ($cursos): ?>
                    <table class="curso-table">
                        <thead><tr><th>Curso</th><th>Horario</th><th>Fecha Inscripción</th><th>Matrícula</th><th>Estado</th></tr></thead>
                        <tbody>
                            <?php foreach ($cursos as $curso): 
                                $estado_display = esc_html($curso['estado']);
                            ?>
                                <tr>
                                    <td><?php echo esc_html($curso['nombre_curso']); ?></td>
                                    <td><?php echo esc_html($curso['horario']); ?></td>
                                    <td><?php echo esc_html($curso['fecha_inscripcion']); ?></td>
                                    <td><?php echo esc_html($curso['matricula'] ?? 'N/A'); ?></td>
                                    <td><?php echo $estado_display; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No hay cursos inscritos para este estudiante.</p>
                <?php endif; ?>
            </div>
        </body></html>
        <?php
        return ob_get_clean();
    }
}
