<?php

if (!defined('ABSPATH')) exit;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Clase SGA_Report_Generator
 *
 * Responsable de la lógica de construcción de HTML y generación de PDF/Facturas
 * utilizando Dompdf. Aisla la lógica de presentación del gestor de reportes.
 */
class SGA_Report_Generator {
    
    /**
     * Genera el Expediente del estudiante en formato PDF.
     * @param int $student_id ID del post del estudiante.
     * @return array|false Datos del PDF o false si Dompdf no existe.
     */
    public function generate_student_profile_pdf($student_id) {
        if (!class_exists('Dompdf\Dompdf')) {
            SGA_Utils::_log_activity('Error de Reporte', 'La librería Dompdf no está instalada o no se puede encontrar.');
            return false;
        }

        $student_post = get_post($student_id);
        if (!$student_post || 'estudiante' !== $student_post->post_type) return false;

        // 1. Obtener datos del estudiante
        $nombre_completo = $student_post->post_title;
        $cedula = get_field('cedula', $student_id);
        $email = get_field('email', $student_id);
        $telefono = get_field('telefono', $student_id);
        $direccion = get_field('direccion', $student_id);
        $cursos = get_field('cursos_inscritos', $student_id);

        $logo_id = 5024; // ID de ejemplo para el logo
        $logo_src = $this->_get_base64_logo($logo_id);
        
        $report_title = 'Expediente Estudiantil: ' . $nombre_completo;

        ob_start();
        ?>
        <!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
        <style>
            body{font-family:Arial,sans-serif;font-size:12pt;color:#333;margin:0;padding:0}.header{text-align:center;margin-bottom:30px;border-bottom:3px solid #141f53;padding-bottom:15px}.header img{max-height:80px;margin-bottom:10px}h1{font-size:24pt;color:#141f53;margin:0}.subtitle{font-size:10pt;color:#555;margin-top:5px}.section-title{font-size:16pt;color:#4f46e5;border-bottom:2px solid #e0e0e0;padding-bottom:5px;margin-top:30px;margin-bottom:15px}.data-grid{display:table;width:100%;border-collapse:collapse;margin-bottom:20px}.data-row{display:table-row;}.data-label{display:table-cell;font-weight:700;padding:8px 0;width:30%;vertical-align:top;font-size:11pt}.data-value{display:table-cell;padding:8px 0;width:70%;vertical-align:top;font-size:11pt}.curso-table{width:100%;border-collapse:collapse;margin-top:10px;font-size:10pt}th,td{border:1px solid #ccc;padding:10px;text-align:left}thead th{background-color:#141f53;color:#fff;font-weight:700}tbody tr:nth-child(even){background-color:#f8f9fa}.pill{display:inline-block;padding:4px 10px;font-size:10pt;font-weight:700;border-radius:12px;color:#fff;}.pill-inscrito{background-color:#f59e0b}.pill-matriculado{background-color:#10b981}.pill-completado{background-color:#3b82f6}.pill-cancelado{background-color:#ef4444}
        </style>
        </head><body>
            <div class="invoice-box">
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
                            $estado_class = 'pill-inscrito';
                            switch ($curso['estado']) {
                                case 'Matriculado': $estado_class = 'pill-matriculado'; break;
                                case 'Completado': $estado_class = 'pill-completado'; break;
                                case 'Cancelado': $estado_class = 'pill-cancelado'; break;
                            }
                        ?>
                            <tr>
                                <td><?php echo esc_html($curso['nombre_curso']); ?></td>
                                <td><?php echo esc_html($curso['horario']); ?></td>
                                <td><?php echo esc_html($curso['fecha_inscripcion']); ?></td>
                                <td><?php echo esc_html($curso['matricula'] ?? 'N/A'); ?></td>
                                <td><span class="pill <?php echo $estado_class; ?>"><?php echo esc_html($curso['estado']); ?></span></td>
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
        $html_content = ob_get_clean();

        return $this->_generate_pdf($html_content, $report_title, 'expediente-' . sanitize_title($nombre_completo) . '-' . date('Y-m-d') . '.pdf');
    }

    /**
     * Genera un reporte general basado en el tipo y los argumentos.
     * @param string $type Tipo de reporte (matriculados, pendientes, etc.).
     * @param array $args Argumentos de filtrado.
     * @return array|false Datos del PDF o false si Dompdf no existe.
     */
    public function generate_general_report($type, $args = []) {
        if (!class_exists('Dompdf\Dompdf')) {
            SGA_Utils::_log_activity('Error de Reporte', 'La librería Dompdf no está instalada o no se puede encontrar.');
            return false;
        }

        $report_title = '';
        $headers = [];
        $rows = [];

        $data_processor = new SGA_Report_Data_Processor();
        $report_data = $data_processor->get_report_data($type, $args);
        $report_title = $report_data['title'];
        $headers = $report_data['headers'];
        $rows = $report_data['rows'];

        $logo_id = 5024;
        $logo_src = $this->_get_base64_logo($logo_id);

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

        return $this->_generate_pdf($html_content, $report_title, sanitize_title($report_title) . '-' . date('Y-m-d') . '.pdf', $type === 'log' ? 'landscape' : 'portrait');
    }

    /**
     * Genera la factura/recibo de pago en PDF.
     */
    public function generate_payment_invoice_pdf($invoice_data) {
        if (!class_exists('Dompdf\Dompdf')) {
            SGA_Utils::_log_activity('Error de Factura', 'La librería Dompdf no está instalada.');
            return false;
        }
        $logo_id = 5024;
        $logo_src = $this->_get_base64_logo($logo_id);

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

        return $this->_generate_pdf($html_content, $invoice_title, 'recibo-pago-' . $invoice_data['invoice_id'] . '.pdf');
    }

    /**
     * Helper: Obtiene el logo como base64.
     * @param int $logo_id ID del post del adjunto.
     * @return string Data URI del logo o cadena vacía.
     */
    private function _get_base64_logo($logo_id) {
        $logo_src = '';
        if ($logo_id && $logo_path = get_attached_file($logo_id)) {
            $mime_type = get_post_mime_type($logo_id);
            if ($mime_type && file_exists($logo_path)) {
                $logo_data = file_get_contents($logo_path);
                $logo_base64 = base64_encode($logo_data);
                $logo_src = 'data:' . $mime_type . ';base64,' . $logo_base64;
            }
        }
        return $logo_src;
    }

    /**
     * Helper: Genera el PDF a partir del HTML usando Dompdf.
     * @param string $html_content El HTML a renderizar.
     * @param string $report_title Título del reporte.
     * @param string $filename Nombre del archivo.
     * @param string $orientation Orientación del papel.
     * @return array|false Datos del PDF o false si falla.
     */
    private function _generate_pdf($html_content, $report_title, $filename, $orientation = 'portrait') {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html_content);
        
        $paper = 'A4';
        if ($orientation === 'landscape') {
            $dompdf->setPaper($paper, 'landscape');
        } else {
            $dompdf->setPaper($paper, 'portrait');
        }
        
        $dompdf->render();
        $pdf_output = $dompdf->output();

        return [
            'pdf_data' => $pdf_output,
            'title' => $report_title,
            'filename' => $filename
        ];
    }
}
