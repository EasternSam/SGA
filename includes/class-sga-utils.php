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
     * @param string $transient_key_suffix Un sufijo único para el transient de esta rotación.
     * @return int|null ID del agente o null si la lista está vacía.
     */
    public static function _get_next_agent_from_list($agent_ids, $transient_key_suffix = 'default') {
        if (empty($agent_ids)) {
            return null;
        }
        
        // Asegurar que solo tengamos IDs únicos y válidos
        $valid_agent_ids = array_filter(array_unique(array_map('intval', $agent_ids)));
        if (empty($valid_agent_ids)) {
            return null;
        }
        
        // Usar un transient único para esta acción de reparto manual
        $transient_key = 'sga_last_distributed_agent_index_' . $transient_key_suffix;
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
     * Envía el correo inicial al estudiante tras la inscripción.
     * * [NUEVA LÓGICA v1.1]
     * - Si el curso es de categoría "Cursos-Infotep", envía un correo con el PDF adjunto (ID 12195).
     * - Si es un curso normal, envía un correo con las instrucciones de pago manual (bancos).
     */
    public static function _send_pending_payment_email($student_name, $student_email, $student_cedula, $course_name, $horario) {
        if (empty($student_email) || !is_email($student_email)) {
            self::_log_activity('Error de Correo', "Intento de envío de correo de inscripción a dirección inválida: " . esc_html($student_email));
            return;
        }
        
        // Variables para el correo
        $subject = '';
        $email_title = '';
        $content_html = '';
        $summary_table_title = 'Resumen de tu Solicitud';
        $summary_data = [
            'Estudiante' => $student_name,
            'Cédula' => $student_cedula,
            'Curso Solicitado' => $course_name,
            'Horario' => $horario,
        ];
        $attachments = [];
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $log_message = '';
        
        // 1. Obtener el post del curso para verificar su categoría
        $curso_post = null;
        $is_infotep_course = false;
        $curso_post_query = get_posts(array('post_type' => 'curso', 'title' => $course_name, 'posts_per_page' => 1, 'post_status' => 'publish'));
        
        if ($curso_post_query) {
            $curso_post = $curso_post_query[0];
            
            // --- CORRECCIÓN ---
            // Verificar por 'slug' (cursos-infotep) en lugar de 'name' (Cursos-Infotep)
            // has_term() por defecto busca por 'name'. Al pasar el slug (que es lo correcto),
            // la comprobación funcionará como se espera.
            if (has_term('cursos-infotep', 'category', $curso_post->ID)) {
                $is_infotep_course = true;
            }
            // --- FIN CORRECCIÓN ---
        }

        if ($is_infotep_course) {
            // --- CASO 1: CURSO DE INFOTEP ---
            // Enviar correo con el formulario PDF adjunto.
            
            $subject = 'Importante: Formulario de Inscripción INFOTEP';
            $email_title = 'Formulario INFOTEP Requerido';
            
            $content_html = '<p>Hola ' . esc_html($student_name) . ',</p>';
            $content_html .= '<p>Gracias por tu solicitud de inscripción para el curso <strong>' . esc_html($course_name) . '</strong>. Hemos recibido tus datos correctamente.</p>';
            $content_html .= '<h2>Paso Obligatorio: Formulario INFOTEP</h2>';
            $content_html .= '<p>Para completar tu inscripción en este curso avalado por INFOTEP, es <strong>obligatorio</strong> descargar, llenar y presentar el formulario que hemos adjuntado a este correo.</p>';
            $content_html .= '<p class="last">Por favor, imprime este documento, complétalo con tu información y tráelo físicamente a nuestras instalaciones en CENTU para finalizar tu proceso.</p>';

            // Obtener la ruta del archivo adjunto desde la Biblioteca de Medios
            $attachment_id = 12273; // ID del PDF Formulario de INFOTEP
            $attachment_path = get_attached_file($attachment_id);
            
            if ($attachment_path && file_exists($attachment_path)) {
                $attachments[] = $attachment_path;
                $log_message = "Correo de INFOTEP (con adjunto ID: {$attachment_id}) enviado a {$student_name} ({$student_email}) para el curso '{$course_name}'.";
            } else {
                $content_html .= '<p style="color: red; font-weight: bold; margin-top: 15px;">Error: No se pudo adjuntar el formulario. Por favor, contacta a la administración.</p>';
                $log_message = "Error: Correo de INFOTEP para {$student_name} ({$student_email}). NO SE PUDO ADJUNTAR el archivo ID: {$attachment_id}.";
                self::_log_activity('Error Adjunto Correo', $log_message, 0);
            }

        } else {
            // --- CASO 2: CURSO NORMAL ---
            // Enviar correo con instrucciones de pago manual (bancos).
            
            $subject = 'Instrucciones para Completar tu Inscripción';
            $email_title = 'Solicitud de Inscripción Recibida';

            $content_html = '<p>Hola ' . esc_html($student_name) . ',</p>';
            $content_html .= '<p>Gracias por inscribirte en CENTU. Hemos recibido tu solicitud y tu cupo está reservado temporalmente. A continuación, encontrarás los detalles de tu solicitud.</p>';
            $content_html .= '<h2>Siguiente Paso: Realizar el Pago</h2>';
            $content_html .= '<p>Para completar tu inscripción y asegurar tu cupo, por favor realiza el pago utilizando una de las siguientes opciones:</p>';

            // Opciones de pago proporcionadas
            $content_html .= '<h3 style="color: #141f53; margin-top: 20px; margin-bottom: 10px;">Opción 1: Transferencia Bancaria</h3>';
            $content_html .= '<p style="margin-bottom: 10px;">Puede hacer su pago por transferencia o depósito bancario. Las cuentas disponibles para realizar su pago son las siguientes:</p>';
            $content_html .= '<ul style="list-style-type: disc; margin-left: 25px; padding-left: 0; line-height: 1.6;">';
            $content_html .= '<li><strong>Banco Popular:</strong> 056088148</li>';
            $content_html .= '<li><strong>Banreservas:</strong> 0201059070</li>';
            $content_html .= '<li><strong>Banco BHD:</strong> 01887270011</li>';
            $content_html .= '<li><strong>Banco Santa Cruz:</strong> 11311020001576</li>';
            $content_html .= '</ul>';
            $content_html .= '<p style="margin-top: 10px;">Todas las cuentas son corrientes, a nombre de <strong>CENTU</strong>. Por favor coloque en comentarios su nombre y curso.</p>';
            $content_html .= '<p style="font-weight: bold; color: #141f53;">El comprobante lo deberá remitir al correo: <a href="mailto:mercadeocentu@gmail.com">mercadeocentu@gmail.com</a></p>';

            $content_html .= '<h3 style="color: #141f53; margin-top: 25px; margin-bottom: 10px;">Opción 2: Pago en Físico</h3>';
            $content_html .= '<p>Puedes visitar nuestras instalaciones y realizar el pago directamente en caja.</p>';
            $content_html .= '<p style="margin-top: 5px;"><strong>Dirección:</strong> Av. Doctor Delgado #103; Gazcue, Distrito Nacional, República Dominicana</p>';
            
            $content_html .= '<p class="last" style="margin-top: 25px;">Quedamos a su orden.</p>';
            
            $log_message = "Correo de Inscripción (con instrucciones de pago manual) enviado a {$student_name} ({$student_email}) para el curso '{$course_name}'.";
        }

        // 3. Ensamblar y enviar el correo
        $body = self::_get_email_template($email_title, $content_html, $summary_table_title, $summary_data);
        
        // Registrar la actividad
        self::_log_activity('Correo Enviado', $log_message, 0);

        // Enviar el correo
        $mail_sent = wp_mail($student_email, $subject, $body, $headers, $attachments);

        // --- INICIO DE LA DEPURACIÓN DE CORREO ---
        // Añadimos un log específico si wp_mail() falla.
        if (false === $mail_sent) {
            $error_message = "ERROR DE WP_MAIL: El correo para '{$student_email}' (Curso: '{$course_name}') no pudo ser enviado. Esto usualmente indica un problema con la configuración del servidor de correo.";
            self::_log_activity('Error Crítico de Correo', $error_message, 0);
        }
        // --- FIN DE LA DEPURACIÓN DE CORREO ---
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

        // *** INICIO OPTIMIZACIÓN ***
        // Al aprobar, el conteo de pendientes cambia. Borramos el transient.
        delete_transient('sga_pending_insc_count');
        delete_transient('sga_pending_calls_count');
        delete_transient('sga_pending_insc_counts_v2'); // Borrar el nuevo transient plural
        delete_transient('sga_pending_calls_counts_v2'); // Borrar el nuevo transient plural
        // *** FIN OPTIMIZACIÓN ***


        $email = get_field('email', $post_id);
        $cursos = get_field('cursos_inscritos', $post_id);
        $curso_aprobado = isset($cursos[$row_index]) ? $cursos[$row_index] : null;

        if ($curso_aprobado && !empty($email)) {
            $subject = "¡Has sido matriculado exitosamente!";
            $email_title = '¡Matriculación Exitosa!';
            $content_html = '<p>Hola ' . esc_html($nombre) . ',</p>';
            if ($es_primera_matricula) {
                $content_html .= '<p>Te informamos con gran alegría que tu inscripción ha sido procesada y has sido matriculado exitosamente. A continuación, encontrarás los detalles de tu matriculación.</p>';
                $content_html .= '<h2>PróximOS Pasos</h2>';
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
            $mail_sent_approval = wp_mail($email, $subject, $body, $headers);

            // --- INICIO DE LA DEPURACIÓN DE CORREO ---
            if (false === $mail_sent_approval) {
                $error_message = "ERROR DE WP_MAIL: El correo de APROBACIÓN para '{$email}' (Matrícula: {$matricula}) no pudo ser enviado.";
                self::_log_activity('Error Crítico de Correo', $error_message, 0);
            }
            // --- FIN DE LA DEPURACIÓN DE CORREO ---
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
        // *** INICIO OPTIMIZACIÓN ***
        // 1. Usar la nueva función rápida para obtener solo los IDs relevantes
        $relevant_student_ids = self::_get_student_ids_by_enrollment_status($status_filter);

        if (empty($relevant_student_ids)) {
            return []; // No hay estudiantes con ese estado, nos ahorramos la consulta
        }
        
        $query_args = array(
            'post_type' => 'estudiante',
            'posts_per_page' => -1,
            'post__in' => $relevant_student_ids // Buscamos SOLO en los IDs relevantes
        );
        
        $estudiantes = get_posts($query_args);
        $filtered_students = [];

        // 2. Pre-calentar caché de metadatos para los estudiantes encontrados
        update_postmeta_cache($relevant_student_ids);
        // *** FIN OPTIMIZACIÓN ***

        if ($estudiantes && function_exists('get_field')) {
            // 3. Este bucle ahora es mucho más rápido (lee desde caché)
            foreach ($estudiantes as $estudiante) {
                $cursos = get_field('cursos_inscritos', $estudiante->ID); // Rápido (desde caché)
                if ($cursos) {
                    foreach ($cursos as $curso) {
                        $is_status_match = empty($status_filter) || (isset($curso['estado']) && $curso['estado'] == $status_filter);
                        
                        if ($is_status_match) {
                            $cedula = get_field('cedula', $estudiante->ID); // Rápido (desde caché)
                            $email = get_field('email', $estudiante->ID); // Rápido (desde caché)
                            $telefono = get_field('telefono', $estudiante->ID); // Rápido (desde caché)
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

        // --- INICIO DE LA DEPURACIÓN DE CORREO ---
        if (false === $sent) {
            $error_message = "ERROR DE WP_MAIL: El correo de RECIBO DE PAGO para '{$recipient_email}' no pudo ser enviado.";
            self::_log_activity('Error Crítico de Correo', $error_message, 0);
        }
        // --- FIN DE LA DEPURACIÓN DE CORREO ---

        return $sent;
    }

    // *** INICIO OPTIMIZACIÓN ***
    /**
     * [NUEVA FUNCIÓN HELPER ESTÁTICA]
     * Pre-calcula los cupos ocupados para TODOS los cursos y horarios.
     * Se almacena en una variable estática para ejecutarse solo UNA VEZ por carga de página.
     * @return array Mapa de cupos: [course_name][horario] => count
     */
    private static $cupos_map_cache = null;
    private static function _get_all_cupos_map_cached() {
        // Si el caché estático ya está lleno, devolverlo inmediatamente.
        if (self::$cupos_map_cache !== null) {
            return self::$cupos_map_cache;
        }

        $cupos_map = [];
        
        // 1. Obtener todos los IDs de estudiantes relevantes (Inscrito o Matriculado)
        $inscrito_ids = self::_get_student_ids_by_enrollment_status('Inscrito');
        $matriculado_ids = self::_get_student_ids_by_enrollment_status('Matriculado');
        $estudiantes_ids = array_unique(array_merge($inscrito_ids, $matriculado_ids));

        if (empty($estudiantes_ids)) {
            self::$cupos_map_cache = $cupos_map; // Guardar caché (vacío)
            return self::$cupos_map_cache;
        }

        // 2. Pre-calentar el caché de metadatos para todos estos estudiantes
        update_postmeta_cache($estudiantes_ids);

        // 3. Iterar UNA VEZ sobre los estudiantes y construir el mapa
        if (function_exists('get_field')) {
            foreach ($estudiantes_ids as $estudiante_id) {
                $cursos_inscritos = get_field('cursos_inscritos', $estudiante_id); // Rápido (desde caché)
                if ($cursos_inscritos) {
                    foreach ($cursos_inscritos as $curso) {
                        if (isset($curso['estado']) && ($curso['estado'] === 'Matriculado' || $curso['estado'] === 'Inscrito')) {
                            $course_name = $curso['nombre_curso'];
                            $horario = $curso['horario'];

                            if (!isset($cupos_map[$course_name])) {
                                $cupos_map[$course_name] = [];
                            }
                            if (!isset($cupos_map[$course_name][$horario])) {
                                $cupos_map[$course_name][$horario] = 0;
                            }
                            
                            $cupos_map[$course_name][$horario]++;
                        }
                    }
                }
            }
        }
        
        self::$cupos_map_cache = $cupos_map; // Guardar caché (lleno)
        return self::$cupos_map_cache;
    }
    // *** FIN OPTIMIZACIÓN ***

    /**
     * Calcula el número de cupos ocupados para un curso y horario específico.
     * @return int Número de cupos ocupados.
     */
    public static function _get_cupos_ocupados($course_name, $horario) {
        // *** INICIO OPTIMIZACIÓN ***
        // Esta función ahora es un simple "lookup" en el mapa estático pre-calculado.
        // La primera vez que se llame, _get_all_cupos_map_cached() se ejecutará.
        // Todas las llamadas subsecuentes serán instantáneas.
        $cupos_map = self::_get_all_cupos_map_cached();
        
        // Buscar el curso y el horario en el mapa
        if (isset($cupos_map[$course_name]) && isset($cupos_map[$course_name][$horario])) {
            return $cupos_map[$course_name][$horario];
        }
        
        return 0; // Si no se encuentra, retornar 0
        // *** FIN OPTIMIZACIÓN ***
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
        $mail_sent_full = wp_mail($student_email, $subject, $body, $headers);

        // --- INICIO DE LA DEPURACIÓN DE CORREO ---
        if (false === $mail_sent_full) {
            $error_message = "ERROR DE WP_MAIL: El correo de CURSO LLENO para '{$student_email}' (Curso: '{$course_name}') no pudo ser enviado.";
            self::_log_activity('Error Crítico de Correo', $error_message, 0);
        }
        // --- FIN DE LA DEPURACIÓN DE CORREO ---
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

        // --- INICIO LÓGICA DE MENOR DE EDAD (PARA VISTA DE PANEL) ---
        $info_menor_html = '';
        if (strpos($cedula, '-') !== false) {
            // Es un menor, la cédula es del tutor
            list($cedula_tutor, $id_menor) = explode('-', $cedula, 2);
            
            // Buscar al tutor por su cédula
            $tutor_query = get_posts(array(
                'post_type' => 'estudiante',
                'meta_key' => 'cedula',
                'meta_value' => $cedula_tutor, // Busca la cédula exacta del tutor
                'posts_per_page' => 1
            ));
            
            $info_menor_html = '<span style="color: #ef4444; font-weight: bold; font-size: 12px; display: block; margin-top: 5px;">* Es menor de edad.</span>';

            if ($tutor_query) {
                $nombre_tutor = $tutor_query[0]->post_title;
                $info_menor_html .= '<span style="color: #64748b; font-weight: bold; font-size: 12px; display: block; margin-top: 5px;">* Tutor registrado: ' . esc_html($nombre_tutor) . ' (Cédula: ' . esc_html($cedula_tutor) . ')</span>';
            } else {
                 $info_menor_html .= '<span style="color: #64748b; font-weight: bold; font-size: 12px; display: block; margin-top: 5px;">* Cédula de tutor: ' . esc_html($cedula_tutor) . ' (No registrado como estudiante).</span>';
            }
        }
        // --- FIN LÓGICA DE MENOR DE EDAD ---

        ob_start();
        ?>
        <a href="#" id="sga-profile-back-btn" class="back-link">&larr; Volver a Lista de Estudiantes</a>
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
                    <?php echo $info_menor_html; // Mostrar información del tutor/menor ?>
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
     * [NUEVA FUNCIÓN] Obtiene conteos de inscripciones pendientes divididos por categoría.
     * @return array ['total' => int, 'general' => int, 'infotep' => int]
     */
    public static function _get_pending_inscriptions_counts() {
        $transient_key = 'sga_pending_insc_counts_v2';
        $cached_counts = get_transient($transient_key);

        if (false !== $cached_counts && is_array($cached_counts)) {
            return $cached_counts;
        }

        $counts = [
            'total' => 0,
            'general' => 0,
            'infotep' => 0,
        ];
        
        $estudiantes_ids = self::_get_student_ids_by_enrollment_status('Inscrito');
        
        if (empty($estudiantes_ids)) {
            set_transient($transient_key, $counts, 5 * MINUTE_IN_SECONDS);
            return $counts;
        }

        update_postmeta_cache($estudiantes_ids);

        // Pre-cargar todos los cursos y sus categorías
        $course_category_map = [];
        $infotep_slug = 'cursos-infotep';
        $all_cursos = get_posts(array('post_type' => 'curso', 'posts_per_page' => -1, 'post_status' => array('publish', 'private'), 'fields' => 'all_with_meta'));
        
        if ($all_cursos) {
            $course_ids_to_check = wp_list_pluck($all_cursos, 'ID');
            $terms_by_course_id = [];
            if (!empty($course_ids_to_check)) {
                $all_terms = wp_get_object_terms($course_ids_to_check, 'category', ['fields' => 'all_with_object_id']);
                foreach ($all_terms as $term) {
                    if (!isset($terms_by_course_id[$term->object_id])) $terms_by_course_id[$term->object_id] = [];
                    $terms_by_course_id[$term->object_id][] = $term->slug;
                }
            }
            foreach ($all_cursos as $course_post) {
                $categories = $terms_by_course_id[$course_post->ID] ?? [];
                $course_category_map[$course_post->post_title] = in_array($infotep_slug, $categories) ? 'infotep' : 'general';
            }
        }
        
        if (function_exists('get_field')) {
            foreach ($estudiantes_ids as $estudiante_id) {
                $cursos = get_field('cursos_inscritos', $estudiante_id);
                if ($cursos) {
                    foreach ($cursos as $curso) {
                        if (isset($curso['estado']) && $curso['estado'] === 'Inscrito') {
                            $course_name = $curso['nombre_curso'];
                            $type = $course_category_map[$course_name] ?? 'general';
                            
                            $counts['total']++;
                            $counts[$type]++;
                        }
                    }
                }
            }
        }
        
        set_transient($transient_key, $counts, 5 * MINUTE_IN_SECONDS);
        return $counts;
    }

    /**
     * Obtiene el número de inscripciones pendientes (Total).
     * @return int Cantidad de inscripciones pendientes.
     */
    public static function _get_pending_inscriptions_count() {
        // Esta función ahora llama a la nueva función plural y devuelve solo el total
        $counts = self::_get_pending_inscriptions_counts();
        return $counts['total'];
    }

    /**
     * [NUEVA FUNCIÓN] Obtiene conteos de llamadas pendientes divididos por categoría.
     * @return array ['total' => int, 'general' => int, 'infotep' => int]
     */
    public static function _get_pending_calls_counts() {
        $transient_key = 'sga_pending_calls_counts_v2';
        $cached_counts = get_transient($transient_key);

        if (false !== $cached_counts && is_array($cached_counts)) {
            return $cached_counts;
        }

        $counts = [
            'total' => 0,
            'general' => 0,
            'infotep' => 0,
        ];

        $estudiantes_ids = self::_get_student_ids_by_enrollment_status('Inscrito');

        if (empty($estudiantes_ids)) {
            set_transient($transient_key, $counts, 5 * MINUTE_IN_SECONDS);
            return $counts;
        }
        
        update_postmeta_cache($estudiantes_ids);

        // Pre-cargar todos los cursos y sus categorías (lógica duplicada de la función anterior, se podría optimizar)
        $course_category_map = [];
        $infotep_slug = 'cursos-infotep';
        $all_cursos = get_posts(array('post_type' => 'curso', 'posts_per_page' => -1, 'post_status' => array('publish', 'private'), 'fields' => 'all_with_meta'));
        
        if ($all_cursos) {
            $course_ids_to_check = wp_list_pluck($all_cursos, 'ID');
            $terms_by_course_id = [];
            if (!empty($course_ids_to_check)) {
                $all_terms = wp_get_object_terms($course_ids_to_check, 'category', ['fields' => 'all_with_object_id']);
                foreach ($all_terms as $term) {
                    if (!isset($terms_by_course_id[$term->object_id])) $terms_by_course_id[$term->object_id] = [];
                    $terms_by_course_id[$term->object_id][] = $term->slug;
                }
            }
            foreach ($all_cursos as $course_post) {
                $categories = $terms_by_course_id[$course_post->ID] ?? [];
                $course_category_map[$course_post->post_title] = in_array($infotep_slug, $categories) ? 'infotep' : 'general';
            }
        }

        if (function_exists('get_field')) {
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
                                // Es una llamada pendiente, ahora determinar tipo
                                $course_name = $curso['nombre_curso'];
                                $type = $course_category_map[$course_name] ?? 'general';
                                
                                $counts['total']++;
                                $counts[$type]++;
                            }
                        }
                    }
                }
            }
        }
        
        set_transient($transient_key, $counts, 5 * MINUTE_IN_SECONDS);
        return $counts;
    }

    /**
     * Obtiene el número de inscripciones pendientes de llamar (Total).
     * @return int Cantidad de inscripciones pendientes de llamar.
     */
    public static function _get_pending_calls_count() {
        // Esta función ahora llama a la nueva función plural y devuelve solo el total
        $counts = self::_get_pending_calls_counts();
        return $counts['total'];
    }
    
    /**
     * [NUEVA FUNCIÓN] Obtiene IDs de estudiantes basado en el estado de una inscripción en el repeater.
     * Esta versión es más robusta: obtiene todos los estudiantes y los filtra en PHP
     * usando get_field() para asegurar compatibilidad.
     *
     * @param string $status El estado a buscar (ej. 'Inscrito', 'Matriculado').
     * @return array Lista de IDs de post de estudiantes.
     */
    public static function _get_student_ids_by_enrollment_status($status = 'Inscrito') {
        if (empty($status) || !function_exists('get_field')) {
            return [];
        }

        global $wpdb;
        
        // 1. Obtener todos los posts que SON estudiantes
        $student_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'estudiante' AND post_status = 'publish'");
        
        if (empty($student_ids)) {
            return [];
        }
        
        $relevant_student_ids = [];
        
        // 2. Pre-calentar caché de metadatos para todos los estudiantes
        update_postmeta_cache($student_ids);
        
        // 3. Filtrar en PHP
        foreach ($student_ids as $id) {
            $cursos = get_field('cursos_inscritos', $id); // Rápido (desde caché)
            if ($cursos) {
                foreach ($cursos as $curso) {
                    if (isset($curso['estado']) && $curso['estado'] === $status) {
                        $relevant_student_ids[] = $id;
                        break; // Encontramos uno, pasamos al siguiente estudiante
                    }
                }
            }
        }
        
        return array_unique($relevant_student_ids);
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

        // --- INICIO LÓGICA DE MENOR DE EDAD (PARA VISTA DE IMPRESIÓN) ---
        $info_menor_html = '';
        if (strpos($cedula, '-') !== false) {
            // Es un menor, la cédula es del tutor
            list($cedula_tutor, $id_menor) = explode('-', $cedula, 2);
            
            // Buscar al tutor por su cédula
            $tutor_query = get_posts(array(
                'post_type' => 'estudiante',
                'meta_key' => 'cedula',
                'meta_value' => $cedula_tutor, // Busca la cédula exacta del tutor
                'posts_per_page' => 1
            ));
            
            // Para el PDF, usamos un estilo más simple
            $info_menor_html = '<div style="font-size: 10pt; color: #ef4444; font-weight: bold; margin-top: 5px;">* Es menor de edad.</div>';

            if ($tutor_query) {
                $nombre_tutor = $tutor_query[0]->post_title;
                $info_menor_html .= '<div style="font-size: 10pt; color: #555; font-weight: bold; margin-top: 5px;">* Tutor registrado: ' . esc_html($nombre_tutor) . ' (Cédula: ' . esc_html($cedula_tutor) . ')</div>';
            } else {
                 $info_menor_html .= '<div style="font-size: 10pt; color: #555; font-weight: bold; margin-top: 5px;">* Cédula de tutor: ' . esc_html($cedula_tutor) . ' (No registrado como estudiante).</div>';
            }
        }
        // --- FIN LÓGICA DE MENOR DE EDAD ---

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
                    <div class="data-row">
                        <div class="data-label">Cédula / ID:</div>
                        <div class="data-value">
                            <?php echo esc_html($cedula); ?>
                            <?php echo $info_menor_html; // Mostrar información del tutor/menor ?>
                        </div>
                    </div>
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
    
    /**
     * INICIO - FUNCIÓN AÑADIDA
     * Genera una plantilla de correo HTML estandarizada.
     * @param string $title El título principal que se muestra en la cabecera del correo.
     * @param string $content_html El contenido principal del correo (párrafos).
     * @param string|null $summary_table_title Título para la tabla de resumen (opcional).
     * @param array|null $summary_data Datos para la tabla de resumen (opcional).
     * @param array|null $button_data Datos para el botón de acción (opcional) [ 'url' => '', 'text' => '' ].
     * @return string El HTML completo del correo.
     */
    public static function _get_email_template($title, $content_html, $summary_table_title = null, $summary_data = null, $button_data = null) {
        // Intenta obtener la URL del logo. ID 5024 basado en otros archivos.
        $logo_url = wp_get_attachment_url(5024); 
        $logo_html = $logo_url ? '<img src="' . esc_url($logo_url) . '" alt="' . get_bloginfo('name') . '" style="max-width: 180px; margin-bottom: 20px;">' : '';

        $summary_html = '';
        if ($summary_table_title && !empty($summary_data) && is_array($summary_data)) {
            $summary_html .= '<h3 style="color: #141f53; margin-top: 25px; border-top: 1px solid #eee; padding-top: 20px;">' . esc_html($summary_table_title) . '</h3>';
            $summary_html .= '<table class="summary-table" style="width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px;">';
            foreach ($summary_data as $key => $value) {
                $summary_html .= '<tr>';
                $summary_html .= '<td style="padding: 10px; border: 1px solid #ddd; background-color: #f9f9f9; font-weight: 600; width: 35%;">' . esc_html($key) . '</td>';
                $summary_html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html($value) . '</td>';
                $summary_html .= '</tr>';
            }
            $summary_html .= '</table>';
        }

        $button_html = '';
        if (!empty($button_data) && is_array($button_data) && isset($button_data['url'], $button_data['text'])) {
            $button_html = '<p style="text-align: center; margin-top: 25px;"><a href="' . esc_url($button_data['url']) . '" class="button" style="display: inline-block; padding: 12px 25px; background-color: #4f46e5; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px;">' . esc_html($button_data['text']) . '</a></p>';
        }

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($title); ?></title>
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    background-color: #f4f7f6;
                }
                .container {
                    width: 90%;
                    max-width: 600px;
                    margin: 20px auto;
                    background-color: #ffffff;
                    border: 1px solid #ddd;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
                }
                .header {
                    padding: 30px;
                    background-color: #141f53;
                    text-align: center;
                    border-bottom: 5px solid #4f46e5;
                }
                .header h1 {
                    color: #ffffff;
                    margin: 0;
                    font-size: 24px;
                }
                .content {
                    padding: 35px;
                    color: #333;
                    font-size: 16px;
                    line-height: 1.6;
                }
                .content p {
                    margin: 0 0 15px;
                }
                .content p.last {
                    margin-bottom: 0;
                }
                .footer {
                    padding: 20px;
                    text-align: center;
                    font-size: 12px;
                    color: #888;
                    background-color: #f9f9f9;
                    border-top: 1px solid #ddd;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <?php if ($logo_html): ?>
                        <?php echo $logo_html; ?>
                    <?php endif; ?>
                    <h1 style="color: #ffffff; margin: 0; font-size: 24px;"><?php echo esc_html($title); ?></h1>
                </div>
                <div class="content">
                    <?php echo $content_html; // Contenido principal del correo ?>
                    <?php echo $summary_html; // Tabla de resumen (si existe) ?>
                    <?php echo $button_html; // Botón (si existe) ?>
                </div>
                <div class="footer">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo get_bloginfo('name'); ?>. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    // FIN - FUNCIÓN AÑADIDA

    /**
     * [NUEVA FUNCIÓN] Reemplaza las etiquetas dinámicas en una plantilla de correo.
     * @param string $template El cuerpo del correo con etiquetas.
     * @param int $student_id ID del estudiante.
     * @param string $group El grupo de destinatarios (ej. 'por_curso').
     * @param string $curso_nombre El nombre del curso (si el grupo es 'por_curso').
     * @return string El cuerpo del correo con las etiquetas reemplazadas.
     */
    public static function _replace_dynamic_tags($template, $student_id, $group, $curso_nombre) {
        $student_post = get_post($student_id);
        if (!$student_post) {
            return $template; // Devuelve la plantilla sin cambios si el estudiante no existe
        }

        $nombre_estudiante = $student_post->post_title;
        $cedula = get_field('cedula', $student_id);

        $nombre_curso_especifico = 'N/A';
        $matricula_especifica = 'N/A';

        // Solo buscar datos del curso si el envío es por curso
        if ($group === 'por_curso' && !empty($curso_nombre)) {
            $cursos = get_field('cursos_inscritos', $student_id);
            if ($cursos) {
                foreach ($cursos as $curso) {
                    if ($curso['nombre_curso'] === $curso_nombre) {
                        $nombre_curso_especifico = $curso['nombre_curso'];
                        $matricula_especifica = $curso['matricula'] ?? 'N/A';
                        break; // Encontramos el curso
                    }
                }
            }
        }

        // Reemplazar etiquetas
        $template = str_replace('[nombre_estudiante]', esc_html($nombre_estudiante), $template);
        $template = str_replace('[cedula]', esc_html($cedula), $template);
        $template = str_replace('[nombre_curso]', esc_html($nombre_curso_especifico), $template);
        $template = str_replace('[matricula]', esc_html($matricula_especifica), $template);

        return $template;
    }


    // *** INICIO - NUEVAS FUNCIONES DE PAGINACIÓN ***

    /**
     * Helper para normalizar strings para búsqueda (quitar acentos).
     * @param string $str
     * @return string
     */
    private static function sga_normalize_string($str) {
        if (empty($str)) return '';
        $str = preg_replace('/[áàâãä]/u', 'a', $str);
        $str = preg_replace('/[éèêë]/u', 'e', $str);
        $str = preg_replace('/[íìîï]/u', 'i', $str);
        $str = preg_replace('/[óòôõö]/u', 'o', $str);
        $str = preg_replace('/[úùûü]/u', 'u', $str);
        $str = preg_replace('/[ÁÀÂÃÄ]/u', 'A', $str);
        $str = preg_replace('/[ÉÈÊË]/u', 'E', $str);
        $str = preg_replace('/[ÍÌÎÏ]/u', 'I', $str);
        $str = preg_replace('/[ÓÒÔÕÖ]/u', 'O', $str);
        $str = preg_replace('/[ÚÙÛÜ]/u', 'U', $str);
        $str = preg_replace('/[ñ]/u', 'n', $str);
        $str = preg_replace('/[Ñ]/u', 'N', $str);
        return $str;
    }

    /**
     * [NUEVA FUNCIÓN DE FILTRADO Y PAGINACIÓN]
     * Obtiene los datos de inscripciones pendientes, filtrados y paginados.
     * @param array $args Argumentos de filtrado y paginación:
     * 'paged_nuevas' => (int) página para la tabla "Nuevas"
     * 'paged_seguimiento' => (int) página para la tabla "Seguimiento"
     * 'posts_per_page' => (int) resultados por página
     * 'search' => (string) término de búsqueda
     * 'course' => (string) nombre del curso
     * 'status' => (string) estado de la llamada
     * 'agent' => (string|int) ID del agente o 'unassigned'
     * 'current_user_role' => (string|null) Rol del usuario actual ('agente' or 'agente_infotep')
     * 'agent_visibility_ids' => (array) IDs de agentes que el usuario actual puede ver
     * 'can_approve' => (bool) Si el usuario puede ver todo
     * @return array Dos arrays: 'pending_calls' y 'in_progress'
     */
    public static function _get_filtered_and_paginated_inscriptions_data($args = []) {
        // 1. Valores por defecto
        $defaults = [
            'paged_nuevas' => 1,
            'paged_seguimiento' => 1,
            'posts_per_page' => 50,
            'search' => '',
            'course' => '',
            'status' => '',
            'agent' => '',
            'current_user_role' => null,
            'agent_visibility_ids' => [],
            'can_approve' => false,
            'infotep_category_slug' => 'cursos-infotep' // Hardcoding this for now
        ];
        $args = wp_parse_args($args, $defaults);

        // Normalizar búsqueda
        $search_term = strtolower(self::sga_normalize_string($args['search']));

        // 2. Obtener TODOS los IDs de estudiantes 'Inscrito'
        $pending_student_ids = self::_get_student_ids_by_enrollment_status('Inscrito');
        
        if (empty($pending_student_ids)) {
            $empty_result = ['data_slice' => [], 'total_count' => 0];
            return ['pending_calls' => $empty_result, 'in_progress' => $empty_result];
        }

        // 3. Pre-calentar caché de metadatos (ACF)
        update_postmeta_cache($pending_student_ids);

        // 4. Obtener todos los posts de una vez
        $estudiantes_inscritos = get_posts(array(
            'post_type' => 'estudiante',
            'posts_per_page' => -1,
            'post__in' => $pending_student_ids,
            'orderby' => 'ID', // Cambiado de post_title a ID
            'order' => 'DESC' // Cambiado de ASC a DESC
        ));

        // 5. Pre-cargar el mapa de categorías de cursos
        $all_cursos = get_posts(array('post_type' => 'curso', 'posts_per_page' => -1, 'post_status' => array('publish', 'private'))); // Incluir privados
        $course_category_map = [];
        $course_ids_to_check = wp_list_pluck($all_cursos, 'ID');
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
        
        // 6. Definir arrays de datos filtrados (aún sin paginar)
        $pending_calls_data_filtered = [];
        $in_progress_data_filtered = [];

        // 7. Bucle de filtrado (en memoria, muy rápido)
        foreach ($estudiantes_inscritos as $estudiante) {
            $cursos = get_field('cursos_inscritos', $estudiante->ID);
            $assignments = get_post_meta($estudiante->ID, '_sga_agent_assignments', true);
            $call_logs = get_post_meta($estudiante->ID, '_sga_call_log', true);
            $call_statuses = get_post_meta($estudiante->ID, '_sga_call_statuses', true);
            
            if (!is_array($assignments)) $assignments = [];
            if (!is_array($call_logs)) $call_logs = [];
            if (!is_array($call_statuses)) $call_statuses = [];

            if (!$cursos) continue;

            $student_cedula = get_field('cedula', $estudiante->ID);
            $student_email = get_field('email', $estudiante->ID);
            $student_telefono = get_field('telefono', $estudiante->ID);

            foreach ($cursos as $index => $curso) {
                if (!isset($curso['estado']) || $curso['estado'] != 'Inscrito') {
                    continue;
                }
                
                // --- INICIO CORRECCIÓN DE BUG (CURSO VACÍO) ---
                $course_name = $curso['nombre_curso'];
                if (empty($course_name)) {
                    continue; // Saltar esta inscripción si el nombre del curso está vacío
                }
                // --- FIN CORRECCIÓN DE BUG (CURSO VACÍO) ---


                // --- A. FILTRADO POR ROL (VISIBILIDAD) ---
                $course_categories = $course_category_map[$course_name] ?? [];
                $is_infotep_course = in_array($args['infotep_category_slug'], $course_categories);
                $agent_id = $assignments[$index] ?? 'unassigned';
                $current_call_status = $call_statuses[$index] ?? 'pendiente';
                $has_call_log = isset($call_logs[$index]);

                $should_display = $args['can_approve'];

                if (!$args['can_approve']) {
                    $is_assigned_to_group = is_numeric($agent_id) && in_array(intval($agent_id), $args['agent_visibility_ids']);
                    $is_unassigned = ($agent_id === 'unassigned');
                    
                    if ($args['current_user_role'] === 'agente_infotep') {
                        $should_display = ($is_infotep_course && ($is_assigned_to_group || $is_unassigned));
                    } elseif ($args['current_user_role'] === 'agente') {
                        $should_display = (!$is_infotep_course && ($is_assigned_to_group || $is_unassigned));
                    }
                }
                
                if (!$should_display) continue;

                // --- B. FILTRADO POR FORMULARIO ---
                // Filtro de Agente
                if (!empty($args['agent']) && $args['agent'] != $agent_id) {
                    continue;
                }
                // Filtro de Curso
                if (!empty($args['course']) && $args['course'] != $course_name) {
                    continue;
                }
                // Filtro de Estado de Llamada
                if (!empty($args['status']) && $args['status'] != $current_call_status) {
                    continue;
                }
                // Filtro de Búsqueda
                if (!empty($search_term)) {
                    $searchable_string = strtolower(self::sga_normalize_string(
                        $estudiante->post_title . ' ' . $student_cedula . ' ' . $student_email . ' ' . $student_telefono . ' ' . $course_name
                    ));
                    if (strpos($searchable_string, $search_term) === false) {
                        continue;
                    }
                }

                // --- C. SI PASA TODOS LOS FILTROS, CLASIFICAR ---
                $data = [
                    'estudiante' => $estudiante,
                    'curso' => $curso,
                    'index' => $index,
                    'agent_id' => $agent_id,
                    'current_call_status' => $current_call_status,
                    'call_info' => $call_logs[$index] ?? null,
                ];
                
                if ($current_call_status === 'pendiente' && !$has_call_log) {
                    $pending_calls_data_filtered[] = $data;
                } else {
                    $in_progress_data_filtered[] = $data;
                }
            } // end foreach curso
        } // end foreach estudiante

        // *** NUEVA SECCIÓN DE ORDENAMIENTO ***
        // Ordenar ambas listas por fecha de inscripción descendente
        $sort_by_date_desc = function($a, $b) {
            $time_a = isset($a['curso']['fecha_inscripcion']) ? strtotime($a['curso']['fecha_inscripcion']) : 0;
            $time_b = isset($b['curso']['fecha_inscripcion']) ? strtotime($b['curso']['fecha_inscripcion']) : 0;
            return $time_b - $time_a; // Descendente
        };
        
        usort($pending_calls_data_filtered, $sort_by_date_desc);
        usort($in_progress_data_filtered, $sort_by_date_desc);
        // *** FIN NUEVA SECCIÓN DE ORDENAMIENTO ***

        // 8. PAGINACIÓN
        $posts_per_page = (int)$args['posts_per_page'];
        
        // Paginar "Nuevas"
        $total_pending = count($pending_calls_data_filtered);
        $pending_slice = array_slice(
            $pending_calls_data_filtered,
            ((int)$args['paged_nuevas'] - 1) * $posts_per_page,
            $posts_per_page
        );

        // Paginar "Seguimiento"
        $total_inprogress = count($in_progress_data_filtered);
        $inprogress_slice = array_slice(
            $in_progress_data_filtered,
            ((int)$args['paged_seguimiento'] - 1) * $posts_per_page,
            $posts_per_page
        );

        // 9. Retornar los datos
        return [
            'pending_calls' => [
                'data_slice' => $pending_slice,
                'total_count' => $total_pending,
            ],
            'in_progress' => [
                'data_slice' => $inprogress_slice,
                'total_count' => $total_inprogress,
            ]
        ];
    }
    // *** FIN - NUEVAS FUNCIONES DE PAGINACIÓN ***
    
    // *** INICIO - NUEVA FUNCIÓN PARA PAGINACIÓN DE ESTUDIANTES ***
    /**
     * [NUEVA FUNCIÓN DE FILTRADO Y PAGINACIÓN PARA ESTUDIANTES GENERALES]
     * Obtiene los datos de estudiantes, filtrados por búsqueda y paginados.
     * @param array $args Argumentos de filtrado y paginación:
     * 'paged' => (int) página actual
     * 'posts_per_page' => (int) resultados por página
     * 'search' => (string) término de búsqueda (nombre, cédula, email, teléfono)
     * @return array Un array con 'students' (los posts) y 'total_found', 'total_pages'.
     */
    public static function _get_filtered_and_paginated_students_data($args = []) {
        // 1. Valores por defecto
        $defaults = [
            'paged' => 1,
            'posts_per_page' => 50,
            'search' => '',
        ];
        $args = wp_parse_args($args, $defaults);

        // 2. Construir los argumentos de WP_Query base (paginación y orden)
        $query_args = [
            'post_type' => 'estudiante',
            'posts_per_page' => (int)$args['posts_per_page'],
            'paged' => (int)$args['paged'],
            'orderby' => 'post_title',
            'order' => 'ASC',
            'post_status' => 'publish',
        ];

        // 3. Lógica de Búsqueda (si hay término de búsqueda)
        if (!empty($args['search'])) {
            global $wpdb;
            
            // IDs por Título (usando 's')
            $search_by_title_args = [
                'post_type' => 'estudiante',
                'posts_per_page' => -1,
                's' => $args['search'],
                'fields' => 'ids',
                'post_status' => 'publish',
            ];
            $ids_by_title = get_posts($search_by_title_args);
            
            // IDs por Meta (cédula, email, teléfono)
            $search_by_meta_args = [
                'post_type' => 'estudiante',
                'posts_per_page' => -1,
                'meta_query' => [
                    'relation' => 'OR',
                    ['key' => 'cedula', 'value' => $args['search'], 'compare' => 'LIKE'],
                    ['key' => 'email', 'value' => $args['search'], 'compare' => 'LIKE'],
                    ['key' => 'telefono', 'value' => $args['search'], 'compare' => 'LIKE']
                ],
                'fields' => 'ids',
                'post_status' => 'publish',
            ];
            $ids_by_meta = get_posts($search_by_meta_args);
            
            // Combinar IDs únicos
            $all_matching_ids = array_unique(array_merge($ids_by_title, $ids_by_meta));

            if (empty($all_matching_ids)) {
                // No hay resultados, forzamos que la query principal no devuelva nada
                $query_args['post__in'] = [0];
            } else {
                // La query principal ahora usará estos IDs y los paginará
                $query_args['post__in'] = $all_matching_ids;
            }
        }

        // 4. Ejecutar la query principal (paginada)
        $students_query = new WP_Query($query_args);
        
        // 5. Pre-calentar caché de metadatos
        if ($students_query->have_posts()) {
            update_postmeta_cache(wp_list_pluck($students_query->posts, 'ID'));
        }

        // 6. Retornar los resultados y la info de paginación
        return [
            'students' => $students_query->posts,
            'total_found' => $students_query->found_posts,
            'total_pages' => $students_query->max_num_pages
        ];
    }
    // *** FIN - NUEVA FUNCIÓN PARA PAGINACIÓN DE ESTUDIANTES ***
}


