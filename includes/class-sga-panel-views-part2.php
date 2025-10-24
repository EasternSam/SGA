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
        $estudiantes_inscritos = get_posts(array('post_type' => 'estudiante', 'posts_per_page' => -1));
        $cursos_disponibles = get_posts(array('post_type' => 'curso', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        
        $can_approve = $this->sga_user_has_role(['administrator']);
        $agents = SGA_Utils::_get_sga_agents();
        ?>
        <a href="#" data-view="matriculacion" class="back-link panel-nav-link">&larr; Volver a Matriculación</a>
        <h1 class="panel-title">
            <?php echo $can_approve ? 'Aprobar Inscripciones Pendientes' : 'Seguimiento de Inscripciones (Llamadas)'; ?>
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

        <div class="tabla-wrapper">
            <table class="wp-list-table widefat striped" id="tabla-pendientes">
                <thead>
                    <tr>
                        <?php if ($can_approve): ?>
                        <th class="ga-check-column"><input type="checkbox" id="select-all-pendientes"></th>
                        <?php endif; ?>
                        <th>Nombre</th><th>Agente Asignado</th><th>Cédula</th><th>Email</th><th>Teléfono</th><th>Curso</th><th>Horario</th><th>Estado</th>
                        <th>Estado de Llamada</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $hay_pendientes = false;
                    $agent_colors = [];
                    $color_palette = ['#e0f2fe', '#e0e7ff', '#dcfce7', '#fef9c3', '#fee2e2', '#f3e8ff', '#dbeafe'];
                    $color_index = 0;

                    if ($estudiantes_inscritos && function_exists('get_field')) {
                        foreach ($estudiantes_inscritos as $estudiante) {
                            $cursos = get_field('cursos_inscritos', $estudiante->ID);
                            $assignments = get_post_meta($estudiante->ID, '_sga_agent_assignments', true);
                            if (!is_array($assignments)) $assignments = [];

                            if ($cursos) {
                                foreach ($cursos as $index => $curso) {
                                    if (isset($curso['estado']) && $curso['estado'] == 'Inscrito') {
                                        $hay_pendientes = true;
                                        
                                        $call_statuses = get_post_meta($estudiante->ID, '_sga_call_statuses', true);
                                        if (!is_array($call_statuses)) $call_statuses = [];
                                        $current_call_status = $call_statuses[$index] ?? 'pendiente';
                                        
                                        $agent_id = $assignments[$index] ?? 'unassigned';
                                        $agent_name = 'Sin Asignar';
                                        $row_style = '';

                                        if (is_numeric($agent_id)) {
                                            $agent_info = get_userdata($agent_id);
                                            if ($agent_info) {
                                                $agent_name = $agent_info->display_name;
                                                if (!isset($agent_colors[$agent_id])) {
                                                    $agent_colors[$agent_id] = $color_palette[$color_index % count($color_palette)];
                                                    $color_index++;
                                                }
                                                $row_style = 'style="background-color:' . $agent_colors[$agent_id] . ';"';
                                            }
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
                                                $call_log = get_post_meta($estudiante->ID, '_sga_call_log', true);
                                                if (!is_array($call_log)) $call_log = [];
                                                $call_info = $call_log[$index] ?? null;

                                                if ($call_info) {
                                                    echo 'Llamado por <strong>' . esc_html($call_info['user_name']) . '</strong><br><small>' . esc_html(date_i18n('d/m/Y H:i', $call_info['timestamp'])) . '</small>';
                                                    if (!empty($call_info['comment'])) {
                                                        echo '<p class="sga-call-comment"><em>' . esc_html($call_info['comment']) . '</em></p>';
                                                    }
                                                } else {
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
                                }
                            }
                        }
                    }
                    if (!$hay_pendientes) {
                        $colspan = $can_approve ? 11 : 10;
                        echo '<tr class="no-results"><td colspan="' . $colspan . '">No hay estudiantes pendientes de aprobación.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // --- VISTA DE LISTA DE MATRICULADOS ---

    public function render_view_lista_matriculados() {
        $estudiantes_matriculados = get_posts(array('post_type' => 'estudiante', 'posts_per_page' => -1));
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
        $todos_estudiantes = get_posts(array('post_type' => 'estudiante', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        ?>
        <a href="#" data-view="principal" class="back-link panel-nav-link">&larr; Volver al Panel Principal</a>
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
                    <?php if ($todos_estudiantes) : ?>
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
        <?php
    }
}
