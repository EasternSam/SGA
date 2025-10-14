<?php

if (!defined('ABSPATH')) exit;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Clase SGA_Reports
 *
 * Gestiona la generación de todos los reportes en PDF, exportaciones a Excel/CSV
 * y maneja las acciones de admin-post relacionadas.
 */
class SGA_Reports {

    public function __construct() {
        // Hooks para acciones de admin-post (formularios en el backend)
        add_action('admin_post_sga_generate_manual_report', array($this, 'handle_manual_report_generation'));
        add_action('admin_post_sga_print_payment_history', array($this, 'handle_print_payment_history'));
        add_action('admin_post_sga_approve_single', array($this, 'handle_single_approval'));
        add_action('admin_post_sga_approve_bulk', array($this, 'handle_bulk_approval'));

        // Hook para el cron job de reportes programados
        add_action('sga_daily_report_cron', array($this, 'handle_scheduled_reports'));
        add_action('sga_archive_daily_calls_cron', array($this, 'archive_daily_calls'));
    }

    // --- MANEJADORES DE ACCIONES DE ADMIN-POST ---

    public function handle_single_approval() {
        if (isset($_GET['post_id']) && isset($_GET['row_index']) && isset($_GET['_wpnonce'])) {
            $post_id = intval($_GET['post_id']);
            $row_index = intval($_GET['row_index']);
            $nonce = sanitize_text_field($_GET['_wpnonce']);
            if (wp_verify_nonce($nonce, 'sga_approve_nonce_' . $post_id . '_' . $row_index)) {
                $post = get_post($post_id);
                $cedula = get_field('cedula', $post_id);
                SGA_Utils::_aprobar_estudiante($post_id, $row_index, $cedula, $post->post_title);
                wp_redirect(admin_url('admin.php?page=sga_dashboard&approved=1'));
                exit;
            }
        }
        wp_die('Error de seguridad o datos inválidos.');
    }

    public function handle_bulk_approval() {
        if (isset($_POST['inscripciones_a_aprobar']) && isset($_POST['_wpnonce_bulk_approve'])) {
            if (wp_verify_nonce($_POST['_wpnonce_bulk_approve'], 'sga_approve_bulk_nonce')) {
                $aprobados_count = 0;
                $inscripciones = (array) $_POST['inscripciones_a_aprobar'];
                foreach ($inscripciones as $inscripcion_data) {
                    list($post_id, $row_index) = explode(':', $inscripcion_data);
                    $post_id = intval($post_id);
                    $row_index = intval($row_index);
                    if ($post_id > 0) {
                        $post = get_post($post_id);
                        $cedula = get_field('cedula', $post_id);
                        SGA_Utils::_aprobar_estudiante($post_id, $row_index, $cedula, $post->post_title);
                        $aprobados_count++;
                    }
                }
                if ($aprobados_count > 0) {
                    wp_redirect(admin_url('admin.php?page=sga_dashboard&approved=' . $aprobados_count));
                    exit;
                }
            }
        }
        wp_die('Error de seguridad o no se seleccionaron estudiantes.');
    }

