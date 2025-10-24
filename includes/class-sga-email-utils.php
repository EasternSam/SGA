<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_Email_Utils
 *
 * Contiene funciones estáticas para la generación de plantillas de correo
 * y el envío de correos específicos.
 */
class SGA_Email_Utils {

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
            SGA_Logging_Utils::_log_activity('Error de Correo', "Intento de envío de correo de pago pendiente a dirección inválida: " . esc_html($student_email));
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
            SGA_Logging_Utils::_log_activity('Correo Enviado', "Correo de pago pendiente enviado a {$student_name} ({$student_email}) para el curso '{$course_name}'.", 0);

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
            SGA_Logging_Utils::_log_activity('Correo Enviado', "Correo de inscripción (pagos manuales) enviado a {$student_name} ({$student_email}) para el curso '{$course_name}'.", 0);
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($student_email, $subject, $body, $headers);
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
     * Envía un correo con el recibo de pago en PDF adjunto.
     */
    public static function _send_payment_receipt_email($recipient_email, $pdf_data, $subject, $filename) {
        if (!is_email($recipient_email)) {
            SGA_Logging_Utils::_log_activity('Error de Recibo', 'Destinatario de recibo no válido: ' . esc_html($recipient_email));
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
        SGA_Logging_Utils::_log_activity($log_title, "El recibo '{$subject}' fue procesado para {$recipient_email}.");
        return $sent;
    }
    
    /**
     * Envía un correo con un reporte en PDF adjunto.
     */
    public static function _send_report_email($pdf_data, $subject, $filename) {
        $options = get_option('sga_report_options');
        $recipient = !empty($options['recipient_email']) ? $options['recipient_email'] : get_option('admin_email');
        if (!is_email($recipient)) {
            SGA_Logging_Utils::_log_activity('Error de Reporte', 'Destinatario no válido: ' . esc_html($recipient));
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
        SGA_Logging_Utils::_log_activity($log_title, "El reporte '{$subject}' fue procesado para {$recipient}.");
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

        if (in_array($context_group, ['por_curso', 'matriculados', 'pendientes']) && !empty($context_course_name)) {
            $cursos_inscritos = get_field('cursos_inscritos', $student_id);
            if ($cursos_inscritos) {
                foreach ($cursos_inscritos as $curso) {
                    // Si el contexto es 'por_curso', debe coincidir el nombre.
                    // Si el contexto es general (matriculados/pendientes) y el curso coincide con el filtro.
                    if ($curso['nombre_curso'] === $context_course_name) {
                        $matricula = !empty($curso['matricula']) ? $curso['matricula'] : 'Pendiente';
                        $nombre_curso = $curso['nombre_curso'];
                        break; 
                    }
                }
            }
        }
        // Si no se encontró un curso específico en el contexto, buscamos el primero para rellenar,
        // aunque esto no es ideal para mensajes masivos no segmentados por curso.
        if (empty($nombre_curso) && $context_group !== 'por_curso') {
            $cursos_inscritos = get_field('cursos_inscritos', $student_id);
             if ($cursos_inscritos) {
                $curso_base = $cursos_inscritos[0];
                $matricula = !empty($curso_base['matricula']) ? $curso_base['matricula'] : 'Pendiente';
                $nombre_curso = $curso_base['nombre_curso'];
            }
        }
        
        $replacements['[nombre_curso]'] = $nombre_curso;
        $replacements['[matricula]'] = $matricula;

        foreach ($replacements as $tag => $value) {
            $content = str_replace($tag, $value, $content);
        }

        return $content;
    }
}
