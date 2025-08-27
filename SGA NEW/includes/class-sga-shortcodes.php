<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_Shortcodes
 *
 * Gestiona la creación y renderizado de todos los shortcodes del plugin.
 * También se encarga de inyectar los estilos y scripts necesarios para los shortcodes.
 */
class SGA_Shortcodes {

    public function __construct() {
        add_shortcode('panel_gestion_academica', array($this, 'render_panel'));
        add_shortcode('sga_pagina_pagos', array($this, 'render_pagos_page'));
    }

    /**
     * Renderiza el shortcode del panel de gestión principal.
     * Carga las diferentes vistas (paneles) desde la clase.
     */
    public function render_panel() {
        ob_start();
        $this->render_panel_styles();
        ?>
        <div id="ga-modal-confirmacion" class="ga-modal" style="display:none;">
            <div class="ga-modal-content">
                <div class="ga-modal-icon-wrapper">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </div>
                <h4>Confirmar Aprobación</h4>
                <p>Se enviará un correo de notificación al estudiante informando que ha sido matriculado.</p>
                <div class="ga-modal-actions">
                    <button id="ga-modal-cancelar" class="button button-secondary">Cancelar</button>
                    <button id="ga-modal-confirmar" class="button button-primary">Confirmar y Enviar</button>
                </div>
            </div>
        </div>

        <div id="gestion-academica-app-container">
            <div class="gestion-academica-wrapper">
                <div id="panel-view-principal" class="panel-view active"><?php $this->render_view_principal(); ?></div>
                <div id="panel-view-matriculacion" class="panel-view"><?php $this->render_view_matriculacion(); ?></div>
                <div id="panel-view-enviar_a_matriculacion" class="panel-view"><?php $this->render_view_enviar_a_matriculacion(); ?></div>
                <div id="panel-view-lista_matriculados" class="panel-view"><?php $this->render_view_lista_matriculados(); ?></div>
                <div id="panel-view-estudiantes" class="panel-view"><?php $this->render_view_lista_estudiantes(); ?></div>
                <div id="panel-view-cursos" class="panel-view"><?php $this->render_view_lista_cursos(); ?></div>
                <div id="panel-view-registro_pagos" class="panel-view"><?php $this->render_view_registro_pagos(); ?></div>
                <div id="panel-view-log" class="panel-view"><?php $this->render_view_log(); ?></div>
                <div id="panel-view-perfil_estudiante" class="panel-view"><?php $this->render_view_perfil_estudiante(); ?></div>
                <div id="panel-view-comunicacion" class="panel-view"><?php $this->render_view_comunicacion(); ?></div>
            </div>
        </div>
        <?php
        $this->render_panel_navigation_js();
        return ob_get_clean();
    }

    /**
     * Renderiza el shortcode de la página de pagos.
     */
    public function render_pagos_page() {
        $payments_handler = new SGA_Payments();
        return $payments_handler->render_pagos_page();
    }

    // --- MÉTODOS PARA RENDERIZAR VISTAS DEL PANEL ---

    public function render_view_principal() {
        $total_estudiantes_obj = wp_count_posts('estudiante');
        $total_cursos_obj = wp_count_posts('curso');
        $total_estudiantes = isset($total_estudiantes_obj->publish) ? $total_estudiantes_obj->publish : 0;
        $total_cursos = isset($total_cursos_obj->publish) ? $total_cursos_obj->publish : 0;
        $inscripciones_pendientes = 0;
        if (function_exists('get_field')) {
            $estudiantes = get_posts(array('post_type' => 'estudiante', 'posts_per_page' => -1, 'fields' => 'ids'));
            if($estudiantes){
                foreach ($estudiantes as $estudiante_id) {
                    $cursos = get_field('cursos_inscritos', $estudiante_id);
                    if ($cursos) {
                        foreach ($cursos as $curso) {
                            if (isset($curso['estado']) && $curso['estado'] == 'Inscrito') {
                                $inscripciones_pendientes++;
                            }
                        }
                    }
                }
            }
        }
        $current_user = wp_get_current_user();
        ?>
        <div class="panel-header-info">
            <div class="user-welcome">
                <p>Bienvenido de nuevo,</p>
                <h3><?php echo esc_html($current_user->display_name); ?></h3>
            </div>
            <div class="datetime-widget">
                <div class="date-display" id="dynamic-date"></div>
                <div class="time-display" id="dynamic-time"></div>
            </div>
        </div>

        <h1 class="panel-title">Panel Principal</h1>

        <div class="panel-stats-grid">
            <div class="stat-card">
                <div class="stat-icon students">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $total_estudiantes; ?></span>
                    <span class="stat-label">Estudiantes Totales</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pending">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $inscripciones_pendientes; ?></span>
                    <span class="stat-label">Inscripciones Pendientes</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon courses">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $total_cursos; ?></span>
                    <span class="stat-label">Cursos Activos</span>
                </div>
            </div>
        </div>

        <div class="panel-grid main-menu">
            <a href="#" data-view="matriculacion" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12c2.2 0 4-1.8 4-4s-1.8-4-4-4-4 1.8-4 4 1.8 4 4 4z"/><path d="M20.6 20.4c-.4-3.3-3.8-5.9-8.6-5.9s-8.2 2.6-8.6 5.9"/><path d="M18 18.2c.4-.2.9-.4 1.3-.7"/><path d="M22 13.8c0-.6-.1-1.2-.3-1.8"/><path d="M11.3 2.2c-.4.2-.8.4-1.2.7"/><path d="M2 13.8c0 .6.1 1.2.3 1.8"/><path d="M4.7 17.5c-.4.3-.8.5-1.3.7"/><path d="M12.7 21.8c.4-.2.8-.4 1.2-.7"/></svg></div>
                <h2>Matriculación</h2>
                <p>Aprobar y gestionar matrículas</p>
            </a>
            <a href="#" data-view="estudiantes" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                <h2>Estudiantes</h2>
                <p>Ver y editar perfiles</p>
            </a>
            <a href="#" data-view="cursos" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg></div>
                <h2>Cursos</h2>
                <p>Administrar cursos y horarios</p>
            </a>
            <a href="#" data-view="registro_pagos" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
                <h2>Pagos</h2>
                <p>Consultar historial de pagos</p>
            </a>
            <a href="#" data-view="comunicacion" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
                <h2>Comunicación</h2>
                <p>Enviar correos masivos</p>
            </a>
            <a href="#" data-view="log" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22h6a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v5"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M5 17a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/><path d="M5 17v-2.5"/><path d="M5 12V2"/></svg></div>
                <h2>Actividad</h2>
                <p>Ver registros del sistema</p>
            </a>
        </div>
        <?php
    }