    public function handle_manual_report_generation() {
        if (!isset($_POST['_wpnonce_manual_report']) || !wp_verify_nonce($_POST['_wpnonce_manual_report'], 'sga_manual_report_nonce')) wp_die('Error de seguridad.');
        if (!current_user_can('manage_options')) wp_die('No tienes permisos para realizar esta acción.');

        $report_type = sanitize_key($_POST['report_type']);
        $report_action = sanitize_key($_POST['report_action']);
        
        $args = [
            'date_from' => isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '',
            'date_to' => isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '',
            'curso_filtro' => isset($_POST['curso_filtro']) ? sanitize_text_field($_POST['curso_filtro']) : '',
            'agente_filtro' => isset($_POST['agente_filtro']) ? sanitize_text_field($_POST['agente_filtro']) : ''
        ];

        $report_data = $this->_generate_report($report_type, $args);
        if (!$report_data) {
            add_settings_error('sga_reports', 'sga_report_error', 'Error: La librería Dompdf es necesaria para generar reportes en PDF. Por favor, instálala y vuelve a intentarlo.', 'error');
            wp_redirect(admin_url('admin.php?page=sga-settings&tab=reportes'));
            exit;
        }

        if ($report_action === 'download') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $report_data['filename'] . '"');
            echo $report_data['pdf_data'];
            SGA_Utils::_log_activity('Reporte Descargado', "Usuario " . wp_get_current_user()->user_login . " descargó: " . $report_data['title']);
            exit;
        } elseif ($report_action === 'email') {
            $sent = SGA_Utils::_send_report_email($report_data['pdf_data'], $report_data['title'], $report_data['filename']);
            add_settings_error('sga_reports', 'sga_report_sent', $sent ? 'El reporte ha sido enviado.' : 'Error al enviar el correo.', $sent ? 'success' : 'error');
        }

        wp_redirect(admin_url('admin.php?page=sga-settings&tab=reportes'));
        exit;
    }

    public function handle_print_payment_history() {
        if (!isset($_POST['_wpnonce_print_history']) || !wp_verify_nonce($_POST['_wpnonce_print_history'], 'sga_print_history_nonce')) {
            wp_die('Error de seguridad.');
        }
        if (!current_user_can('edit_posts')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        $report_data = $this->_generate_report('payment_history');
        if (!$report_data) {
            wp_die('Error: La librería Dompdf es necesaria para generar reportes en PDF. Por favor, instálala y vuelve a intentarlo.');
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $report_data['filename'] . '"');
        echo $report_data['pdf_data'];
        SGA_Utils::_log_activity('Reporte de Pagos Descargado', "Usuario " . wp_get_current_user()->user_login . " descargó el historial de pagos completo.");
        exit;
    }

    // --- LÓGICA DE REPORTES Y EXPORTACIONES ---
    
    public function handle_scheduled_reports() {
        $options = get_option('sga_report_options');
        $today = date('N'); // 1 (para Lunes) hasta 7 (para Domingo)
        $day_of_month = date('j');

        if ($today == 1 && !empty($options['enable_weekly'])) { // Si es Lunes
            $report_pendientes = $this->_generate_report('pendientes');
            if($report_pendientes) SGA_Utils::_send_report_email($report_pendientes['pdf_data'], $report_pendientes['title'], $report_pendientes['filename']);
            
            $report_matriculados = $this->_generate_report('matriculados');
            if($report_matriculados) SGA_Utils::_send_report_email($report_matriculados['pdf_data'], $report_matriculados['title'], $report_matriculados['filename']);
        }

        if ($day_of_month == 1 && !empty($options['enable_monthly'])) { // Si es el primer día del mes
            foreach (['matriculados', 'pendientes', 'cursos', 'log'] as $type) {
                $report_data = $this->_generate_report($type);
                if($report_data) SGA_Utils::_send_report_email($report_data['pdf_data'], $report_data['title'], $report_data['filename']);
                sleep(2); // Pequeña pausa entre envíos
            }
        }
    }

    private function _generate_report($type, $args = []) {
        if (!class_exists('Dompdf\Dompdf')) {
            SGA_Utils::_log_activity('Error de Reporte', 'La librería Dompdf no está instalada o no se puede encontrar.');
            return false;
        }

        $logo_id = 5024; // Considera hacer esto una opción en los ajustes del plugin
        $logo_src = '';
        if ($logo_id && $logo_path = get_attached_file($logo_id)) {
            $mime_type = get_post_mime_type($logo_id);
            if ($mime_type && file_exists($logo_path)) {
                $logo_data = file_get_contents($logo_path);
                $logo_base64 = base64_encode($logo_data);
                $logo_src = 'data:' . $mime_type . ';base64,' . $logo_base64;
            }
        }

        $report_title = '';
        $headers = [];
        $rows = [];

        switch ($type) {
            case 'matriculados':
                $report_title = 'Reporte de Estudiantes Matriculados';
                $headers = ['Matrícula', 'Nombre', 'Cédula', 'Email', 'Teléfono', 'Curso', 'Horario'];
                $students_data = SGA_Utils::_get_filtered_students('', $args['curso_filtro'] ?? '');
                foreach ($students_data as $data) {
                    $rows[] = [
                        esc_html(isset($data['curso']['matricula']) ? $data['curso']['matricula'] : ''),
                        esc_html($data['estudiante']->post_title),
                        esc_html(get_field('cedula', $data['estudiante']->ID)),
                        esc_html(get_field('email', $data['estudiante']->ID)),
                        esc_html(get_field('telefono', $data['estudiante']->ID)),
                        esc_html($data['curso']['nombre_curso']),
                        esc_html(isset($data['curso']['horario']) ? $data['curso']['horario'] : '')
                    ];
                }
                break;
            case 'pendientes':
                $report_title = 'Reporte de Inscripciones Pendientes';
                $headers = ['Nombre', 'Cédula', 'Email', 'Teléfono', 'Curso Inscrito', 'Horario', 'Fecha Inscripción'];
                $estudiantes_query = get_posts(array('post_type' => 'estudiante', 'posts_per_page' => -1));
                if ($estudiantes_query && function_exists('get_field')) {
                    foreach ($estudiantes_query as $estudiante) {
                        $cursos = get_field('cursos_inscritos', $estudiante->ID);
                        if ($cursos) {
                            foreach ($cursos as $curso) {
                                if (isset($curso['estado']) && $curso['estado'] == 'Inscrito') {
                                     if (!empty($args['curso_filtro']) && $curso['nombre_curso'] !== $args['curso_filtro']) {
                                        continue;
                                    }
                                    $rows[] = [
                                        esc_html($estudiante->post_title),
                                        esc_html(get_field('cedula', $estudiante->ID)),
                                        esc_html(get_field('email', $estudiante->ID)),
                                        esc_html(get_field('telefono', $estudiante->ID)),
                                        esc_html($curso['nombre_curso']),
                                        esc_html($curso['horario']),
                                        esc_html($curso['fecha_inscripcion'])
                                    ];
                                }
                            }
                        }
                    }
                }
                break;
            case 'cursos':
                $report_title = 'Reporte de Cursos Activos';
                $headers = ['Nombre del Curso', 'Horarios', 'Escuela', 'Modalidad', 'Precio', 'Mensualidad', 'Duración', 'Fecha de Inicio'];
                $cursos_activos = get_posts(array('post_type' => 'curso', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
                if ($cursos_activos && function_exists('get_field')) {
                    foreach ($cursos_activos as $curso) {
                        $horarios_repeater = get_field('horarios_del_curso', $curso->ID);
                        $horarios_display = '';
                        if ($horarios_repeater) {
                            $horarios_array = [];
                            foreach ($horarios_repeater as $horario) $horarios_array[] = $horario['dias_de_la_semana'] . ' ' . $horario['hora'];
                            $horarios_display = implode(', ', $horarios_array);
                        }
                        $modalidad = !empty($horarios_repeater) ? $horarios_repeater[0]['modalidad'] : '';
                        $fecha_inicio = !empty($horarios_repeater) ? $horarios_repeater[0]['fecha_de_inicio'] : '';

                        $escuelas = get_the_terms($curso->ID, 'category');
                        $escuela_display = 'N/A';
                        if ($escuelas && !is_wp_error($escuelas)) {
                            $escuela_names = array_map(function($term) { return $term->name; }, $escuelas);
                            $escuela_display = implode(', ', $escuela_names);
                        }
                        $rows[] = [
                            esc_html($curso->post_title),
                            esc_html($horarios_display),
                            esc_html($escuela_display),
                            esc_html($modalidad),
                            esc_html(get_field('precio_del_curso', $curso->ID)),
                            esc_html(get_field('mensualidad', $curso->ID)),
                            esc_html(get_field('duracion_del_curso', $curso->ID)),
                            esc_html($fecha_inicio),
                        ];
                    }
                }
                break;
            case 'log':
                $report_title = 'Reporte de Actividad';
                $headers = ['Acción', 'Detalles', 'Usuario', 'Fecha'];
                $query_args = [
                    'post_type' => 'gestion_log', 'posts_per_page' => -1,
                    'orderby' => 'date', 'order' => 'DESC'
                ];
                if (!empty($args['date_from']) || !empty($args['date_to'])) {
                    $query_args['date_query'] = [];
                    if (!empty($args['date_from'])) $query_args['date_query']['after'] = $args['date_from'];
                    if (!empty($args['date_to'])) $query_args['date_query']['before'] = $args['date_to'];
                }
                $log_entries = get_posts($query_args);

                if ($log_entries) {
                    foreach ($log_entries as $entry) {
                        $user_id = get_post_meta($entry->ID, '_log_user_id', true);
                        $user_info = get_userdata($user_id);
                        $user_name = ($user_id == 0) ? 'Sistema' : ($user_info ? $user_info->display_name : 'Usuario Desconocido');
                        $rows[] = [
                            esc_html($entry->post_title),
                            wp_strip_all_tags($entry->post_content),
                            esc_html($user_name),
                            get_the_date('Y-m-d H:i:s', $entry),
                        ];
                    }
                }
                break;
            case 'historial_llamadas':
                $report_title = 'Reporte de Historial de Llamadas';
                $headers = ['Agente', 'Estudiante', 'Cédula', 'Email', 'Teléfono', 'Curso', 'Comentario', 'Fecha'];
                
                // 1. Obtener llamadas archivadas
                $query_args_hist = [
                    'post_type' => 'sga_llamada_hist', 'posts_per_page' => -1,
                    'orderby' => 'date', 'order' => 'DESC'
                ];
                if (!empty($args['date_from']) || !empty($args['date_to'])) {
                    $query_args_hist['date_query'] = [];
                    if (!empty($args['date_from'])) $query_args_hist['date_query']['after'] = $args['date_from'];
                    if (!empty($args['date_to'])) $query_args_hist['date_query']['before'] = $args['date_to'];
                }
                 if (!empty($args['agente_filtro'])) {
                    $query_args_hist['author'] = intval($args['agente_filtro']);
                }
                if (!empty($args['curso_filtro'])) {
                    $query_args_hist['meta_query'] = [['key' => '_course_name', 'value' => $args['curso_filtro']]];
                }
                $llamadas_hist_query = new WP_Query($query_args_hist);

                // 2. Obtener llamadas del día actual
                $query_args_hoy = [
                    'post_type' => 'sga_llamada', 'posts_per_page' => -1,
                    'orderby' => 'date', 'order' => 'DESC'
                ];
                 if (!empty($args['agente_filtro'])) {
                    $query_args_hoy['author'] = intval($args['agente_filtro']);
                }
                if (!empty($args['curso_filtro'])) {
                    $query_args_hoy['meta_query'] = [['key' => '_course_name', 'value' => $args['curso_filtro']]];
                }
                $llamadas_hoy_query = new WP_Query($query_args_hoy);
                
                $todas_las_llamadas = array_merge($llamadas_hist_query->posts, $llamadas_hoy_query->posts);

                if (!empty($todas_las_llamadas)) {
                    foreach ($todas_las_llamadas as $llamada) {
                         $user_info = get_userdata($llamada->post_author);
                         $student_id = get_post_meta($llamada->ID, '_student_id', true);
                        $rows[] = [
                            esc_html($user_info ? $user_info->display_name : 'N/A'),
                            esc_html(get_post_meta($llamada->ID, '_student_name', true)),
                            esc_html(get_field('cedula', $student_id)),
                            esc_html(get_field('email', $student_id)),
                            esc_html(get_field('telefono', $student_id)),
                            esc_html(get_post_meta($llamada->ID, '_course_name', true)),
                            esc_html($llamada->post_content),
                            get_the_date('Y-m-d H:i:s', $llamada),
                        ];
                    }
                }
                break;
            case 'payment_history':
                $report_title = 'Historial Completo de Pagos';
                $headers = ['Fecha', 'Estudiante', 'Concepto', 'Monto', 'Moneda', 'ID Transacción'];
                $pagos = get_posts(array('post_type' => 'sga_pago', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC'));
                if ($pagos) {
                    foreach ($pagos as $pago) {
                        $rows[] = [
                            get_the_date('Y-m-d H:i:s', $pago),
                            esc_html(get_post_meta($pago->ID, '_student_name', true)),
                            esc_html(get_post_meta($pago->ID, '_payment_description', true)),
                            esc_html(get_post_meta($pago->ID, '_payment_amount', true)),
                            esc_html(get_post_meta($pago->ID, '_payment_currency', true)),
                            esc_html(get_post_meta($pago->ID, '_transaction_id', true))
                        ];
                    }
                }
                break;
        }

        ob_start();
        ?>
        <!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
        <style>body{font-family:Arial,sans-serif;font-size:10pt;color:#333}.header{text-align:center;margin-bottom:20px;border-bottom:2px solid #002060;padding-bottom:15px}.header img{max-height:60px;margin-bottom:10px}h1{font-size:16pt;color:#002060;margin:0}.subtitle{font-size:9pt;color:#555}table{width:100%;border-collapse:collapse;margin-top:20px;font-size:9pt}th,td{border:1px solid #ccc;padding:8px;text-align:left}thead th{background-color:#002060;color:#fff;font-weight:700}tbody tr:nth-child(2n){background-color:#f2f2f2}</style>
        </head><body>
            <div class="header">
                <?php if (!empty($logo_src)): ?><img src="<?php echo esc_url($logo_src); ?>" alt="Logo"><?php endif; ?>
                <h1><?php echo esc_html($report_title); ?></h1>
                <p class="subtitle">Generado el: <?php echo date_i18n('j \d\e F \d\e Y \a \l\a\s H:i'); ?></p>
            </div>
            <?php if (!empty($rows)): ?>
                <table>
                    <thead><tr><?php foreach ($headers as $header) echo '<th>' . esc_html($header) . '</th>'; ?></tr></thead>
                    <tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($row as $cell) echo '<td>' . $cell . '</td>'; ?></tr><?php endforeach; ?></tbody>
                </table>
            <?php else: ?><p>No hay datos disponibles para este reporte en este momento.</p><?php endif; ?>
        </body></html>
        <?php
        $html_content = ob_get_clean();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html_content);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $pdf_output = $dompdf->output();

        return [
            'pdf_data' => $pdf_output,
            'title' => $report_title,
            'filename' => sanitize_title($report_title) . '-' . date('Y-m-d') . '.pdf'
        ];
    }

    public function _generate_payment_invoice_pdf($invoice_data) {
        if (!class_exists('Dompdf\Dompdf')) {
            SGA_Utils::_log_activity('Error de Factura', 'La librería Dompdf no está instalada.');
            return false;
        }
        $logo_id = 5024;
        $logo_src = '';
        if ($logo_id && $logo_path = get_attached_file($logo_id)) {
            $mime_type = get_post_mime_type($logo_id);
            if ($mime_type && file_exists($logo_path)) {
                $logo_data = file_get_contents($logo_path);
                $logo_base64 = base64_encode($logo_data);
                $logo_src = 'data:' . $mime_type . ';base64,' . $logo_base64;
            }
        }
        $invoice_title = 'Recibo de Pago #' . $invoice_data['invoice_id'];

        ob_start();
        ?>
        <!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
            .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, .15); font-size: 16px; line-height: 24px; }
            .header { text-align: center; margin-bottom: 30px; }
            .header img { max-width: 200px; }
            .header h1 { color: #141f53; margin: 10px 0 0; }
            .invoice-details { margin-bottom: 40px; }
            .invoice-details table { width: 100%; }
            .invoice-details td { padding: 5px; }
            .invoice-details .right { text-align: right; }
            .item-table { width: 100%; line-height: inherit; text-align: left; border-collapse: collapse; }
            .item-table td { padding: 10px; vertical-align: top; }
            .item-table .heading td { background: #eee; border-bottom: 1px solid #ddd; font-weight: bold; }
            .item-table .item td { border-bottom: 1px solid #eee; }
            .item-table .total td { border-top: 2px solid #eee; font-weight: bold; }
            .footer { text-align: center; margin-top: 40px; font-size: 12px; color: #777; }
        </style>
        </head><body>
            <div class="invoice-box">
                <div class="header">
                    <?php if (!empty($logo_src)): ?><img src="<?php echo esc_url($logo_src); ?>" alt="Logo"><?php endif; ?>
                    <h1>RECIBO DE PAGO</h1>
                </div>
                <div class="invoice-details">
                    <table>
                        <tr>
                            <td>
                                <strong>Facturado a:</strong><br>
                                <?php echo esc_html($invoice_data['student_name']); ?><br>
                                Cédula: <?php echo esc_html($invoice_data['student_cedula']); ?>
                            </td>
                            <td class="right">
                                <strong>Recibo #:</strong> <?php echo esc_html($invoice_data['invoice_id']); ?><br>
                                <strong>Fecha de Pago:</strong> <?php echo esc_html(date_i18n('j \d\e F \d\e Y', strtotime($invoice_data['payment_date']))); ?><br>
                                <strong>ID de Transacción:</strong> <?php echo esc_html($invoice_data['transaction_id']); ?>
                            </td>
                        </tr>
                    </table>
                </div>
                <table class="item-table">
                    <tr class="heading">
                        <td>Descripción</td>
                        <td style="text-align: right;">Precio</td>
                    </tr>
                    <tr class="item">
                        <td><?php echo esc_html($invoice_data['payment_description']); ?></td>
                        <td style="text-align: right;"><?php echo esc_html(number_format(floatval($invoice_data['amount']), 2)); ?> <?php echo esc_html($invoice_data['currency']); ?></td>
                    </tr>
                    <tr class="total">
                        <td></td>
                        <td style="text-align: right;"><strong>Total: <?php echo esc_html(number_format(floatval($invoice_data['amount']), 2)); ?> <?php echo esc_html($invoice_data['currency']); ?></strong></td>
                    </tr>
                </table>
                <div class="footer">
                    <p>Gracias por su pago. Este es un recibo generado automáticamente.</p>
                    <p>CENTU | <?php echo esc_url(home_url('/')); ?></p>
                </div>
            </div>
        </body></html>
        <?php
        $html_content = ob_get_clean();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html_content);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf_output = $dompdf->output();

        return [
            'pdf_data' => $pdf_output,
            'title'    => $invoice_title,
            'filename' => 'recibo-pago-' . $invoice_data['invoice_id'] . '.pdf'
        ];
    }
    
    public function ajax_sga_print_invoice() {
        if (!isset($_REQUEST['payment_id']) || !isset($_REQUEST['_wpnonce'])) {
            wp_die('Parámetros inválidos.');
        }
        $payment_id = intval($_REQUEST['payment_id']);
        $nonce = sanitize_text_field($_REQUEST['_wpnonce']);
        if (!wp_verify_nonce($nonce, 'sga_print_invoice_' . $payment_id)) {
            wp_die('Error de seguridad.');
        }

        $pago = get_post($payment_id);
        if (!$pago || 'sga_pago' !== $pago->post_type) {
            wp_die('Pago no encontrado.');
        }

        $student_id = get_post_meta($payment_id, '_student_id', true);
        $student_post = get_post($student_id);

        $invoice_data = [
            'invoice_id'          => $payment_id,
            'payment_date'        => get_the_date('Y-m-d H:i:s', $pago),
            'student_name'        => get_post_meta($payment_id, '_student_name', true),
            'student_cedula'      => $student_post ? get_field('cedula', $student_id) : 'N/A',
            'payment_description' => get_post_meta($payment_id, '_payment_description', true),
            'amount'              => get_post_meta($payment_id, '_payment_amount', true),
            'currency'            => get_post_meta($payment_id, '_payment_currency', true),
            'transaction_id'      => get_post_meta($payment_id, '_transaction_id', true),
        ];

        $pdf_data = $this->_generate_payment_invoice_pdf($invoice_data);

        if ($pdf_data && is_array($pdf_data) && !empty($pdf_data['pdf_data'])) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $pdf_data['filename'] . '"');
            echo $pdf_data['pdf_data'];
        } else {
            wp_die('No se pudo generar el PDF. Verifique que la librería Dompdf esté instalada.');
        }
        wp_die();
    }

    public function exportar_excel() {
        $search_term = isset($_GET['search_term']) ? sanitize_text_field(stripslashes($_GET['search_term'])) : '';
        $course_filter = isset($_GET['course_filter']) ? sanitize_text_field(stripslashes($_GET['course_filter'])) : '';
        $filename = 'matriculados-' . date('Y-m-d') . '.xls';

        $logo_id = 5024;
        $logo_src = '';
        if ($logo_id && $logo_path = get_attached_file($logo_id)) {
            $mime_type = get_post_mime_type($logo_id);
            if ($mime_type && file_exists($logo_path)) {
                $logo_data = file_get_contents($logo_path);
                $logo_base64 = base64_encode($logo_data);
                $logo_src = 'data:' . $mime_type . ';base64,' . $logo_base64;
            }
        }

        $students_data = SGA_Utils::_get_filtered_students($search_term, $course_filter);
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="es" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Matriculados</x:Name><x:WorksheetOptions><x:PageSetup><x:Layout x:Orientation="Landscape"/><x:Header x:Margin="0.5"/><x:Footer x:Margin="0.5"/><x:PageMargins x:Bottom="0.75" x:Left="0.7" x:Right="0.7" x:Top="0.75"/></x:PageSetup><x:FitToPage/><x:Print><x:ValidPrinterInfo/><x:PaperSizeIndex>9</x:PaperSizeIndex><x:HorizontalResolution>600</x:HorizontalResolution><x:VerticalResolution>600</x:VerticalResolution></x:Print><x:Zoom>100</x:Zoom><x:Selected/><x:ProtectContents>False</x:ProtectContents><x:ProtectObjects>False</x:ProtectObjects><x:ProtectScenarios>False</x:ProtectScenarios></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
        <style>body{font-family:Arial,sans-serif;font-size:10pt}.header{text-align:center;margin-bottom:20px}.top-banner{background-color:#002060;padding:10px}.header img{max-height:60px}.header h1{font-size:16pt;font-weight:700;margin-top:20px;margin-bottom:5px}.header .subtitle{font-size:9pt;color:#555}table{width:100%;border-collapse:collapse;margin-top:20px;font-size:9pt}th,td{border:1px solid #999;padding:5px;text-align:left}thead th{background-color:#002060;color:#fff;font-weight:700}tbody tr:nth-child(2n){background-color:#ddebf7}</style></head>
        <body>
            <div class="header">
                <div class="top-banner"><?php if (!empty($logo_src)):?><img src="<?php echo esc_url($logo_src);?>" alt="Logo"><?php endif;?></div>
                <h1>Lista de Estudiantes Matriculados</h1>
                <p class="subtitle"><?php echo date('j/n/Y H:i'); ?></p>
                <p class="subtitle">Exportación Generada por Sistema de Gestión Académica SGA by Sam</p>
            </div>
            <table>
                <thead><tr><th>Matrícula</th><th>Nombre</th><th>Cédula</th><th>Email</th><th>Teléfono</th><th>Curso</th><th>Horario</th></tr></thead>
                <tbody>
                    <?php foreach ($students_data as $data) : $estudiante = $data['estudiante']; $curso = $data['curso']; ?>
                        <tr>
                            <td><?php echo esc_html(isset($curso['matricula']) ? $curso['matricula'] : ''); ?></td>
                            <td><?php echo esc_html($estudiante->post_title); ?></td>
                            <td><?php echo esc_html(get_field('cedula', $estudiante->ID)); ?></td>
                            <td><?php echo esc_html(get_field('email', $estudiante->ID)); ?></td>
                            <td><?php echo esc_html(get_field('telefono', $estudiante->ID)); ?></td>
                            <td><?php echo esc_html($curso['nombre_curso']); ?></td>
                            <td><?php echo esc_html(isset($curso['horario']) ? $curso['horario'] : ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </body></html>
        <?php
        $html = ob_get_clean();
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        SGA_Utils::_log_activity('Exportación Excel', "Se exportó una lista de matriculados.");
        echo $html;
        exit();
    }

    public function exportar_moodle_csv() {
        $search_term = isset($_GET['search_term']) ? sanitize_text_field(stripslashes($_GET['search_term'])) : '';
        $course_filter = isset($_GET['course_filter']) ? sanitize_text_field(stripslashes($_GET['course_filter'])) : '';
        $filename = 'moodle-import-' . date('Y-m-d') . '.csv';
        SGA_Utils::_log_activity('Exportación Moodle CSV', "Se exportó una lista para importación en Moodle.");
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        $output = fopen('php://output', 'w');
        fputcsv($output, array('username', 'password', 'firstname', 'lastname', 'email', 'course1'));
        $students_data = SGA_Utils::_get_filtered_students($search_term, $course_filter);
        foreach ($students_data as $data) {
            $estudiante = $data['estudiante'];
            $curso = $data['curso'];
            $email = get_field('email', $estudiante->ID);
            $firstname = get_field('nombre', $estudiante->ID);
            $lastname = get_field('apellido', $estudiante->ID);
            if(empty($firstname) || empty($lastname)) {
                $parts = explode(' ', $estudiante->post_title, 2);
                $firstname = $parts[0];
                $lastname = isset($parts[1]) ? $parts[1] : '';
            }
            $username = sanitize_user(isset($curso['matricula']) ? $curso['matricula'] : $email, true);
            $password = wp_generate_password(12, true, true);
            $course_shortname = sanitize_title($curso['nombre_curso']);
            fputcsv($output, array($username, $password, $firstname, $lastname, $email, $course_shortname));
        }
        fclose($output);
        exit();
    }

    public function exportar_registro_llamadas() {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'export_calls_nonce')) {
            wp_die('Error de seguridad.');
        }
        if (!current_user_can('edit_estudiantes')) {
            wp_die('No tienes permisos.');
        }

        $search_term = isset($_GET['search_term']) ? sanitize_text_field(stripslashes($_GET['search_term'])) : '';
        $agent_filter = isset($_GET['agent_filter']) ? sanitize_text_field(stripslashes($_GET['agent_filter'])) : '';
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field(stripslashes($_GET['status_filter'])) : '';
        
        $filename = 'registro-llamadas-' . date('Y-m-d') . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $args = array(
            'post_type' => 'sga_llamada',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $call_logs_query = new WP_Query($args);
        $filtered_calls = [];

        $status_map = [
            'pendiente' => 'Pendiente',
            'contactado' => 'Contactado',
            'no_contesta' => 'No Contesta',
            'numero_incorrecto' => 'Número Incorrecto',
            'rechazado' => 'Rechazado',
        ];

        if ($call_logs_query->have_posts()) {
            while ($call_logs_query->have_posts()) {
                $call_logs_query->the_post();
                
                $post_id = get_the_ID();
                $author_info = get_userdata(get_the_author_meta('ID'));
                $agent_name = $author_info->display_name;

                // Agent filter
                if (!empty($agent_filter) && $agent_name != $agent_filter) {
                    continue;
                }

                $student_id = get_post_meta($post_id, '_student_id', true);
                $row_index = get_post_meta($post_id, '_row_index', true);
                
                $call_statuses = get_post_meta($student_id, '_sga_call_statuses', true);
                if (!is_array($call_statuses)) { $call_statuses = []; }
                $current_status_key = $call_statuses[$row_index] ?? 'pendiente';
                
                // Status filter
                if (!empty($status_filter) && $current_status_key != $status_filter) {
                    continue;
                }
                
                $student_name = get_post_meta($post_id, '_student_name', true);
                $course_name = get_post_meta($post_id, '_course_name', true);
                
                // Search term filter (for meta fields)
                if (!empty($search_term)) {
                    $searchable_string = strtolower($student_name . ' ' . $course_name . ' ' . get_the_content());
                    if (strpos($searchable_string, strtolower($search_term)) === false) {
                        continue;
                    }
                }

                $status_text = $status_map[$current_status_key] ?? ucfirst($current_status_key);

                $filtered_calls[] = [
                    'agent' => $agent_name,
                    'student' => $student_name,
                    'cedula' => get_field('cedula', $student_id),
                    'email' => get_field('email', $student_id),
                    'telefono' => get_field('telefono', $student_id),
                    'course' => $course_name,
                    'status' => $status_text,
                    'comment' => get_the_content(),
                    'date' => get_the_date('d/m/Y h:i A', $post_id)
                ];
            }
            wp_reset_postdata();
        }

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head><meta charset="UTF-8"></head>
        <body>
            <table>
                <thead>
                    <tr>
                        <th>Agente</th>
                        <th>Estudiante</th>
                        <th>Cédula</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Curso</th>
                        <th>Estado de Llamada</th>
                        <th>Comentario</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($filtered_calls)): ?>
                        <?php foreach ($filtered_calls as $call): ?>
                            <tr>
                                <td><?php echo esc_html($call['agent']); ?></td>
                                <td><?php echo esc_html($call['student']); ?></td>
                                <td><?php echo esc_html($call['cedula']); ?></td>
                                <td><?php echo esc_html($call['email']); ?></td>
                                <td><?php echo esc_html($call['telefono']); ?></td>
                                <td><?php echo esc_html($call['course']); ?></td>
                                <td><?php echo esc_html($call['status']); ?></td>
                                <td><?php echo esc_html($call['comment']); ?></td>
                                <td><?php echo esc_html($call['date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">No se encontraron registros con los filtros aplicados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        SGA_Utils::_log_activity('Exportación Excel', "Se exportó el registro de llamadas.");
        echo ob_get_clean();
        exit();
    }
}

