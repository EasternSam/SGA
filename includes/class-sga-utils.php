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
     * Obtiene una lista de todos los usuarios con el rol 'agente'.
     * @return array Array de objetos WP_User.
     */
    public static function _get_sga_agents() {
        $args = array(
            'role'    => 'agente',
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
     * Obtiene el siguiente agente en la rotación para asignación automática.
     * @return int|null ID del agente o null si no hay agentes.
     */
    public static function _get_next_agent_for_assignment() {
        $agents = self::_get_sga_agents();
        if (empty($agents)) {
            return null;
        }

        $last_assigned_index = get_transient('sga_last_assigned_agent_index');
        if (false === $last_assigned_index) {
            $next_index = 0;
        } else {
            $next_index = ($last_assigned_index + 1) % count($agents);
        }

        set_transient('sga_last_assigned_agent_index', $next_index, DAY_IN_SECONDS);
        return $agents[$next_index]->ID;
    }

    /**
     * Obtiene y formatea una plantilla de correo electrónico HTML.
     * @return string El HTML completo del correo.
     */
    public static function _get_email_template($title, $content_html, $summary_table_title = '', $summary_data = [], $button_data = []) {
        $logo_url = 'https://portal-dev.centu.edu.do/wp-content/uploads/2025/07/centu-logo.png'; // Considera hacerlo una opción en los ajustes
        $summary_html = '';
        if (!empty($summary_data) && !empty($summary_table_title)) {
            $summary_html .= '<table class="summary-table" border="0" width="100%" cellspacing="0" cellpadding="0"><thead><tr><th colspan="2">' . esc_html($summary_table_title) . '</th></tr></thead><tbody>';
            foreach ($summary_data as $label => $value) {
                $summary_html .= '<tr><td class="label">' . esc_html($label) . '</td><td>' . esc_html($value) . '</td></tr>';
            }
            $summary_html .= '</tbody></table>';
        }
        $button_html = '';
        if (!empty($button_data) && isset($button_data['url']) && isset($button_data['text'])) {
            $button_html = '<table class="button-table" border="0" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding-top: 20px;"><a href="' . esc_url($button_data['url']) . '" class="button-link">' . esc_html($button_data['text']) . '</a></td></tr></table>';
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
                .email-container { padding: 20px 0; }
                .email-body { border-collapse: collapse; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); width: 600px; margin: 0 auto; }
                .header { background-color: #141f53; padding: 30px 20px; border-top-left-radius: 12px; border-top-right-radius: 12px; text-align: center; }
                .logo { display: block; max-width: 180px; height: auto; margin: 0 auto; }
                .content-cell { padding: 40px 30px; font-family: Arial, sans-serif; }
                .content-cell h1 { font-size: 26px; font-weight: bold; margin-top: 0; margin-bottom: 20px; color: #141f53; }
                .content-cell p { margin: 0 0 25px 0; font-size: 16px; line-height: 1.7; color: #555555; }
                .content-cell p.last { margin-bottom: 0; }
                .content-cell h2 { font-size: 20px; font-weight: 600; margin-top: 35px; margin-bottom: 15px; color: #141f53; }
                .summary-table { border-collapse: collapse; border: 1px solid #e0e0e0; border-radius: 8px; width: 100%; overflow: hidden; margin-top: 25px; }
                .summary-table th { padding: 15px; background-color: #f8f9fa; font-size: 18px; text-align: left; color: #141f53; border-bottom: 1px solid #e0e0e0; }
                .summary-table td { padding: 15px; font-size: 15px; color: #555; border-bottom: 1px solid #eeeeee; }
                .summary-table td.label { color: #333; font-weight: 600; width: 40%; }
                .summary-table tr:last-child td { border-bottom: none; }
                .button-table { margin-top: 30px; }
                .button-link { display: inline-block; padding: 14px 28px; background-color: #0052cc; color: #ffffff !important; text-decoration: none; font-weight: bold; border-radius: 8px; font-size: 16px; }
                .button-link:hover { text-decoration: none; }
                .footer { padding: 30px; text-align: center; background-color: #f4f7f6; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; font-family: Arial, sans-serif; }
                .footer p { margin: 0; font-size: 13px; color: #888888; }
                .footer a { color: #141f53; text-decoration: none; font-weight: 600; }
            </style>
        </head>
        <body style="margin: 0; padding: 0; background-color: #f4f7f6;">
            <table border="0" width="100%" cellspacing="0" cellpadding="0"> <tbody> <tr> <td class="email-container">
            <table class="email-body" border="0" cellspacing="0" cellpadding="0">
                <tr> <td class="header"> <img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="Logo de CENTU" width="180" /> </td> </tr>
                <tr> <td class="content-cell">
                    <h1><?php echo esc_html($title); ?></h1>
                    <?php echo wp_kses_post($content_html); ?>
                    <?php if (!empty($summary_html)) echo $summary_html; ?>
                    <?php if (!empty($button_html)) echo $button_html; ?>
                </td> </tr>
                <tr> <td class="footer"> <p>© <?php echo date('Y'); ?> CENTU. Todos los derechos reservados.<br /> <a href="<?php echo esc_url(home_url('/')); ?>">Visita nuestro sitio web</a> </p> </td> </tr>
            </tbody> </table>
            </td> </tr> </tbody> </table>
        </body>
        </html>
        <?php
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
}

