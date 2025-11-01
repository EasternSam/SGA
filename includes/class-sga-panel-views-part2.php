<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_Panel_Views_Part2
 *
 * Contiene las vistas de gestión de matrículas y listados de estudiantes.
 * Extiende SGA_Panel_Views_Part1 para heredar el helper de roles y métodos principales.
 */
class SGA_Panel_Views_Part2 extends SGA_Panel_Views_Part1 {

    // --- VISTA DE ENVÍO A MATRICULACIÓN / SEGUIMIENTO ---

    public function render_view_enviar_a_matriculacion() {
        // *** INICIO OPTIMIZACIÓN ***
        // Ya no cargamos todos los estudiantes. Solo cargamos lo necesario para los filtros.
        // *** FIN OPTIMIZACIÓN ***
        
        $cursos_disponibles = get_posts(array('post_type' => 'curso', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        $can_approve = $this->sga_user_has_role(['administrator', 'gestor_academico']);
        $agents = SGA_Utils::_get_sga_agents();
        
        ?>
        <a href="#" data-view="matriculacion" class="back-link panel-nav-link">&larr; Volver a Matriculación</a>
        <h1 class="panel-title">
            <?php echo $can_approve ? 'Aprobar Inscripciones Pendientes' : 'Seguimiento de Inscripciones'; ?>
        </h1>

        <div class="filtros-tabla">
            <?php if ($can_approve): ?>
            <div class="bulk-actions-wrapper">
                <button id="sga-distribute-btn" class="button button-primary">Repartir Inscripciones</button>
            </div>
            <div class="bulk-actions-wrapper">
                <select name="bulk-action" id="bulk-action-select">
                    <option value="-1">Acciones en lote</option>
                    <option value="aprobar">Aprobar seleccionados</option>
                </select>
                <button id="apply-bulk-action" class="button">Aplicar</button>
            </div>
            <?php endif; ?>
            <input type="text" id="buscador-estudiantes-pendientes" placeholder="Buscar por nombre, cédula o curso...">
            <select id="filtro-curso-pendientes">
                <option value="">Todos los cursos</option>
                <?php foreach ($cursos_disponibles as $curso_filtro) : ?>
                    <option value="<?php echo esc_attr($curso_filtro->post_title); ?>"><?php echo esc_html($curso_filtro->post_title); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filtro-estado-llamada">
                <option value="">Todos los Estados de Llamada</option>
                <option value="pendiente">Pendiente</option>
                <option value="contactado">Contactado</option>
                <option value="no_contesta">No Contesta</option>
                <option value="numero_incorrecto">Número Incorrecto</option>
                <option value="rechazado">Rechazado</option>
            </select>
             <select id="filtro-agente-asignado">
                <option value="">Todos los Agentes</option>
                <option value="unassigned">Sin Asignar</option>
                <?php foreach ($agents as $agent) : ?>
                    <option value="<?php echo esc_attr($agent->ID); ?>"><?php echo esc_html($agent->display_name); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico'])): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sga_dashboard')); ?>" class="button button-secondary" target="_blank">Gestión Avanzada</a>
            <?php endif; ?>
        </div>
        
        <!-- --- NUEVO WRAPPER Y TOGGLE SWITCH --- -->
        <div class="sga-table-switcher-wrapper">
            <div class="sga-toggle-switch-container">
                <!-- [MODIFICADO] Los contadores se cargarán vía AJAX -->
                <label for="sga-table-toggle" class="sga-toggle-label sga-toggle-label-left active" data-target="nuevas">
                    Nuevas (0)
                </label>
                <input type="checkbox" id="sga-table-toggle" class="sga-toggle-input">
                <label for="sga-table-toggle" class="sga-toggle-label sga-toggle-label-right" data-target="seguimiento">
                    Seguimiento (0)
                </label>
                <div class="sga-toggle-slider"></div>
            </div>
        </div>
        
        <!-- [MODIFICADO] La tabla y la paginación ahora tienen contenedores de carga -->
        <div id="sga-table-section-nuevas" class="sga-table-section active">
            <h2 class="sga-section-title-toggle">Inscripciones Nuevas (Pendientes de Primer Contacto)</h2>
            <p class="description">Estas inscripciones tienen el estado "Pendiente" y aún no tienen registro de llamada. Deben ser tu primera prioridad.</p>

            <div class="tabla-wrapper">
                <table class="wp-list-table widefat striped" id="tabla-pendientes-nuevas">
                    <thead>
                        <tr>
                            <?php if ($can_approve): ?>
                            <th class="ga-check-column"><input type="checkbox" id="select-all-pendientes-nuevas"></th>
                            <?php endif; ?>
                            <th>Nombre</th><th>Agente Asignado</th><th>Cédula</th><th>Email</th><th>Teléfono</th><th>Curso</th><th>Horario</th><th>Estado</th>
                            <th>Estado de Llamada</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-pendientes-nuevas-tbody">
                         <!-- El contenido se cargará vía AJAX -->
                         <tr class="no-results"><td colspan="<?php echo $can_approve ? 11 : 10; ?>">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
            <!-- [NUEVO] Controles de paginación para la tabla "Nuevas" -->
            <div class="sga-pagination-controls" id="sga-pagination-nuevas">
                 <!-- El contenido se cargará vía AJAX -->
            </div>
        </div>
        
        <div id="sga-table-section-seguimiento" class="sga-table-section">
            <h2 class="sga-section-title-toggle">Inscripciones en Seguimiento</h2>
            <p class="description">Estas inscripciones ya tienen un registro de llamada (marcadas como Llamado, No Contesta, Contactado, etc.) y requieren seguimiento.</p>
            
            <div class="tabla-wrapper">
                <table class="wp-list-table widefat striped" id="tabla-pendientes-seguimiento">
                     <thead>
                        <tr>
                            <?php if ($can_approve): ?>
                            <th class="ga-check-column"><input type="checkbox" id="select-all-pendientes-seguimiento"></th>
                            <?php endif; ?>
                            <th>Nombre</th><th>Agente Asignado</th><th>Cédula</th><th>Email</th><th>Teléfono</th><th>Curso</th><th>Horario</th><th>Estado</th>
                            <th>Estado de Llamada</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-pendientes-seguimiento-tbody">
                        <!-- El contenido se cargará vía AJAX -->
                        <tr class="no-results"><td colspan="<?php echo $can_approve ? 11 : 10; ?>">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
            <!-- [NUEVO] Controles de paginación para la tabla "Seguimiento" -->
            <div class="sga-pagination-controls" id="sga-pagination-seguimiento">
                 <!-- El contenido se cargará vía AJAX -->
            </div>
        </div>
        <!-- --- FIN MODIFICACIONES DE PAGINACIÓN --- -->

        <?php
    }


