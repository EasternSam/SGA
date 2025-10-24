<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_Panel_Views_Part3
 *
 * Contiene las vistas de cursos, reportes y los métodos de estilos/scripts del panel.
 * Extiende SGA_Panel_Views_Part2 para heredar los helpers y las vistas anteriores.
 */
class SGA_Panel_Views_Part3 extends SGA_Panel_Views_Part2 {

    // --- VISTA DE LISTA DE CURSOS ---

    public function render_view_lista_cursos() {
        $cursos_activos = get_posts(array(
            'post_type' => 'curso',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => array('publish', 'private')
        ));
        $escuelas = get_terms(array('taxonomy' => 'category', 'hide_empty' => false));
        ?>
        <a href="#" data-view="principal" class="back-link panel-nav-link">&larr; Volver al Panel Principal</a>
        <h1 class="panel-title">Lista de Cursos Activos</h1>
    
        <div class="filtros-tabla">
            <input type="text" id="buscador-cursos" placeholder="Buscar por nombre de curso...">
            <select id="filtro-escuela-cursos">
                <option value="">Todas las Escuelas</option>
                <?php if ($escuelas && !is_wp_error($escuelas)): ?>
                    <?php foreach ($escuelas as $escuela) : ?>
                        <option value="<?php echo esc_attr($escuela->slug); ?>"><?php echo esc_html($escuela->name); ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <select id="filtro-visibilidad-cursos">
                <option value="">Toda la Visibilidad</option>
                <option value="publish">Público</option>
                <option value="private">Privado</option>
            </select>
            <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico', 'gestor_de_cursos'])) : ?>
            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=curso')); ?>" target="_blank" class="button button-primary">Nuevo Curso</a>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=curso')); ?>" target="_blank" class="button button-secondary">Editar Cursos</a>
            <?php endif; ?>
            <div class="view-switcher">
                <button class="view-btn active" data-view="grid" title="Vista de Tarjetas">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                </button>
                <button class="view-btn" data-view="list" title="Vista de Lista">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                </button>
            </div>
        </div>
    
        <div class="cursos-grid" style="margin-top: 25px;">
            <?php if ($cursos_activos && function_exists('get_field')) : ?>
                <?php foreach ($cursos_activos as $curso) : 
                    $escuelas_terms = get_the_terms($curso->ID, 'category');
                    $escuela_display = 'N/A';
                    $escuela_slugs = '';
                    if ($escuelas_terms && !is_wp_error($escuelas_terms)) {
                        $escuela_names = array_map(function($term) { return $term->name; }, $escuelas_terms);
                        $escuela_slugs_array = array_map(function($term) { return $term->slug; }, $escuelas_terms);
                        $escuela_display = implode(', ', $escuela_names);
                        $escuela_slugs = implode(' ', $escuela_slugs_array);
                    }
                    $post_status = get_post_status($curso->ID);
                ?>
                <div class="curso-card" 
                     data-search-term="<?php echo esc_attr(strtolower($curso->post_title . ' ' . $escuela_display)); ?>"
                     data-escuela="<?php echo esc_attr($escuela_slugs); ?>"
                     data-visibilidad="<?php echo esc_attr($post_status); ?>">
                    <div class="curso-card-header">
                        <div class="curso-card-title-wrapper">
                            <h3><?php echo esc_html($curso->post_title); ?></h3>
                            <span class="ga-pill <?php echo $post_status === 'publish' ? 'ga-pill-publico' : 'ga-pill-privado'; ?>"><?php echo $post_status === 'publish' ? 'Público' : 'Privado'; ?></span>
                        </div>
                        <div class="curso-card-actions">
                            <a href="#" class="button button-secondary ver-matriculados-btn" data-curso-nombre="<?php echo esc_attr($curso->post_title); ?>">Matriculados</a>
                            <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico', 'gestor_de_cursos'])) : ?>
                            <a href="<?php echo get_edit_post_link($curso->ID); ?>" class="button" target="_blank">Editar</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="curso-card-body">
                        <div class="curso-details-grid">
                            <div><span>Precio:</span> <?php echo esc_html(get_field('precio_del_curso', $curso->ID)); ?></div>
                            <div><span>Mensualidad:</span> <?php echo esc_html(get_field('mensualidad', $curso->ID)); ?></div>
                            <div><span>Duración:</span> <?php echo esc_html(get_field('duracion_del_curso', $curso->ID)); ?></div>
                            <div><span>Escuela:</span> <?php echo esc_html($escuela_display); ?></div>
                        </div>
                        <div class="horarios-section">
                            <h4>Horarios Disponibles</h4>
                            <?php 
                            $horarios_repeater = get_field('horarios_del_curso', $curso->ID);
                            if ($horarios_repeater) : ?>
                                <ul class="horarios-list">
                                <?php foreach ($horarios_repeater as $horario) : 
                                    $total_cupos = !empty($horario['numero_de_cupos']) ? intval($horario['numero_de_cupos']) : 0;
                                    $cupos_ocupados = 0;
                                    if ($total_cupos > 0) {
                                        $cupos_ocupados = SGA_Utils::_get_cupos_ocupados($curso->post_title, $horario['dias_de_la_semana'] . ' ' . $horario['hora']);
                                    }
                                    $modalidad = !empty($horario['modalidad']) ? $horario['modalidad'] : 'N/A';
                                    $modalidad_class = 'ga-pill-default';
                                    switch (strtolower($modalidad)) {
                                        case 'presencial': $modalidad_class = 'ga-pill-presencial'; break;
                                        case 'virtual': $modalidad_class = 'ga-pill-virtual'; break;
                                        case 'híbrido': case 'hibrido': $modalidad_class = 'ga-pill-hibrido'; break;
                                    }
                                ?>
                                    <li>
                                        <div class="horario-info">
                                            <span class="horario-dia-hora"><?php echo esc_html($horario['dias_de_la_semana'] . ' ' . $horario['hora']); ?></span>
                                            <span class="ga-pill <?php echo $modalidad_class; ?>"><?php echo esc_html($modalidad); ?></span>
                                        </div>
                                        <div class="horario-cupos">
                                            <span>Cupos: </span>
                                            <strong><?php echo ($total_cupos > 0) ? "{$cupos_ocupados} / {$total_cupos}" : 'Ilimitados'; ?></strong>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p>No hay horarios definidos para este curso.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else : ?>
                <p class="no-results">No se encontraron cursos.</p>
            <?php endif; ?>
        </div>

        <div class="cursos-list-view" style="display: none; margin-top: 25px;">
            <div class="tabla-wrapper">
                <table class="wp-list-table widefat striped" id="tabla-cursos-lista">
                    <thead>
                        <tr>
                            <th>Curso</th>
                            <th>Detalles</th>
                            <th>Horarios</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($cursos_activos && function_exists('get_field')) : ?>
                        <?php foreach ($cursos_activos as $curso) : 
                            $escuelas_terms = get_the_terms($curso->ID, 'category');
                            $escuela_display = 'N/A';
                            $escuela_slugs = '';
                            if ($escuelas_terms && !is_wp_error($escuelas_terms)) {
                                $escuela_names = array_map(function($term) { return $term->name; }, $escuelas_terms);
                                $escuela_slugs_array = array_map(function($term) { return $term->slug; }, $escuelas_terms);
                                $escuela_display = implode(', ', $escuela_names);
                                $escuela_slugs = implode(' ', $escuela_slugs_array);
                            }
                            $post_status = get_post_status($curso->ID);
                        ?>
                        <tr data-search-term="<?php echo esc_attr(strtolower($curso->post_title . ' ' . $escuela_display)); ?>"
                            data-escuela="<?php echo esc_attr($escuela_slugs); ?>"
                            data-visibilidad="<?php echo esc_attr($post_status); ?>">
                            <td>
                                <strong><?php echo esc_html($curso->post_title); ?></strong><br>
                                <span class="ga-pill <?php echo $post_status === 'publish' ? 'ga-pill-publico' : 'ga-pill-privado'; ?>"><?php echo $post_status === 'publish' ? 'Público' : 'Privado'; ?></span>
                            </td>
                            <td>
                                <ul class="curso-details-list">
                                    <li><span>Precio:</span> <?php echo esc_html(get_field('precio_del_curso', $curso->ID)); ?></li>
                                    <li><span>Mensualidad:</span> <?php echo esc_html(get_field('mensualidad', $curso->ID)); ?></li>
                                    <li><span>Duración:</span> <?php echo esc_html(get_field('duracion_del_curso', $curso->ID)); ?></li>
                                    <li><span>Escuela:</span> <?php echo esc_html($escuela_display); ?></li>
                                </ul>
                            </td>
                            <td>
                                <?php 
                                $horarios_repeater = get_field('horarios_del_curso', $curso->ID);
                                if ($horarios_repeater) : ?>
                                    <ul class="horarios-list-inline">
                                    <?php foreach ($horarios_repeater as $horario) : 
                                        $total_cupos = !empty($horario['numero_de_cupos']) ? intval($horario['numero_de_cupos']) : 0;
                                        $cupos_ocupados = 0;
                                        if ($total_cupos > 0) {
                                            $cupos_ocupados = SGA_Utils::_get_cupos_ocupados($curso->post_title, $horario['dias_de_la_semana'] . ' ' . $horario['hora']);
                                        }
                                         $modalidad = !empty($horario['modalidad']) ? $horario['modalidad'] : 'N/A';
                                        $modalidad_class = 'ga-pill-default';
                                        switch (strtolower($modalidad)) {
                                            case 'presencial': $modalidad_class = 'ga-pill-presencial'; break;
                                            case 'virtual': $modalidad_class = 'ga-pill-virtual'; break;
                                            case 'híbrido': case 'hibrido': $modalidad_class = 'ga-pill-hibrido'; break;
                                        }
                                    ?>
                                        <li>
                                            <div class="horario-info">
                                                <span class="horario-dia-hora"><?php echo esc_html($horario['dias_de_la_semana'] . ' ' . $horario['hora']); ?></span>
                                                <span class="ga-pill <?php echo $modalidad_class; ?>"><?php echo esc_html($modalidad); ?></span>
                                            </div>
                                            <div class="horario-cupos">
                                                <span>Cupos: </span>
                                                <strong><?php echo ($total_cupos > 0) ? "{$cupos_ocupados} / {$total_cupos}" : 'Ilimitados'; ?></strong>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span>N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <a href="#" class="button button-secondary ver-matriculados-btn" data-curso-nombre="<?php echo esc_attr($curso->post_title); ?>">Matriculados</a>
                                <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico', 'gestor_de_cursos'])) : ?>
                                <a href="<?php echo get_edit_post_link($curso->ID); ?>" class="button" target="_blank">Editar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="no-results"><td colspan="4">No se encontraron cursos.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    // --- VISTA DE REGISTRO DE LLAMADAS ---

    public function render_view_registro_llamadas() {
        $all_call_authors_query = new WP_User_Query(array(
            'who' => 'authors',
            'has_published_posts' => array('sga_llamada'),
            'fields' => 'all_with_meta',
        ));
        $authors = $all_call_authors_query->get_results();
        ?>
        <a href="#" data-view="matriculacion" class="back-link panel-nav-link">&larr; Volver a Matriculación</a>
        <h1 class="panel-title">Registro de Llamadas</h1>
        <div class="filtros-tabla">
            <input type="text" id="buscador-registro-llamadas" placeholder="Buscar por estudiante o curso...">
            <select id="filtro-agente-llamadas">
                <option value="">Todos los agentes</option>
                <?php foreach ($authors as $author) : ?>
                    <option value="<?php echo esc_attr($author->display_name); ?>"><?php echo esc_html($author->display_name); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filtro-estado-llamadas-registro">
                <option value="">Todos los estados</option>
                <option value="pendiente">Pendiente</option>
                <option value="contactado">Contactado</option>
                <option value="no_contesta">No Contesta</option>
                <option value="numero_incorrecto">Número Incorrecto</option>
                <option value="rechazado">Rechazado</option>
            </select>
            <div class="export-actions-wrapper">
                <button id="exportar-llamadas-btn" class="button">Exportar a Excel</button>
            </div>
        </div>

        <div id="sga-call-log-accordion">
            <?php
            $args = array(
                'post_type' => 'sga_llamada',
                'posts_per_page' => -1,
                'orderby' => 'author',
                'order' => 'ASC',
            );
            $call_logs_query = new WP_Query($args);
            $calls_by_user = [];

            if ($call_logs_query->have_posts()) {
                while ($call_logs_query->have_posts()) {
                    $call_logs_query->the_post();
                    $author_id = get_the_author_meta('ID');
                    if (!isset($calls_by_user[$author_id])) {
                        $calls_by_user[$author_id] = [
                            'user_info' => get_userdata($author_id),
                            'calls' => []
                        ];
                    }
                    $calls_by_user[$author_id]['calls'][] = get_post();
                }
                wp_reset_postdata();
            }

            if (!empty($calls_by_user)):
                foreach ($calls_by_user as $user_id => $data): ?>
                    <div class="user-log-section" data-agent="<?php echo esc_attr($data['user_info']->display_name); ?>">
                        <h2 class="user-log-title">
                            <button aria-expanded="false">
                                <span class="user-name"><?php echo esc_html($data['user_info']->display_name); ?></span>
                                <span class="call-count"><?php echo count($data['calls']); ?> llamadas</span>
                                <span class="toggle-icon" aria-hidden="true"></span>
                            </button>
                        </h2>
                        <div class="user-log-content">
                             <div class="tabla-wrapper">
                                <table class="wp-list-table widefat striped">
                                    <thead>
                                        <tr>
                                            <th>Estudiante</th>
                                            <th>Cédula</th>
                                            <th>Contacto</th>
                                            <th>Curso</th>
                                            <th>Estado</th>
                                            <th>Comentario</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $status_map = [
                                            'pendiente' => ['text' => 'Pendiente', 'class' => 'ga-pill-llamada-pendiente'],
                                            'contactado' => ['text' => 'Contactado', 'class' => 'ga-pill-llamada-contactado'],
                                            'no_contesta' => ['text' => 'No Contesta', 'class' => 'ga-pill-llamada-no_contesta'],
                                            'numero_incorrecto' => ['text' => 'Número Incorrecto', 'class' => 'ga-pill-llamada-numero_incorrecto'],
                                            'rechazado' => ['text' => 'Rechazado', 'class' => 'ga-pill-llamada-rechazado'],
                                        ];
                                        foreach (array_reverse($data['calls']) as $call):
                                            $student_id = get_post_meta($call->ID, '_student_id', true);
                                            $row_index = get_post_meta($call->ID, '_row_index', true);
                                            $call_statuses = get_post_meta($student_id, '_sga_call_statuses', true);
                                            if (!is_array($call_statuses)) { $call_statuses = []; }
                                            $current_status_key = $call_statuses[$row_index] ?? 'pendiente';
                                            $status_details = $status_map[$current_status_key] ?? ['text' => ucfirst($current_status_key), 'class' => ''];
                                        ?>
                                            <tr data-status="<?php echo esc_attr($current_status_key); ?>">
                                                <td><?php echo esc_html(get_post_meta($call->ID, '_student_name', true)); ?></td>
                                                <td><?php echo esc_html(get_field('cedula', $student_id)); ?></td>
                                                <td>
                                                    <small><strong>Email:</strong> <?php echo esc_html(get_field('email', $student_id)); ?></small><br>
                                                    <small><strong>Tel:</strong> <?php echo esc_html(get_field('telefono', $student_id)); ?></small>
                                                </td>
                                                <td><?php echo esc_html(get_post_meta($call->ID, '_course_name', true)); ?></td>
                                                <td><span class="ga-pill <?php echo esc_attr($status_details['class']); ?>"><?php echo esc_html($status_details['text']); ?></span></td>
                                                <td><?php echo esc_html($call->post_content); ?></td>
                                                <td><?php echo esc_html(get_the_date('d/m/Y h:i A', $call)); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No se han registrado llamadas todavía.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    // --- VISTA DE COMUNICACIÓN ---

    public function render_view_comunicacion() {
        $cursos = get_posts(array('post_type' => 'curso', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        $all_students = get_posts(array('post_type' => 'estudiante', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        ?>
        <a href="#" data-view="principal" class="back-link panel-nav-link">&larr; Volver al Panel Principal</a>
        <h1 class="panel-title">Comunicación y Correo Masivo</h1>
        <div id="sga-comunicacion-wrapper">
            <div class="sga-comunicacion-form">
                <div class="sga-form-group">
                    <label for="sga-email-recipient-group"><strong>Enviar a:</strong></label>
                    <select id="sga-email-recipient-group">
                        <option value="todos">Todos los Estudiantes</option>
                        <option value="matriculados">Estudiantes Matriculados</option>
                        <option value="pendientes">Estudiantes Pendientes de Aprobación</option>
                        <option value="por_curso">Estudiantes por Curso Específico</option>
                        <option value="especificos">Estudiantes Específicos (Selección Manual)</option>
                        <option value="manual" style="display: none;">Selección Manual</option>
                    </select>
                    <input type="hidden" id="sga-manual-recipient-ids" name="manual_ids">
                </div>
                <div class="sga-form-group" id="sga-curso-selector-group" style="display: none;">
                    <label for="sga-email-curso-select"><strong>Seleccionar Curso:</strong></label>
                    <select id="sga-email-curso-select">
                        <?php foreach ($cursos as $curso): ?>
                            <option value="<?php echo esc_attr($curso->post_title); ?>"><?php echo esc_html($curso->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sga-form-group" id="sga-estudiantes-especificos-group" style="display: none;">
                    <label for="sga-estudiantes-search"><strong>Seleccionar Estudiantes:</strong></label>
                    <input type="text" id="sga-estudiantes-search" placeholder="Buscar estudiante por nombre o cédula...">
                    <div id="sga-estudiantes-checkbox-list">
                        <?php foreach ($all_students as $student):
                            $cedula = get_field('cedula', $student->ID);
                        ?>
                            <div class="sga-student-item" data-search-term="<?php echo esc_attr(strtolower($student->post_title . ' ' . $cedula)); ?>">
                                <label>
                                    <input type="checkbox" class="sga-specific-student-checkbox" value="<?php echo esc_attr($student->ID); ?>">
                                    <?php echo esc_html($student->post_title); ?> (Cédula: <?php echo esc_html($cedula); ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="sga-form-group">
                    <label for="sga-email-subject"><strong>Asunto:</strong></label>
                    <input type="text" id="sga-email-subject" placeholder="Asunto del correo">
                </div>
                <div class="sga-form-group">
                    <label><strong>Mensaje:</strong></label>
                    <div class="sga-dynamic-tags-info">
                        <p><strong>Etiquetas dinámicas disponibles:</strong></p>
                        <ul>
                            <li><code>[nombre_estudiante]</code> - Nombre completo del estudiante.</li>
                            <li><code>[cedula]</code> - Cédula del estudiante.</li>
                            <li><code>[nombre_curso]</code> - Nombre del curso (solo para envíos "por curso").</li>
                            <li><code>[matricula]</code> - Matrícula del estudiante en el curso (solo para envíos "por curso").</li>
                        </ul>
                    </div>
                    <?php 
                    $default_editor_content = '<p>Hola [nombre_estudiante],</p><p>Escribe aquí tu mensaje...</p>';
                    wp_editor($default_editor_content, 'sga-email-body', array('textarea_name' => 'sga-email-body', 'media_buttons' => false, 'textarea_rows' => 10)); 
                    ?>
                </div>
                <div class="sga-form-group">
                    <button id="sga-send-bulk-email-btn" class="button button-primary button-large">Enviar Correo</button>
                    <span class="spinner"></span>
                </div>
            </div>
            <div id="sga-email-status"></div>
        </div>
        <?php
    }


    // --- VISTA DE REPORTES ---

    public function render_view_reportes() {
        $options = get_option('sga_report_options', []);
        $cursos = get_posts(array('post_type' => 'curso', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        $agentes = SGA_Utils::_get_sga_agents();
        $is_agent = $this->sga_user_has_role(['agente']);
        ?>
        <a href="#" data-view="principal" class="back-link panel-nav-link">&larr; Volver al Panel Principal</a>
        <h1 class="panel-title">Central de Reportes</h1>

        <div class="report-grid">
            <div class="report-main-content">
                <div class="sga-card">
                    <h3>Generador de Reportes</h3>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="sga_generate_manual_report">
                        <?php wp_nonce_field('sga_manual_report_nonce', '_wpnonce_manual_report'); ?>
                        <div class="report-form-grid">
                            <div class="sga-form-group">
                                <label for="report_type">Tipo de Reporte</label>
                                <select name="report_type" id="report_type">
                                    <?php if ($is_agent) : ?>
                                        <option value="historial_llamadas">Historial de Llamadas</option>
                                    <?php else : ?>
                                        <option value="matriculados">Estudiantes Matriculados</option>
                                        <option value="pendientes">Inscripciones Pendientes</option>
                                        <option value="cursos">Lista de Cursos Activos</option>
                                        <option value="payment_history">Historial de Pagos</option>
                                        <option value="historial_llamadas">Historial de Llamadas</option>
                                        <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico'])) : ?>
                                        <option value="log">Registro de Actividad</option>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                             <div class="sga-form-group" id="report-agente-filter-container" style="display:none;">
                                <label for="report_agente_filtro">Filtrar por Agente</label>
                                <select name="agente_filtro" id="report_agente_filtro" <?php if ($is_agent) echo 'disabled'; ?>>
                                    <option value="">Todos los agentes</option>
                                    <?php foreach ($agentes as $agente) : ?>
                                        <option value="<?php echo esc_attr($agente->ID); ?>" <?php selected(get_current_user_id(), $agente->ID, $is_agent); ?>><?php echo esc_html($agente->display_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="sga-form-group" id="report-curso-filter-container" style="display:none;">
                                <label for="report_curso_filtro">Filtrar por Curso</label>
                                <select name="curso_filtro" id="report_curso_filtro">
                                    <option value="">Todos los cursos</option>
                                    <?php foreach ($cursos as $curso) : ?>
                                        <option value="<?php echo esc_attr($curso->post_title); ?>"><?php echo esc_html($curso->post_title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="sga-form-group">
                                <label for="report_date_from">Desde</label>
                                <input type="date" name="date_from" id="report_date_from">
                            </div>
                            <div class="sga-form-group">
                                <label for="report_date_to">Hasta</label>
                                <input type="date" name="date_to" id="report_date_to">
                            </div>
                        </div>
                        <div class="report-actions">
                            <?php if (!$is_agent) : ?>
                            <button type="submit" name="report_action" value="email" class="button button-secondary">Enviar por Correo</button>
                            <?php endif; ?>
                            <button type="submit" name="report_action" value="download" class="button button-primary">Generar PDF</button>
                        </div>
                    </form>
                </div>
                <div class="sga-card">
                    <h3>Visualización de Datos</h3>
                    <div class="chart-container">
                        <canvas id="inscriptionsChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="report-sidebar">
                 <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico'])) : ?>
                <div class="sga-card">
                    <h3>Reportes Programados</h3>
                    <form id="sga-scheduled-reports-form">
                        <div class="sga-form-group">
                            <label for="recipient_email">Correo Receptor</label>
                            <input type="email" id="recipient_email" name="recipient_email" value="<?php echo esc_attr($options['recipient_email']); ?>" class="regular-text" />
                        </div>
                        <div class="sga-form-group">
                            <label class="checkbox-label"><input type="checkbox" id="enable_weekly" name="enable_weekly" value="1" <?php checked(1, $options['enable_weekly'], true); ?> /> Activar reporte semanal</label>
                            <p class="description">Se enviará cada lunes a las 2:00 AM.</p>
                        </div>
                        <div class="sga-form-group">
                            <label class="checkbox-label"><input type="checkbox" id="enable_monthly" name="enable_monthly" value="1" <?php checked(1, $options['enable_monthly'], true); ?> /> Activar reporte mensual</label>
                             <p class="description">Se enviará el primer día del mes a las 2:00 AM.</p>
                        </div>
                        <button type="submit" class="button button-primary" style="width: 100%;">Guardar Ajustes</button>
                        <div id="scheduled-report-status" style="margin-top: 10px;"></div>
                    </form>
                </div>
                <?php endif; ?>
                <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico'])) : ?>
                <a href="#" data-view="log" class="panel-card panel-nav-link">
                    <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22h6a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v5"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M5 17a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/><path d="M5 17v-2.5"/><path d="M5 12V2"/></svg></div>
                    <h2>Registro de Actividad</h2>
                    <p>Consultar el log del sistema</p>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // --- VISTA DE REGISTRO DE ACTIVIDAD (LOG) ---

    public function render_view_log() {
        global $wpdb;
        $log_entries = get_posts(array('post_type' => 'gestion_log', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC'));
        $user_ids = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_log_user_id'");
        $users = array();
        foreach ($user_ids as $user_id) {
            if ($user_id == 0) {
                $users[0] = 'Sistema';
            } else {
                $user_data = get_userdata($user_id);
                if ($user_data) $users[$user_id] = $user_data->display_name;
            }
        }
        ?>
        <a href="#" data-view="reportes" class="back-link panel-nav-link">&larr; Volver a Reportes</a>
        <h1 class="panel-title">Registro de Actividad del Sistema</h1>
        <div class="filtros-tabla">
            <input type="text" id="buscador-log" placeholder="Buscar en el registro...">
            <select id="filtro-usuario-log">
                <option value="">Todos los usuarios</option>
                <?php foreach ($users as $id => $name) : ?>
                    <option value="<?php echo esc_attr($name); ?>"><?php echo esc_html($name); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" id="filtro-fecha-inicio">
            <input type="date" id="filtro-fecha-fin">
        </div>
        <div class="tabla-wrapper">
            <table class="wp-list-table widefat striped" id="tabla-log">
                <thead><tr><th>Acción</th><th>Detalles</th><th>Usuario</th><th>Fecha</th></tr></thead>
                <tbody>
                    <?php if ($log_entries) : ?>
                        <?php
                        foreach ($log_entries as $entry) :
                            $user_id = get_post_meta($entry->ID, '_log_user_id', true);
                            $user_name = ($user_id != 0 && isset($users[$user_id])) ? $users[$user_id] : 'Sistema';
                            $entry_date = get_the_date('Y-m-d', $entry);
                            ?>
                            <tr data-usuario="<?php echo esc_attr($user_name); ?>" data-fecha="<?php echo esc_attr($entry_date); ?>">
                                <td><?php echo esc_html($entry->post_title); ?></td>
                                <td><?php echo wp_kses_post($entry->post_content); ?></td>
                                <td><?php echo esc_html($user_name); ?></td>
                                <td><?php echo get_the_date('Y-m-d H:i:s', $entry); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr class="no-results"><td colspan="4">No hay registros de actividad.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