    public function render_view_matriculacion() {
        ?>
        <a href="#" data-view="principal" class="back-link panel-nav-link">&larr; Volver al Panel Principal</a>
        <h1 class="panel-title">Sistema de Matriculación</h1>
        <div class="panel-grid">
            <a href="#" data-view="enviar_a_matriculacion" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/></svg></div>
                <h2>Aprobar Inscripciones</h2>
                <p>Validar y matricular nuevos estudiantes</p>
            </a>
            <a href="#" data-view="lista_matriculados" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></div>
                <h2>Lista de Matriculados</h2>
                <p>Consultar y exportar estudiantes activos</p>
            </a>
            <a href="<?php echo esc_url(site_url('/cursos/')); ?>" target="_blank" class="panel-card">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></div>
                <h2>Nueva Inscripción</h2>
                <p>Inscribir un estudiante manualmente</p>
            </a>
        </div>
        <?php
    }

    public function render_view_enviar_a_matriculacion() {
        $estudiantes_inscritos = get_posts(array('post_type' => 'estudiante', 'posts_per_page' => -1));
        $cursos_disponibles = get_posts(array('post_type' => 'curso', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        ?>
        <a href="#" data-view="matriculacion" class="back-link panel-nav-link">&larr; Volver a Matriculación</a>
        <h1 class="panel-title">Aprobar Inscripciones Pendientes</h1>

        <div class="filtros-tabla">
            <div class="bulk-actions-wrapper">
                <select name="bulk-action" id="bulk-action-select">
                    <option value="-1">Acciones en lote</option>
                    <option value="aprobar">Aprobar seleccionados</option>
                </select>
                <button id="apply-bulk-action" class="button">Aplicar</button>
            </div>
            <input type="text" id="buscador-estudiantes-pendientes" placeholder="Buscar por nombre, cédula o curso...">
            <select id="filtro-curso-pendientes">
                <option value="">Todos los cursos</option>
                <?php foreach ($cursos_disponibles as $curso_filtro) : ?>
                    <option value="<?php echo esc_attr($curso_filtro->post_title); ?>"><?php echo esc_html($curso_filtro->post_title); ?></option>
                <?php endforeach; ?>
            </select>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sga-aprobar-inscripciones')); ?>" class="button button-secondary" target="_blank">Gestión Avanzada</a>
        </div>

        <div class="tabla-wrapper">
            <table class="wp-list-table widefat striped" id="tabla-pendientes">
                <thead><tr><th class="ga-check-column"><input type="checkbox" id="select-all-pendientes"></th><th>Nombre</th><th>Cédula</th><th>Email</th><th>Teléfono</th><th>Curso</th><th>Horario</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php
                    $hay_pendientes = false;
                    if ($estudiantes_inscritos && function_exists('get_field')) {
                        foreach ($estudiantes_inscritos as $estudiante) {
                            $cursos = get_field('cursos_inscritos', $estudiante->ID);
                            if ($cursos) {
                                foreach ($cursos as $index => $curso) {
                                    if (isset($curso['estado']) && $curso['estado'] == 'Inscrito') {
                                        $hay_pendientes = true;
                                        ?>
                                        <tr data-curso="<?php echo esc_attr($curso['nombre_curso']); ?>">
                                            <td class="ga-check-column"><input type="checkbox" class="bulk-checkbox" data-postid="<?php echo $estudiante->ID; ?>" data-rowindex="<?php echo $index; ?>" data-cedula="<?php echo esc_attr(get_field('cedula', $estudiante->ID)); ?>" data-nombre="<?php echo esc_attr($estudiante->post_title); ?>"></td>
                                            <td><?php echo esc_html($estudiante->post_title); ?></td>
                                            <td><?php echo esc_html(get_field('cedula', $estudiante->ID)); ?></td>
                                            <td><?php echo esc_html(get_field('email', $estudiante->ID)); ?></td>
                                            <td><?php echo esc_html(get_field('telefono', $estudiante->ID)); ?></td>
                                            <td><?php echo esc_html($curso['nombre_curso']); ?></td>
                                            <td><?php echo esc_html($curso['horario']); ?></td>
                                            <td><span class="estado-inscrito">Inscrito</span></td>
                                            <td><button class="button button-primary aprobar-btn" data-postid="<?php echo $estudiante->ID; ?>" data-rowindex="<?php echo $index; ?>" data-cedula="<?php echo esc_attr(get_field('cedula', $estudiante->ID)); ?>" data-nombre="<?php echo esc_attr($estudiante->post_title); ?>" data-nonce="<?php echo wp_create_nonce('aprobar_nonce'); ?>">Aprobar</button></td>
                                        </tr>
                                        <?php
                                    }
                                }
                            }
                        }
                    }
                    if (!$hay_pendientes) {
                        echo '<tr class="no-results"><td colspan="9">No hay estudiantes pendientes de aprobación.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }

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

    public function render_view_lista_cursos() {
        $cursos_activos = get_posts(array('post_type' => 'curso', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        ?>
        <a href="#" data-view="principal" class="back-link panel-nav-link">&larr; Volver al Panel Principal</a>
        <h1 class="panel-title">Lista de Cursos Activos</h1>

        <div class="filtros-tabla">
            <input type="text" id="buscador-cursos" placeholder="Buscar por nombre de curso...">
             <a href="<?php echo esc_url(admin_url('post-new.php?post_type=curso')); ?>" target="_blank" class="button button-primary">Nuevo Curso</a>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=curso')); ?>" target="_blank" class="button button-secondary">Editar Cursos</a>
        </div>

        <div class="tabla-wrapper" style="margin-top: 25px;">
            <table class="wp-list-table widefat striped" id="tabla-cursos">
                <thead>
                    <tr>
                        <th>Nombre del Curso</th><th>Horarios y Modalidades</th><th>Cupos</th><th>Escuela</th>
                        <th>Precio</th><th>Mensualidad</th><th>Duración</th><th>Fecha de Inicio</th><th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($cursos_activos && function_exists('get_field')) : ?>
                        <?php
                        foreach ($cursos_activos as $curso) :
                            $horarios_repeater = get_field('horarios_del_curso', $curso->ID);
                            $horarios_html = '';
                            $cupos_display = '';
                            $fecha_inicio_display = '';

                            if ($horarios_repeater) {
                                $horarios_html = '<div class="horarios-container">';
                                $fechas_inicio_array = [];
                                $cupos_array = [];
                                foreach ($horarios_repeater as $horario) {
                                    $horario_completo = $horario['dias_de_la_semana'] . ' ' . $horario['hora'];
                                    $modalidad = !empty($horario['modalidad']) ? $horario['modalidad'] : 'N/A';
                                    $modalidad_class = 'ga-pill-default';
                                    switch (strtolower($modalidad)) {
                                        case 'presencial': $modalidad_class = 'ga-pill-presencial'; break;
                                        case 'virtual': $modalidad_class = 'ga-pill-virtual'; break;
                                        case 'híbrido': case 'hibrido': $modalidad_class = 'ga-pill-hibrido'; break;
                                    }

                                    $horarios_html .= '<div class="horario-item">';
                                    $horarios_html .= '<span class="ga-pill ga-pill-time">' . esc_html($horario_completo) . '</span>';
                                    $horarios_html .= '<span class="ga-pill ' . $modalidad_class . '">' . esc_html($modalidad) . '</span>';
                                    $horarios_html .= '</div>';

                                    $total_cupos = !empty($horario['numero_de_cupos']) ? intval($horario['numero_de_cupos']) : 0;
                                    if ($total_cupos > 0) {
                                        $cupos_ocupados = SGA_Utils::_get_cupos_ocupados($curso->post_title, $horario_completo);
                                        $cupos_array[] = "{$cupos_ocupados} / {$total_cupos}";
                                    } else {
                                        $cupos_array[] = 'Ilimitados';
                                    }

                                    if (!empty($horario['fecha_de_inicio'])) {
                                        $fechas_inicio_array[] = $horario['fecha_de_inicio'];
                                    }
                                }
                                $horarios_html .= '</div>';
                                $cupos_display = implode('<br>', $cupos_array);
                                $fecha_inicio_display = implode('<br>', array_unique($fechas_inicio_array));
                            }

                            $escuelas = get_the_terms($curso->ID, 'category');
                            $escuela_display = 'N/A';
                            if ($escuelas && !is_wp_error($escuelas)) {
                                $escuela_names = array();
                                foreach ($escuelas as $escuela) $escuela_names[] = $escuela->name;
                                $escuela_display = implode(', ', $escuela_names);
                            }
                            ?>
                            <tr>
                                <td><?php echo esc_html($curso->post_title); ?></td>
                                <td><?php echo $horarios_html; // WPCS: XSS ok. ?></td>
                                <td><?php echo $cupos_display; // WPCS: XSS ok. ?></td>
                                <td><?php echo esc_html($escuela_display); ?></td>
                                <td><?php echo esc_html(get_field('precio_del_curso', $curso->ID)); ?></td>
                                <td><?php echo esc_html(get_field('mensualidad', $curso->ID)); ?></td>
                                <td><?php echo esc_html(get_field('duracion_del_curso', $curso->ID)); ?></td>
                                <td><?php echo $fecha_inicio_display; // WPCS: XSS ok. ?></td>
                                <td class="actions-cell">
                                    <a href="#" class="button button-secondary ver-matriculados-btn" data-curso-nombre="<?php echo esc_attr($curso->post_title); ?>">Matriculados</a>
                                    <a href="<?php echo get_edit_post_link($curso->ID); ?>" class="button" target="_blank">Editar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr class="no-results"><td colspan="9">No se encontraron cursos.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

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
        <a href="#" data-view="principal" class="back-link panel-nav-link">&larr; Volver al Panel Principal</a>
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

    public function render_view_registro_pagos() {
        $pagos = get_posts(array('post_type' => 'sga_pago', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC'));
        ?>
        <a href="#" data-view="principal" class="back-link panel-nav-link">&larr; Volver al Panel Principal</a>
        <h1 class="panel-title">Registro de Pagos</h1>

        <div class="filtros-tabla">
            <input type="text" id="buscador-pagos" placeholder="Buscar por estudiante, concepto o ID de transacción...">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="sga_print_payment_history">
                <?php wp_nonce_field('sga_print_history_nonce', '_wpnonce_print_history'); ?>
                <button type="submit" class="button button-primary">Imprimir Historial Completo</button>
            </form>
        </div>

        <div class="tabla-wrapper">
            <table class="wp-list-table widefat striped" id="tabla-pagos">
                <thead><tr><th>Fecha</th><th>Estudiante</th><th>Concepto</th><th>Monto Pagado</th><th>ID Transacción</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php if ($pagos) : ?>
                        <?php
                        foreach ($pagos as $pago) :
                            $print_url = add_query_arg([
                                'action' => 'sga_print_invoice',
                                'payment_id' => $pago->ID,
                                '_wpnonce' => wp_create_nonce('sga_print_invoice_' . $pago->ID)
                            ], admin_url('admin-ajax.php'));
                            ?>
                            <tr>
                                <td><?php echo get_the_date('Y-m-d H:i:s', $pago); ?></td>
                                <td><?php echo esc_html(get_post_meta($pago->ID, '_student_name', true)); ?></td>
                                <td><?php echo esc_html(get_post_meta($pago->ID, '_payment_description', true)); ?></td>
                                <td><?php echo esc_html(get_post_meta($pago->ID, '_payment_amount', true)); ?> <?php echo esc_html(get_post_meta($pago->ID, '_payment_currency', true)); ?></td>
                                <td><?php echo esc_html(get_post_meta($pago->ID, '_transaction_id', true)); ?></td>
                                <td><a href="<?php echo esc_url($print_url); ?>" class="button button-secondary" target="_blank">Imprimir Factura</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr class="no-results"><td colspan="6">No hay pagos registrados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_view_perfil_estudiante() {
        ?>
        <div id="sga-student-profile-content">
            <div class="sga-profile-loading">
                <div class="spinner is-active" style="float:none; width:auto; height:auto; margin: 20px auto;"></div>
                Cargando perfil del estudiante...
            </div>
        </div>
        <?php
    }

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

    // --- MÉTODOS PARA ESTILOS Y SCRIPTS ---

    public function render_panel_styles() {
        ?>
        <style>
            :root {
                --sga-primary: #141f53; --sga-primary-dark: #0f173d; --sga-secondary: #4f46e5;
                --sga-light: #f8fafc; --sga-gray: #e2e8f0; --sga-text: #334155;
                --sga-text-light: #64748b; --sga-white: #ffffff; --sga-green: #10b981;
                --sga-yellow: #f59e0b; --sga-red: #ef4444; --sga-blue: #3b82f6;
                --sga-purple: #8b5cf6; --sga-pink: #ec4899;
                --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
                --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
                --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            }
            @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
            #gestion-academica-app-container { padding: 20px; }
            .gestion-academica-wrapper { background-color: var(--sga-white); border-radius: 16px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; box-shadow: var(--shadow-lg); }
            .panel-view { display: none; padding: 30px 40px; animation: fadeIn 0.5s ease-out; }
            .panel-view.active { display: block; }
            
            /* Header */
            .panel-header-info { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--sga-gray); }
            .user-welcome p { margin: 0; color: var(--sga-text-light); font-size: 14px; }
            .user-welcome h3 { margin: 0; color: var(--sga-primary); font-size: 20px; font-weight: 600; }
            .datetime-widget { text-align: right; }
            .date-display { font-size: 14px; font-weight: 500; color: var(--sga-text); }
            .time-display { font-size: 12px; color: var(--sga-text-light); }
            .panel-title { font-size: 24px; margin: 25px 0; color: var(--sga-text); font-weight: 700; letter-spacing: -0.5px; }
            .back-link { color: var(--sga-secondary); text-decoration: none; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 25px; font-weight: 600; transition: color 0.2s; }
            .back-link:hover { color: var(--sga-primary); }

            /* Stats Cards */
            .panel-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 40px; }
            .stat-card { background-color: var(--sga-light); border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 16px; border: 1px solid var(--sga-gray); transition: all 0.3s ease; }
            .stat-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); border-color: var(--sga-secondary); }
            .stat-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--sga-white); }
            .stat-icon.students { background: linear-gradient(135deg, var(--sga-blue), #60a5fa); }
            .stat-icon.pending { background: linear-gradient(135deg, var(--sga-yellow), #fbbf24); }
            .stat-icon.courses { background: linear-gradient(135deg, var(--sga-green), #34d399); }
            .stat-icon svg { width: 24px; height: 24px; }
            .stat-info .stat-number { font-size: 28px; font-weight: 700; color: var(--sga-text); display: block; line-height: 1; }
            .stat-info .stat-label { font-size: 14px; color: var(--sga-text-light); }

            /* Main Menu Cards */
            .panel-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; }
            .panel-card { background-color: var(--sga-white); border: 1px solid var(--sga-gray); border-radius: 12px; padding: 25px; text-align: center; text-decoration: none; color: var(--sga-text); transition: all .3s ease; cursor: pointer; position: relative; overflow: hidden; }
            .panel-card:before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, var(--sga-secondary), var(--sga-primary)); transform: scaleX(0); transition: transform 0.4s ease; transform-origin: left; }
            .panel-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
            .panel-card:hover:before { transform: scaleX(1); }
            .panel-card-icon { margin: 0 auto 15px; width: 50px; height: 50px; border-radius: 50%; background-color: var(--sga-light); display: flex; align-items: center; justify-content: center; color: var(--sga-secondary); transition: all .3s ease; }
            .panel-card:hover .panel-card-icon { background-color: var(--sga-secondary); color: var(--sga-white); transform: rotate(10deg) scale(1.1); }
            .panel-card h2 { margin: 0 0 5px 0; font-size: 18px; font-weight: 600; color: var(--sga-primary); }
            .panel-card p { margin: 0; font-size: 14px; color: var(--sga-text-light); }

            /* Tables & Filters */
            .tabla-wrapper { overflow-x: auto; width: 100%; border: 1px solid var(--sga-gray); border-radius: 12px; box-shadow: var(--shadow-sm); }
            .wp-list-table { background: var(--sga-white); margin: 0; width: 100%; border-collapse: collapse; }
            .wp-list-table thead th { background-color: var(--sga-light); color: var(--sga-text); font-weight: 600; border: none; text-align: left; padding: 12px 15px; border-bottom: 2px solid var(--sga-gray); }
            .wp-list-table tbody tr { transition: background-color 0.2s; }
            .wp-list-table tbody tr:hover { background-color: #f1f5f9; }
            .wp-list-table td { padding: 12px 15px; vertical-align: middle; border-bottom: 1px solid var(--sga-gray); }
            .wp-list-table td.actions-cell { display: flex; gap: 8px; align-items: center; }
            .wp-list-table tbody tr:last-child td { border-bottom: none; }
            .filtros-tabla { display: flex; flex-wrap: wrap; align-items: center; gap: 15px; margin-bottom: 25px; padding: 20px; background-color: var(--sga-light); border-radius: 12px; }
            .filtros-tabla input[type=text], .filtros-tabla input[type=date], .filtros-tabla select { padding: 10px 15px; border-radius: 8px; border: 1px solid var(--sga-gray); background-color: var(--sga-white); transition: all 0.2s; }
            .filtros-tabla input:focus, .filtros-tabla select:focus { border-color: var(--sga-secondary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); outline: none; }
            .filtros-tabla input[type=text] { flex-grow: 1; }
            .button, .button-primary, .button-secondary { border-radius: 8px !important; padding: 8px 16px !important; font-weight: 600 !important; transition: all 0.2s !important; border: 1px solid transparent !important; line-height: 1.5 !important; height: auto !important; }
            .button-primary { background-color: var(--sga-secondary) !important; color: var(--sga-white) !important; }
            .button-primary:hover { background-color: var(--sga-primary) !important; transform: translateY(-2px); }
            .button-secondary { background-color: var(--sga-light) !important; color: var(--sga-text) !important; border-color: var(--sga-gray) !important; }
            .button-secondary:hover { background-color: var(--sga-gray) !important; }
            
            /* Status Pills & Course Table */
            .horarios-container { display: flex; flex-direction: column; gap: 8px; }
            .horario-item { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
            .estado-inscrito { color: var(--sga-yellow); background-color: #fffbeb; padding: 4px 10px; border-radius: 999px; font-weight: 500; font-size: 12px; }
            .ga-pill { display: inline-block; padding: 4px 10px; font-size: 12px; font-weight: 500; border-radius: 16px; color: var(--sga-white); }
            .ga-pill-time { background-color: var(--sga-text-light); } .ga-pill-presencial { background-color: var(--sga-blue); }
            .ga-pill-virtual { background-color: var(--sga-purple); } .ga-pill-hibrido { background-color: var(--sga-pink); }

            /* Modal */
            .ga-modal { position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; }
            .ga-modal-content { background-color: var(--sga-white); padding: 30px; border-radius: 12px; width: 90%; max-width: 420px; box-shadow: var(--shadow-lg); text-align: center; animation: fadeIn 0.3s; }
            .ga-modal-icon-wrapper { width: 50px; height: 50px; margin: 0 auto 20px; background-color: var(--sga-green); color: var(--sga-white); border-radius: 50%; display: flex; align-items: center; justify-content: center; }
            .ga-modal-content h4 { margin-top: 0; font-size: 18px; color: var(--sga-text); }
            .ga-modal-actions { margin-top: 25px; display: flex; justify-content: center; gap: 10px; }
            
            /* Student Profile View */
            #panel-view-perfil_estudiante .sga-profile-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 30px; }
            #panel-view-perfil_estudiante .sga-profile-card { background-color: var(--sga-light); border: 1px solid var(--sga-gray); border-radius: 12px; padding: 25px; }
            #panel-view-perfil_estudiante .sga-profile-card h3 { margin-top: 0; border-bottom: 1px solid var(--sga-gray); padding-bottom: 10px; font-size: 18px; font-weight: 600; color: var(--sga-primary); }
            #panel-view-perfil_estudiante .sga-profile-form-group { margin-bottom: 20px; }
            #panel-view-perfil_estudiante .sga-profile-form-group label { display: block; font-weight: 500; margin-bottom: 8px; font-size: 14px; color: var(--sga-text-light); }
            #panel-view-perfil_estudiante .sga-profile-form-group input, #panel-view-perfil_estudiante .sga-profile-form-group select { width: 100%; padding: 10px 15px; border: 1px solid var(--sga-gray); border-radius: 8px; background-color: var(--sga-white); transition: all 0.2s; }
            #panel-view-perfil_estudiante .sga-profile-form-group input:focus, #panel-view-perfil_estudiante .sga-profile-form-group select:focus { border-color: var(--sga-secondary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); outline: none; }
            #panel-view-perfil_estudiante .sga-profile-actions { margin-top: 30px; display: flex; justify-content: flex-end; }

            /* Communication View */
            .sga-dynamic-tags-info { background-color: #f1f5f9; border-radius: 8px; padding: 15px; margin-bottom: 15px; font-size: 13px; border: 1px solid var(--sga-gray); }
            .sga-dynamic-tags-info p { margin: 0 0 10px 0; font-weight: 600; color: var(--sga-text); }
            .sga-dynamic-tags-info ul { margin: 0; padding-left: 20px; }
            .sga-dynamic-tags-info code { background-color: var(--sga-gray); padding: 3px 6px; border-radius: 4px; font-family: monospace; color: var(--sga-primary); }
            #sga-estudiantes-checkbox-list { height: 200px; overflow-y: auto; border: 1px solid var(--sga-gray); padding: 10px; border-radius: 8px; margin-top: 10px; background-color: var(--sga-white); }
            .sga-student-item label { display: block; padding: 5px; border-radius: 4px; transition: background-color 0.2s; cursor: pointer; }
            .sga-student-item label:hover { background-color: #f1f5f9; }
        </style>
        <?php
    }

    public function render_panel_navigation_js() {
        ?>
        <script>
            jQuery(document).ready(function($){var ajaxurl="<?php echo admin_url('admin-ajax.php');?>",approvalData={};function setDynamicDateTime(){if(!$("#dynamic-date").length)return;const e=new Date,t={weekday:"long",year:"numeric",month:"long",day:"numeric"},o={hour:"2-digit",minute:"2-digit",second:"2-digit",hour12:!0};let a=new Intl.DateTimeFormat("es-ES",t).format(e);a=a.charAt(0).toUpperCase()+a.slice(1);const n=e.toLocaleTimeString("es-ES",o);$("#dynamic-date").text(a),$("#dynamic-time").text(n)}setDynamicDateTime(),setInterval(setDynamicDateTime,1e3),$("#gestion-academica-app-container").on("click",".panel-nav-link",function(e){e.preventDefault();var t=$(this).data("view"),o=$(".panel-view.active");o.is("#panel-view-"+t)||o.fadeOut(200,function(){$(this).removeClass("active"),$("#panel-view-"+t).fadeIn(200).addClass("active")})}),$("#tabla-pendientes").on("click",".aprobar-btn",function(){var e=$(this);approvalData={type:"single",nonce:e.data("nonce"),post_id:e.data("postid"),row_index:e.data("rowindex"),cedula:e.data("cedula"),nombre:e.data("nombre"),element:e},$("#ga-modal-confirmacion").fadeIn(200)}),$("#apply-bulk-action").on("click",function(){if("aprobar"!==$("#bulk-action-select").val())return void alert("Por favor, selecciona una acción válida.");var e=[];$("#tabla-pendientes .bulk-checkbox:checked").each(function(){var t=$(this);e.push({post_id:t.data("postid"),row_index:t.data("rowindex")})}),e.length?(approvalData={type:"bulk",nonce:"<?php echo wp_create_nonce("aprobar_bulk_nonce");?>",seleccionados:e,element:$(this)},$("#ga-modal-confirmacion").fadeIn(200)):alert("Por favor, selecciona al menos un estudiante.")}),$("#ga-modal-confirmar").on("click",function(){var e=$(this);e.text("Procesando...").prop("disabled",!0),$("#ga-modal-cancelar").prop("disabled",!0),"single"===approvalData.type?$.post(ajaxurl,{action:"aprobar_para_matriculacion",_ajax_nonce:approvalData.nonce,post_id:approvalData.post_id,row_index:approvalData.row_index,cedula:approvalData.cedula,nombre:approvalData.nombre}).done(function(e){e.success?(actualizarUIAprobacion(e.data),approvalData.element.closest("tr").fadeOut(500,function(){$(this).remove(),checkEmptyTable("#tabla-pendientes",9,"No hay estudiantes pendientes de aprobación.")})):alert("Hubo un error: "+(e.data||"Error desconocido")),closeModal()}).fail(function(e,t,o){console.error("AJAX Error:",t,o),alert("Hubo un error de comunicación con el servidor."),closeModal()}):"bulk"===approvalData.type&&$.post(ajaxurl,{action:"aprobar_seleccionados",_ajax_nonce:approvalData.nonce,seleccionados:approvalData.seleccionados}).done(function(e){if(e.success){if(e.data.approved&&e.data.approved.length>0&&e.data.approved.forEach(function(e){actualizarUIAprobacion(e),$('#tabla-pendientes .bulk-checkbox[data-postid="'+e.post_id+'"][data-rowindex="'+e.row_index+'"]').closest("tr").fadeOut(500,function(){$(this).remove(),checkEmptyTable("#tabla-pendientes",9,"No hay estudiantes pendientes de aprobación.")})}),e.data.failed&&e.data.failed.length>0){var t="No se pudo aprobar a "+e.data.failed.length+" estudiante(s). Por favor, revisa la consola para más detalles o inténtalo de nuevo.";alert(t),console.log("Estudiantes no aprobados:",e.data.failed)}}else alert("Hubo un error al procesar la solicitud: "+(e.data.message||"Error desconocido"));closeModal(),$("#select-all-pendientes").prop("checked",!1),$("#tabla-pendientes .bulk-checkbox").prop("checked",!1)}).fail(function(e,t,o){console.error("AJAX Error:",t,o),alert("Hubo un error de comunicación con el servidor."),closeModal()})});function closeModal(){$("#ga-modal-confirmacion").fadeOut(200),$("#ga-modal-confirmar").text("Confirmar y Enviar").prop("disabled",!1),$("#ga-modal-cancelar").prop("disabled",!1),approvalData={}}function actualizarUIAprobacion(e){var t='<tr data-curso="'+e.nombre_curso+'"><td><strong>'+e.matricula+"</strong></td><td>"+e.nombre+"</td><td>"+e.cedula+"</td><td>"+e.email+"</td><td>"+e.telefono+"</td><td>"+e.nombre_curso+"</td></tr>";$("#tabla-matriculados .no-results").remove(),$("#tabla-matriculados tbody").append(t)}function checkEmptyTable(e,t,o){0===$(e+" tbody tr:not(.no-results-search)").length&&!$(e+" .no-results").length&&$(e+" tbody").append('<tr class="no-results"><td colspan="'+t+'">'+o+"</td></tr>")}$("#ga-modal-cancelar, .ga-modal").on("click",function(e){(e.target==this||$(this).is("#ga-modal-cancelar"))&&closeModal()}),$("#select-all-pendientes").on("click",function(){$("#tabla-pendientes .bulk-checkbox").prop("checked",this.checked)});function actualizarFiltros(e,t,o){var a=$(t).val().toLowerCase(),n=o?$(o).val():"",r=0;$(e+" tbody tr").each(function(){var t=$(this);if(!t.hasClass("no-results")&&!t.hasClass("no-results-search")){var l=t.text().toLowerCase(),d=t.data("curso"),s=(""===a||l.includes(a))&&(!o||""===n||d===n);s?(t.show(),r++):t.hide()}}),$(e+" .no-results-search").remove(),0===r&&!$(e+" .no-results").is(":visible")&&$(e+" tbody").append('<tr class="no-results-search"><td colspan="100%">No se encontraron resultados para los filtros aplicados.</td></tr>')}function actualizarFiltrosLog(){var e=$("#buscador-log").val().toLowerCase(),t=$("#filtro-usuario-log").val(),o=$("#filtro-fecha-inicio").val(),a=$("#filtro-fecha-fin").val(),n=0;$("#tabla-log tbody tr").each(function(){var r=$(this);if(!r.hasClass("no-results")){var l=r.text().toLowerCase(),d=r.data("usuario"),s=r.data("fecha"),c=(""===e||l.includes(e))&&(""===t||d===t),i=!0;i=o&&a?s>=o&&s<=a:o?s>=o:a?s<=a:!0,c&&i?(r.show(),n++):r.hide()}}),$("#tabla-log .no-results-search").remove(),0===n&&!$("#tabla-log .no-results").is(":visible")&&$("#tabla-log tbody").append('<tr class="no-results-search"><td colspan="4">No se encontraron resultados para los filtros aplicados.</td></tr>')}$("#buscador-estudiantes-pendientes, #filtro-curso-pendientes").on("keyup change",function(){actualizarFiltros("#tabla-pendientes","#buscador-estudiantes-pendientes","#filtro-curso-pendientes")}),$("#buscador-matriculados, #filtro-curso-matriculados").on("keyup change",function(){actualizarFiltros("#tabla-matriculados","#buscador-matriculados","#filtro-curso-matriculados")}),$("#buscador-general-estudiantes").on("keyup",function(){actualizarFiltros("#tabla-general-estudiantes","#buscador-general-estudiantes",null)}),$("#buscador-cursos").on("keyup",function(){actualizarFiltros("#tabla-cursos","#buscador-cursos",null)}),$("#buscador-log, #filtro-usuario-log, #filtro-fecha-inicio, #filtro-fecha-fin").on("keyup change",function(){actualizarFiltrosLog()}),$("#exportar-btn").on("click",function(e){e.preventDefault();var t=$("#export-format-select").val(),o=$("#buscador-matriculados").val(),a=$("#filtro-curso-matriculados").val(),n="<?php echo wp_create_nonce("export_nonce");?>",r=new URL(ajaxurl);"excel"===t?r.searchParams.append("action","exportar_excel"):r.searchParams.append("action","exportar_moodle_csv"),r.searchParams.append("_wpnonce",n),r.searchParams.append("search_term",o),r.searchParams.append("course_filter",a),window.location.href=r.href}),$("#panel-view-cursos").on("click",".ver-matriculados-btn",function(e){e.preventDefault();var t=$(this).data("curso-nombre");$(".panel-view").removeClass("active").hide(),$("#panel-view-lista_matriculados").addClass("active").show(),$("#filtro-curso-matriculados").val(t).change(),$("#buscador-matriculados").val("")}),$("#buscador-pagos").on("keyup",function(){actualizarFiltros("#tabla-pagos","#buscador-pagos",null)});
            $("#tabla-general-estudiantes").on("click", ".ver-perfil-btn", function() {
                var studentId = $(this).data('estudiante-id');
                var profileContainer = $("#sga-student-profile-content");
                profileContainer.html('<div class="sga-profile-loading"><div class="spinner is-active" style="float:none; width:auto; height:auto; margin: 20px auto;"></div>Cargando perfil del estudiante...</div>');
                $(".panel-view.active").fadeOut(200, function() {
                    $(this).removeClass("active");
                    $("#panel-view-perfil_estudiante").fadeIn(200).addClass("active");
                });
                $.post(ajaxurl, {
                    action: 'sga_get_student_profile_data',
                    _ajax_nonce: '<?php echo wp_create_nonce("sga_get_profile_nonce"); ?>',
                    student_id: studentId
                }).done(function(response) {
                    if (response.success) {
                        profileContainer.html(response.data.html);
                    } else {
                        profileContainer.html('<div class="sga-profile-error">Error al cargar el perfil: ' + response.data.message + '</div>');
                    }
                }).fail(function() {
                    profileContainer.html('<div class="sga-profile-error">Error de comunicación con el servidor.</div>');
                });
            });
            $("#gestion-academica-app-container").on("click", "#sga-profile-back-btn", function() {
                $(".panel-view.active").fadeOut(200, function() {
                    $(this).removeClass("active");
                    $("#panel-view-estudiantes").fadeIn(200).addClass("active");
                });
            });
            $("#gestion-academica-app-container").on("click", "#sga-profile-save-btn", function() {
                var btn = $(this);
                btn.text('Guardando...').prop('disabled', true);
                var studentId = btn.data('student-id');
                var profileData = {
                    nombre_completo: $('#sga-profile-nombre_completo').val(),
                    cedula: $('#sga-profile-cedula').val(),
                    email: $('#sga-profile-email').val(),
                    telefono: $('#sga-profile-telefono').val(),
                    direccion: $('#sga-profile-direccion').val(),
                    cursos: []
                };
                $('#sga-profile-cursos-tbody tr').each(function() {
                    var row = $(this);
                    profileData.cursos.push({
                        row_index: row.data('row-index'),
                        estado: row.find('.sga-profile-curso-estado').val()
                    });
                });
                $.post(ajaxurl, {
                    action: 'sga_update_student_profile_data',
                    _ajax_nonce: '<?php echo wp_create_nonce("sga_update_profile_nonce"); ?>',
                    student_id: studentId,
                    profile_data: profileData
                }).done(function(response) {
                    if (response.success) {
                        alert('Perfil actualizado correctamente.');
                    } else {
                        alert('Error al guardar: ' + response.data.message);
                    }
                    btn.text('Guardar Cambios').prop('disabled', false);
                }).fail(function() {
                    alert('Error de comunicación al guardar.');
                    btn.text('Guardar Cambios').prop('disabled', false);
                });
            });
            $('#sga-email-recipient-group').on('change', function() {
                var selectedGroup = $(this).val();
                if (selectedGroup === 'por_curso') {
                    $('#sga-curso-selector-group').slideDown();
                    $('#sga-estudiantes-especificos-group').slideUp();
                } else if (selectedGroup === 'especificos') {
                    $('#sga-curso-selector-group').slideUp();
                    $('#sga-estudiantes-especificos-group').slideDown();
                } else {
                    $('#sga-curso-selector-group').slideUp();
                    $('#sga-estudiantes-especificos-group').slideUp();
                }
            });
            $('#sga-estudiantes-search').on('keyup', function() {
                var searchTerm = $(this).val().toLowerCase();
                $('#sga-estudiantes-checkbox-list .sga-student-item').each(function() {
                    var itemText = $(this).data('search-term');
                    if (itemText.includes(searchTerm)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
            $('#sga-send-bulk-email-btn').on('click', function() {
                var btn = $(this);
                var statusDiv = $('#sga-email-status');
                var editorContent = typeof tinymce !== 'undefined' && tinymce.get('sga-email-body') ? tinymce.get('sga-email-body').getContent() : $('#sga-email-body').val();

                if (!$('#sga-email-subject').val() || !editorContent) {
                    alert('Por favor, completa el asunto y el mensaje.');
                    return;
                }

                btn.prop('disabled', true).siblings('.spinner').addClass('is-active');
                statusDiv.hide().removeClass('success error');
                
                var recipientGroup = $('#sga-email-recipient-group').val();
                var postData = {
                    action: 'sga_send_bulk_email',
                    _ajax_nonce: '<?php echo wp_create_nonce("sga_send_bulk_email_nonce"); ?>',
                    recipient_group: recipientGroup,
                    curso: $('#sga-email-curso-select').val(),
                    subject: $('#sga-email-subject').val(),
                    body: editorContent,
                };

                if (recipientGroup === 'especificos') {
                    var selectedStudents = [];
                    $('.sga-specific-student-checkbox:checked').each(function() {
                        selectedStudents.push($(this).val());
                    });

                    if (selectedStudents.length === 0) {
                        alert('Por favor, selecciona al menos un estudiante.');
                        btn.prop('disabled', false).siblings('.spinner').removeClass('is-active');
                        return;
                    }
                    postData.student_ids = JSON.stringify(selectedStudents);
                }

                $.post(ajaxurl, postData).done(function(response) {
                    if (response.success) {
                        statusDiv.addClass('success').html(response.data.message).slideDown();
                        $('#sga-email-subject').val('');
                        if (typeof tinymce !== 'undefined' && tinymce.get('sga-email-body')) {
                            tinymce.get('sga-email-body').setContent('');
                        } else {
                            $('#sga-email-body').val('');
                        }
                    } else {
                        statusDiv.addClass('error').html('Error: ' + response.data.message).slideDown();
                    }
                }).fail(function() {
                    statusDiv.addClass('error').html('Error de comunicación con el servidor.').slideDown();
                }).always(function() {
                    btn.prop('disabled', false).siblings('.spinner').removeClass('is-active');
                });
            });
        });
        </script>
        <?php
    }
}