    // *** INICIO - NUEVAS FUNCIONES DE RENDERIZADO PARA PAGINACIÓN ***

    /**
     * [NUEVA FUNCIÓN ESTÁTICA]
     * Llamada por AJAX para obtener el HTML de las tablas paginadas.
     * @param array $args Argumentos de filtrado y paginación.
     * @return array HTML para las tablas y controles de paginación.
     */
    public static function get_paginated_table_html($args) {
        // 1. Obtener los datos filtrados y paginados desde la utilidad
        $data = SGA_Utils::_get_filtered_and_paginated_inscriptions_data($args);
        
        $pending_data = $data['pending_calls'];
        $inprogress_data = $data['in_progress'];
        
        $can_approve = $args['can_approve'];
        
        // 2. Obtener mapa de agentes (para nombres)
        $agents = array_merge(SGA_Utils::_get_sga_agents('agente'), SGA_Utils::_get_sga_agents('agente_infotep'));
        $agent_map = [];
        foreach ($agents as $agent) {
            $agent_map[$agent->ID] = $agent->display_name;
        }

        // 3. Renderizar el HTML para la tabla "Nuevas"
        $html_nuevas = self::_render_inscription_rows_paginated(
            $pending_data['data_slice'], 
            $can_approve, 
            $agent_map
        );
        
        // 4. Renderizar el HTML de paginación para "Nuevas"
        $pagination_nuevas = self::_render_pagination_controls_html(
            $pending_data['total_count'], 
            $args['paged_nuevas'], 
            $args['posts_per_page']
        );

        // 5. Renderizar el HTML para la tabla "Seguimiento"
        $html_seguimiento = self::_render_inscription_rows_paginated(
            $inprogress_data['data_slice'], 
            $can_approve, 
            $agent_map
        );
        
        // 6. Renderizar el HTML de paginación para "Seguimiento"
        $pagination_seguimiento = self::_render_pagination_controls_html(
            $inprogress_data['total_count'], 
            $args['paged_seguimiento'], 
            $args['posts_per_page']
        );

        // 7. Devolver todo
        return [
            'html_nuevas' => $html_nuevas,
            'pagination_nuevas' => $pagination_nuevas,
            'total_nuevas' => $pending_data['total_count'],
            'html_seguimiento' => $html_seguimiento,
            'pagination_seguimiento' => $pagination_seguimiento,
            'total_seguimiento' => $inprogress_data['total_count'],
        ];
    }

