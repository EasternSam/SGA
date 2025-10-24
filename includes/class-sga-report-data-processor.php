<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_Report_Data_Processor
 *
 * Se encarga de consultar y procesar los datos de la base de datos
 * para la generación de reportes específicos.
 */
class SGA_Report_Data_Processor {

    /**
     * Obtiene los datos, encabezados y título para un tipo de reporte específico.
     * @param string $type Tipo de reporte (matriculados, pendientes, etc.).
     * @param array $args Argumentos de filtrado.
     * @return array Datos del reporte formateados.
     */
    public function get_report_data($type, $args = []) {
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

        return [
            'title' => $report_title,
            'headers' => $headers,
            'rows' => $rows
        ];
    }
}