    /**
     * [NUEVA FUNCIÓN ESTÁTICA HELPER]
     * Renderiza solo las filas (TRs) para una tabla de inscripción.
     * @param array $data_rows El array de datos (ya paginado).
     * @param bool $can_approve Si el usuario puede aprobar.
     * @param array $agent_map Mapa de IDs de agentes a nombres.
     * @return string HTML de las filas de la tabla.
     */
    private static function _render_inscription_rows_paginated($data_rows, $can_approve, $agent_map) {
        $agent_colors = [];
        $color_palette = ['#e0f2fe', '#e0e7ff', '#dcfce7', '#fef9c3', '#fee2e2', '#f3e8ff', '#dbeafe'];
        $color_index = 0;

        ob_start();

        if (!empty($data_rows)) {
            foreach ($data_rows as $data) {
                $estudiante = $data['estudiante'];
                $curso = $data['curso'];
                $index = $data['index'];
                $agent_id = $data['agent_id'];
                $current_call_status = $data['current_call_status'];
                $call_info = $data['call_info'];
                
                $agent_name = 'Sin Asignar';
                $row_style = '';

                if (is_numeric($agent_id)) {
                    if (isset($agent_map[$agent_id])) {
                        $agent_name = $agent_map[$agent_id];
                        if (!isset($agent_colors[$agent_id])) {
                            $agent_colors[$agent_id] = $color_palette[$color_index % count($color_palette)];
                            $color_index++;
                        }
                        $row_style = 'style="background-color:' . $agent_colors[$agent_id] . ';"';
                    }
                }

                $call_log_post_id = $call_info['cpt_log_id'] ?? null;
                // Si no está en el meta (llamada antigua), buscarlo
                if (!$call_log_post_id) {
                     $call_log_post_id = SGA_Utils::_get_last_call_log_post_id($estudiante->ID, $index);
                }
                
                ?>
                <tr data-curso="<?php echo esc_attr($curso['nombre_curso']); ?>" data-call-status="<?php echo esc_attr($current_call_status); ?>" data-agent-id="<?php echo esc_attr($agent_id); ?>" <?php echo $row_style; ?>>
                    <?php if ($can_approve): ?>
                    <td class="ga-check-column"><input type="checkbox" class="bulk-checkbox" data-postid="<?php echo $estudiante->ID; ?>" data-rowindex="<?php echo $index; ?>" data-cedula="<?php echo esc_attr(get_field('cedula', $estudiante->ID)); ?>" data-nombre="<?php echo esc_attr($estudiante->post_title); ?>"></td>
                    <?php endif; ?>
                    <td><?php echo esc_html($estudiante->post_title); ?></td>
                    <td><strong><?php echo esc_html($agent_name); ?></strong></td>
                    <td><?php echo esc_html(get_field('cedula', $estudiante->ID)); ?></td>
                    <td><?php echo esc_html(get_field('email', $estudiante->ID)); ?></td>
                    <td><?php echo esc_html(get_field('telefono', $estudiante->ID)); ?></td>
                    <td><?php echo esc_html($curso['nombre_curso']); ?></td>
                    <td><?php echo esc_html($curso['horario']); ?></td>
                    <td><span class="estado-inscrito">Inscrito</span></td>
                    <td>
                        <div class="sga-call-status-wrapper">
                            <select class="sga-call-status-select" data-postid="<?php echo esc_attr($estudiante->ID); ?>" data-rowindex="<?php echo esc_attr($index); ?>" data-nonce="<?php echo wp_create_nonce('sga_update_call_status_' . $estudiante->ID . '_' . $index); ?>">
                                <option value="pendiente" <?php selected($current_call_status, 'pendiente'); ?>>Pendiente</option>
                                <option value="contactado" <?php selected($current_call_status, 'contactado'); ?>>Contactado</option>
                                <option value="no_contesta" <?php selected($current_call_status, 'no_contesta'); ?>>No Contesta</option>
                                <option value="numero_incorrecto" <?php selected($current_call_status, 'numero_incorrecto'); ?>>Número Incorrecto</option>
                                <option value="rechazado" <?php selected($current_call_status, 'rechazado'); ?>>Rechazado</option>
                            </select>
                            <span class="spinner"></span>
                        </div>
                    </td>
                    <td>
                        <?php
                        if ($call_info) {
                            // Usamos la nueva función utilitaria para generar el HTML con botón de Editar
                            echo SGA_Utils::_get_call_log_html($estudiante->ID, $index, $call_info, $call_log_post_id, true);
                            
                        } else {
                            // Solo aparecerá en la sección de 'Nuevas y Pendientes'
                            ?>
                            <button class="button button-secondary sga-marcar-llamado-btn" data-postid="<?php echo $estudiante->ID; ?>" data-rowindex="<?php echo $index; ?>" data-nonce="<?php echo wp_create_nonce('sga_marcar_llamado_' . $estudiante->ID . '_' . $index); ?>">Marcar como Llamado</button>
                            <?php
                        }

                        if ($can_approve) { ?>
                            <button class="button button-primary aprobar-btn" data-postid="<?php echo $estudiante->ID; ?>" data-rowindex="<?php echo $index; ?>" data-cedula="<?php echo esc_attr(get_field('cedula', $estudiante->ID)); ?>" data-nombre="<?php echo esc_attr($estudiante->post_title); ?>" data-nonce="<?php echo wp_create_nonce('aprobar_nonce'); ?>">
                                Aprobar
                            </button>
                        <?php } ?>
                    </td>
                </tr>
                <?php
            }
        } else {
            // No hay resultados
            $colspan = $can_approve ? 11 : 10;
            echo '<tr class="no-results"><td colspan="' . $colspan . '">No hay inscripciones en esta sección.</td></tr>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * [NUEVA FUNCIÓN ESTÁTICA HELPER]
     * Renderiza el HTML para los controles de paginación.
     * @param int $total_items Total de ítems en esta categoría (post-filtro).
     * @param int $current_page Página actual.
     * @param int $posts_per_page Ítems por página.
     * @return string HTML de los controles.
     */
    private static function _render_pagination_controls_html($total_items, $current_page, $posts_per_page) {
        $total_pages = ceil($total_items / $posts_per_page);
        if ($total_pages <= 0) $total_pages = 1;
        
        $start_item = (($current_page - 1) * $posts_per_page) + 1;
        $end_item = min($current_page * $posts_per_page, $total_items);
        
        $info_text = 'Mostrando <strong>' . $start_item . '</strong>-<strong>' . $end_item . '</strong> de <strong>' . $total_items . '</strong> resultados';
        if ($total_items === 0) {
            $info_text = 'Mostrando <strong>0</strong> de <strong>0</strong> resultados';
        }

        ob_start();
        ?>
        <div class="sga-pagination-info">
            <?php echo $info_text; ?>
        </div>
        <div class="sga-pagination-actions">
            <button class="button sga-page-prev" <?php disabled($current_page, 1); ?>>
                &laquo; Anterior
            </button>
            <button class="button sga-page-next" <?php disabled($current_page, $total_pages); ?>>
                Siguiente &raquo;
            </button>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // *** FIN - NUEVAS FUNCIONES DE RENDERIZADO PARA PAGINACIÓN ***


    // --- VISTA DE LISTA DE MATRICULADOS ---
    public function render_view_lista_matriculados() {
        // *** INICIO OPTIMIZACIÓN ***
        // Usamos la nueva función rápida para obtener solo los IDs relevantes
        $matriculado_student_ids = SGA_Utils::_get_student_ids_by_enrollment_status('Matriculado');
        
        if (empty($matriculado_student_ids)) {
            $estudiantes_matriculados = [];
        } else {
             // Cargamos SOLAMENTE los posts de estudiantes que sabemos que tienen inscripciones matriculadas
             $estudiantes_matriculados = get_posts(array(
                'post_type' => 'estudiante',
                'posts_per_page' => -1, // -1 está bien aquí porque la lista de IDs es (relativamente) pequeña
                'post__in' => $matriculado_student_ids,
                'orderby' => 'post_title',
                'order' => 'ASC'
            ));
            // Pre-calentar caché de metadatos
            update_postmeta_cache(wp_list_pluck($estudiantes_matriculados, 'ID'));
        }
        // *** FIN OPTIMIZACIÓN ***
        
        $cursos_disponibles = get_posts(array('post_type' => 'curso', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        ?>
        <a href="#" data-view="matriculacion" class="back-link panel-nav-link">&larr; Volver a Matriculación</a>
        <h1 class="panel-title">Lista de Estudiantes Matriculados</h1>

        <div class="filtros-tabla">
            <input type="text" id="buscador-matriculados" placeholder="Buscar por matrícula, nombre, cédula...">
            <select id="filtro-curso-matriculados">
                <option value="">Todos los cursos</option>
                <?php foreach ($cursos_disponibles as $curso_filtro) : ?>
                    <option value="<?php echo esc_attr($curso_filtro->post_title); ?>"><?php echo esc_html($curso_filtro->post_title); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="export-actions-wrapper">
                <select id="export-format-select">
                    <option value="excel">Exportar a Excel</option>
                    <option value="moodle">Exportar para Moodle (CSV)</option>
                </select>
                <button id="exportar-btn" class="button">Exportar</button>
            </div>
        </div>

        <div class="tabla-wrapper">
            <table class="wp-list-table widefat striped" id="tabla-matriculados">
                <thead><tr><th>Matrícula</th><th>Nombre</th><th>Cédula</th><th>Email</th><th>Teléfono</th><th>Curso</th></tr></thead>
                <tbody>
                    <?php
                    $hay_matriculados = false;
                    // Este bucle ahora es mucho más rápido porque solo itera sobre estudiantes relevantes
                    if ($estudiantes_matriculados && function_exists('get_field')) {
                        foreach ($estudiantes_matriculados as $estudiante) { 
                            $cursos = get_field('cursos_inscritos', $estudiante->ID);
                            if ($cursos) {
                                foreach ($cursos as $curso) {
                                    if (isset($curso['estado']) && $curso['estado'] == 'Matriculado') {
                                        $hay_matriculados = true;
                                        echo '<tr data-curso="' . esc_attr($curso['nombre_curso']) . '">';
                                        echo '<td><strong>' . esc_html(isset($curso['matricula']) ? $curso['matricula'] : '') . '</strong></td>';
                                        echo '<td>' . esc_html($estudiante->post_title) . '</td>';
                                        echo '<td>' . esc_html(get_field('cedula', $estudiante->ID)) . '</td>';
                                        echo '<td>' . esc_html(get_field('email', $estudiante->ID)) . '</td>';
                                        echo '<td>' . esc_html(get_field('telefono', $estudiante->ID)) . '</td>';
                                        echo '<td>' . esc_html($curso['nombre_curso']) . '</td>';
                                        echo '</tr>';
                                    }
                                }
                            }
                        }
                    }
                    if (!$hay_matriculados) {
                        echo '<tr class="no-results"><td colspan="6">No hay estudiantes matriculados.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // --- VISTA DE LISTA GENERAL DE ESTUDIANTES ---

    public function render_view_lista_estudiantes() {
        // *** INICIO OPTIMIZACIÓN: PAGINACIÓN ***
        // Determinar la página actual
        $paged = ( isset($_GET['paged']) ) ? absint( $_GET['paged'] ) : 1;
        $posts_per_page = 50; // Mostrar 50 estudiantes por página

        $query_args = array(
            'post_type' => 'estudiante',
            'posts_per_page' => $posts_per_page,
            'paged' => $paged,
            'orderby' => 'title',
            'order' => 'ASC',
        );
        
        // WP_Query es la forma correcta de manejar paginación
        $todos_estudiantes_query = new WP_Query($query_args);
        $todos_estudiantes = $todos_estudiantes_query->posts;
        
        // Pre-calentar caché de metadatos para la página actual
        if ($todos_estudiantes_query->have_posts()) {
            update_postmeta_cache(wp_list_pluck($todos_estudiantes, 'ID'));
        }
        // *** FIN OPTIMIZACIÓN: PAGINACIÓN ***
        
        ?>
        <a href="#" data-view="matriculacion" class="back-link panel-nav-link">&larr; Volver a Matriculación</a>
        <h1 class="panel-title">Lista General de Estudiantes</h1>
        <div class="filtros-tabla">
            <select name="bulk-action-estudiantes" id="bulk-action-estudiantes-select">
                <option value="-1">Acciones en lote</option>
                <option value="enviar_correo">Enviar correo a seleccionados</option>
            </select>
            <button id="apply-bulk-action-estudiantes" class="button">Aplicar</button>
            <input type="text" id="buscador-general-estudiantes" placeholder="Buscar por nombre, cédula o email...">
        </div>
        <div class="tabla-wrapper">
            <table class="wp-list-table widefat striped" id="tabla-general-estudiantes">
                <thead><tr>
                    <th class="ga-check-column"><input type="checkbox" id="select-all-estudiantes"></th>
                    <th>Nombre</th>
                    <th>Cédula</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Acción</th>
                </tr></thead>
                <tbody>
                    <?php if ($todos_estudiantes_query->have_posts()) : ?>
                        <?php foreach ($todos_estudiantes as $estudiante) : ?>
                            <tr>
                                <td class="ga-check-column"><input type="checkbox" class="student-checkbox" value="<?php echo $estudiante->ID; ?>"></td>
                                <td><?php echo esc_html($estudiante->post_title); ?></td>
                                <td><?php echo esc_html(get_field('cedula', $estudiante->ID)); ?></td>
                                <td><?php echo esc_html(get_field('email', $estudiante->ID)); ?></td>
                                <td><?php echo esc_html(get_field('telefono', $estudiante->ID)); ?></td>
                                <td><button class="button button-secondary ver-perfil-btn" data-estudiante-id="<?php echo $estudiante->ID; ?>">Ver Perfil</button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr class="no-results"><td colspan="6">No se encontraron estudiantes.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- *** INICIO OPTIMIZACIÓN: ENLACES DE PAGINACIÓN *** -->
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo $todos_estudiantes_query->found_posts; ?> estudiantes</span>
                <?php
                // Generar los enlaces de paginación
                // [CORRECCIÓN] Asegurarse de que los enlaces de paginación funcionen con el JS de navegación
                $base_url = '#'; // Cambiar a '#' para que no recargue la página
                echo paginate_links( array(
                    'base' => $base_url . '%_%', // Usar # para el JS
                    'format' => '?paged=%#%', // Formato del enlace
                    'current' => $paged, // Página actual
                    'total' => $todos_estudiantes_query->max_num_pages, // Total de páginas
                    'prev_text' => '&laquo;', // Texto para "Anterior"
                    'next_text' => '&raquo;', // Texto para "Siguiente"
                    'add_args' => ['view' => 'lista_estudiantes'], // Añadir la vista actual
                ) );
                ?>
            </div>
        </div>
        <?php
        wp_reset_postdata(); // Restaurar datos de post originales
        // *** FIN OPTIMIZACIÓN: ENLACES DE PAGINACIÓN ***
    }
}

