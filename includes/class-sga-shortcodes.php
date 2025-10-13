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
                <div id="panel-view-reportes" class="panel-view"><?php $this->render_view_reportes(); ?></div>
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
            <?php
            $options = get_option('sga_payment_options');
            if (isset($options['enable_online_payments']) && $options['enable_online_payments'] == 1) :
            ?>
            <a href="#" data-view="registro_pagos" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
                <h2>Pagos</h2>
                <p>Consultar historial de pagos</p>
            </a>
            <?php endif; ?>
            <a href="#" data-view="comunicacion" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
                <h2>Comunicación</h2>
                <p>Enviar correos masivos</p>
            </a>
            <a href="#" data-view="reportes" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20V16"/></svg></div>
                <h2>Reportes</h2>
                <p>Visualizar y generar informes</p>
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
        
        $integration_options = get_option('sga_integration_options', []);
        $matriculacion_desactivada = !empty($integration_options['disable_auto_enroll']) && $integration_options['disable_auto_enroll'] == 1;
        ?>
        <a href="#" data-view="matriculacion" class="back-link panel-nav-link">&larr; Volver a Matriculación</a>
        <h1 class="panel-title">
            <?php echo $matriculacion_desactivada ? 'Seguimiento de Inscripciones (Llamadas)' : 'Aprobar Inscripciones Pendientes'; ?>
        </h1>

        <div class="filtros-tabla">
            <?php if (!$matriculacion_desactivada): ?>
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
            <?php if ($matriculacion_desactivada): ?>
            <select id="filtro-estado-llamada">
                <option value="">Todos los Estados de Llamada</option>
                <option value="pendiente">Pendiente</option>
                <option value="contactado">Contactado</option>
                <option value="no_contesta">No Contesta</option>
                <option value="numero_incorrecto">Número Incorrecto</option>
                <option value="rechazado">Rechazado</option>
            </select>
            <?php endif; ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sga_dashboard')); ?>" class="button button-secondary" target="_blank">Gestión Avanzada</a>
        </div>

        <div class="tabla-wrapper">
            <table class="wp-list-table widefat striped" id="tabla-pendientes">
                <thead>
                    <tr>
                        <?php if (!$matriculacion_desactivada): ?>
                        <th class="ga-check-column"><input type="checkbox" id="select-all-pendientes"></th>
                        <?php endif; ?>
                        <th>Nombre</th><th>Cédula</th><th>Email</th><th>Teléfono</th><th>Curso</th><th>Horario</th><th>Estado</th>
                        <?php if ($matriculacion_desactivada): ?>
                            <th>Estado de Llamada</th>
                        <?php endif; ?>
                        <th>Acción</th>
                    </tr>
                </thead>
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
                                        $current_call_status = 'pendiente';
                                        if ($matriculacion_desactivada) {
                                            $call_statuses = get_post_meta($estudiante->ID, '_sga_call_statuses', true);
                                            if (!is_array($call_statuses)) $call_statuses = [];
                                            $current_call_status = $call_statuses[$index] ?? 'pendiente';
                                        }
                                        ?>
                                        <tr data-curso="<?php echo esc_attr($curso['nombre_curso']); ?>" data-call-status="<?php echo esc_attr($current_call_status); ?>">
                                            <?php if (!$matriculacion_desactivada): ?>
                                            <td class="ga-check-column"><input type="checkbox" class="bulk-checkbox" data-postid="<?php echo $estudiante->ID; ?>" data-rowindex="<?php echo $index; ?>" data-cedula="<?php echo esc_attr(get_field('cedula', $estudiante->ID)); ?>" data-nombre="<?php echo esc_attr($estudiante->post_title); ?>"></td>
                                            <?php endif; ?>
                                            <td><?php echo esc_html($estudiante->post_title); ?></td>
                                            <td><?php echo esc_html(get_field('cedula', $estudiante->ID)); ?></td>
                                            <td><?php echo esc_html(get_field('email', $estudiante->ID)); ?></td>
                                            <td><?php echo esc_html(get_field('telefono', $estudiante->ID)); ?></td>
                                            <td><?php echo esc_html($curso['nombre_curso']); ?></td>
                                            <td><?php echo esc_html($curso['horario']); ?></td>
                                            <td><span class="estado-inscrito">Inscrito</span></td>
                                            <?php if ($matriculacion_desactivada): ?>
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
                                            <?php endif; ?>
                                            <td>
                                                <?php if ($matriculacion_desactivada): ?>
                                                    <?php
                                                    $call_log = get_post_meta($estudiante->ID, '_sga_call_log', true);
                                                    if (!is_array($call_log)) $call_log = [];
                                                    $call_info = $call_log[$index] ?? null;

                                                    if ($call_info) {
                                                        echo 'Llamado por <strong>' . esc_html($call_info['user_name']) . '</strong><br><small>' . esc_html(date_i18n('d/m/Y H:i', $call_info['timestamp'])) . '</small>';
                                                    } else {
                                                        ?>
                                                        <button class="button button-secondary sga-marcar-llamado-btn" data-postid="<?php echo $estudiante->ID; ?>" data-rowindex="<?php echo $index; ?>" data-nonce="<?php echo wp_create_nonce('sga_marcar_llamado_' . $estudiante->ID . '_' . $index); ?>">Marcar como Llamado</button>
                                                        <?php
                                                    }
                                                    ?>
                                                <?php else: ?>
                                                    <button class="button button-primary aprobar-btn" data-postid="<?php echo $estudiante->ID; ?>" data-rowindex="<?php echo $index; ?>" data-cedula="<?php echo esc_attr(get_field('cedula', $estudiante->ID)); ?>" data-nombre="<?php echo esc_attr($estudiante->post_title); ?>" data-nonce="<?php echo wp_create_nonce('aprobar_nonce'); ?>">
                                                        Aprobar
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                }
                            }
                        }
                    }
                    if (!$hay_pendientes) {
                        $colspan = 9;
                        echo '<tr class="no-results"><td colspan="' . $colspan . '">No hay estudiantes pendientes de aprobación.</td></tr>';
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
        // Fetch courses with both 'publish' and 'private' statuses
        $cursos_activos = get_posts(array(
            'post_type' => 'curso',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => array('publish', 'private')
        ));
        // Fetch all categories (Escuelas)
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
            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=curso')); ?>" target="_blank" class="button button-primary">Nuevo Curso</a>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=curso')); ?>" target="_blank" class="button button-secondary">Editar Cursos</a>
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
                            <a href="<?php echo get_edit_post_link($curso->ID); ?>" class="button" target="_blank">Editar</a>
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
                                <a href="<?php echo get_edit_post_link($curso->ID); ?>" class="button" target="_blank">Editar</a>
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

    public function render_view_reportes() {
        $options = get_option('sga_report_options', [
            'recipient_email' => get_option('admin_email'),
            'enable_weekly' => 0,
            'enable_monthly' => 0,
        ]);
        $cursos = get_posts(array('post_type' => 'curso', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
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
                                    <option value="matriculados">Estudiantes Matriculados</option>
                                    <option value="pendientes">Inscripciones Pendientes</option>
                                    <option value="cursos">Lista de Cursos Activos</option>
                                    <option value="log">Registro de Actividad</option>
                                    <option value="payment_history">Historial de Pagos</option>
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
                            <button type="submit" name="report_action" value="email" class="button button-secondary">Enviar por Correo</button>
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
                <a href="#" data-view="log" class="panel-card panel-nav-link">
                    <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22h6a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v5"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M5 17a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/><path d="M5 17v-2.5"/><path d="M5 12V2"/></svg></div>
                    <h2>Registro de Actividad</h2>
                    <p>Consultar el log del sistema</p>
                </a>
            </div>
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
            .ga-check-column { width: 2.2em; }
            
            /* Status Pills */
            .estado-inscrito { color: var(--sga-yellow); background-color: #fffbeb; padding: 4px 10px; border-radius: 999px; font-weight: 500; font-size: 12px; }
            .ga-pill { display: inline-block; padding: 4px 10px; font-size: 12px; font-weight: 500; border-radius: 16px; color: var(--sga-white); }
            .ga-pill-time { background-color: var(--sga-text-light); } .ga-pill-presencial { background-color: var(--sga-blue); }
            .ga-pill-virtual { background-color: var(--sga-purple); } .ga-pill-hibrido { background-color: var(--sga-pink); }
            .ga-pill-publico { background-color: var(--sga-green); margin-right: 15px;} .ga-pill-privado { background-color: var(--sga-text-light); margin-right: 15px;}
            
            /* Course View Switcher */
            .view-switcher { display: flex; gap: 5px; background-color: var(--sga-gray); padding: 4px; border-radius: 8px; margin-left: auto; }
            .view-btn { background-color: transparent; border: none; padding: 6px 8px; cursor: pointer; border-radius: 6px; color: var(--sga-text-light); transition: all 0.2s ease; }
            .view-btn:hover { color: var(--sga-primary); background-color: #fff; }
            .view-btn.active { background-color: var(--sga-white); color: var(--sga-secondary); box-shadow: var(--shadow-sm); }
            .view-btn svg { width: 20px; height: 20px; display: block; }

            /* Course Cards Redesign */
            .cursos-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 25px; }
            .curso-card { background-color: var(--sga-white); border: 1px solid var(--sga-gray); border-radius: 12px; box-shadow: var(--shadow-md); transition: all 0.3s ease; }
            .curso-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
            .curso-card-header { background-color: var(--sga-light); padding: 15px 20px; border-bottom: 1px solid var(--sga-gray); display: flex; justify-content: space-between; align-items: center; border-top-left-radius: 12px; border-top-right-radius: 12px; }
            .curso-card-title-wrapper { display: flex; align-items: center; gap: 10px; }
            .curso-card-header h3 { margin: 0; font-size: 18px; color: var(--sga-primary); }
            .curso-card-actions { display: flex; gap: 10px; }
            .curso-card-body { padding: 20px; }
            .curso-details-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px; font-size: 14px; }
            .curso-details-grid div { display: flex; flex-direction: column; }
            .curso-details-grid span { color: var(--sga-text-light); font-size: 12px; margin-bottom: 2px; }
            .horarios-section h4 { font-size: 16px; color: var(--sga-text); margin-top: 0; margin-bottom: 10px; border-top: 1px solid var(--sga-gray); padding-top: 15px; }
            .horarios-list { list-style: none; margin: 0; padding: 0; }
            .horarios-list li { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px dashed var(--sga-gray); }
            .horarios-list li:last-child { border-bottom: none; }
            .horario-info { display: flex; align-items: center; gap: 8px; }
            .horario-dia-hora { font-weight: 500; }
            .horario-cupos span { color: var(--sga-text-light); }

            /* Course List View */
            .cursos-list-view { display: none; }
            #tabla-cursos-lista .ga-pill { font-size: 10px; padding: 3px 8px; }
            #tabla-cursos-lista .curso-details-list { list-style: none; margin: 0; padding: 0; font-size: 13px; }
            #tabla-cursos-lista .curso-details-list li { margin-bottom: 5px; }
            #tabla-cursos-lista .curso-details-list li span { font-weight: 600; color: var(--sga-text); }
            #tabla-cursos-lista .horarios-list-inline { list-style: none; margin: 0; padding: 0; }
            #tabla-cursos-lista .horarios-list-inline li { padding: 8px 0; border-bottom: 1px dashed var(--sga-gray); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
            #tabla-cursos-lista .horarios-list-inline li:last-child { border-bottom: none; }


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

            /* Reports View */
            .report-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; align-items: flex-start; }
            .report-main-content { display: flex; flex-direction: column; gap: 30px; }
            .report-sidebar { display: flex; flex-direction: column; gap: 30px; }
            .sga-card { background-color: var(--sga-light); border-radius: 12px; padding: 25px; border: 1px solid var(--sga-gray); }
            .sga-card h3 { margin-top: 0; font-size: 18px; font-weight: 600; color: var(--sga-primary); border-bottom: 1px solid var(--sga-gray); padding-bottom: 10px; margin-bottom: 20px; }
            .report-form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px; }
            .sga-form-group label { display: block; font-weight: 500; margin-bottom: 8px; font-size: 14px; color: var(--sga-text-light); }
            .sga-form-group input, .sga-form-group select { width: 100%; }
            .sga-form-group .description { font-size: 12px; color: var(--sga-text-light); margin-top: 5px; }
            .checkbox-label { display: flex; align-items: center; gap: 8px; font-weight: 500; }
            .report-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px; }
            .chart-container { position: relative; height: 300px; }
            @media (max-width: 960px) { .report-grid { grid-template-columns: 1fr; } }
        </style>
        <?php
    }

    public function render_panel_navigation_js() {
        ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            jQuery(document).ready(function($) {
                var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
                var approvalData = {};
                var inscriptionsChart;

                function setDynamicDateTime() {
                    if (!$("#dynamic-date").length) return;
                    const now = new Date();
                    const dateFormatter = new Intl.DateTimeFormat('es-ES', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                    const timeFormatter = new Intl.DateTimeFormat('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
                    let formattedDate = dateFormatter.format(now);
                    formattedDate = formattedDate.charAt(0).toUpperCase() + formattedDate.slice(1);
                    const formattedTime = now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
                    $("#dynamic-date").text(formattedDate);
                    $("#dynamic-time").text(formattedTime);
                }
                setDynamicDateTime();
                setInterval(setDynamicDateTime, 1000);

                $("#gestion-academica-app-container").on("click", ".panel-nav-link", function(e) {
                    e.preventDefault();
                    var view = $(this).data("view");
                    var activePanel = $(".panel-view.active");
                    if (activePanel.is("#panel-view-" + view)) {
                        return;
                    }
                    activePanel.fadeOut(200, function() {
                        $(this).removeClass("active");
                        $("#panel-view-" + view).fadeIn(200).addClass("active");
                        if (view === 'reportes') {
                            renderInscriptionsChart();
                        }
                    });
                });

                $("#tabla-pendientes").on("click", ".aprobar-btn", function() {
                    var btn = $(this);
                    approvalData = {
                        type: 'single',
                        nonce: btn.data('nonce'),
                        post_id: btn.data('postid'),
                        row_index: btn.data('rowindex'),
                        cedula: btn.data('cedula'),
                        nombre: btn.data('nombre'),
                        element: btn
                    };
                    $("#ga-modal-confirmacion").fadeIn(200);
                });

                $("#apply-bulk-action").on("click", function() {
                    if ($("#bulk-action-select").val() !== 'aprobar') {
                        alert('Por favor, selecciona una acción válida.');
                        return;
                    }
                    var seleccionados = [];
                    $("#tabla-pendientes .bulk-checkbox:checked").each(function() {
                        var checkbox = $(this);
                        seleccionados.push({
                            post_id: checkbox.data('postid'),
                            row_index: checkbox.data('rowindex')
                        });
                    });
                    if (seleccionados.length > 0) {
                        approvalData = {
                            type: 'bulk',
                            nonce: '<?php echo wp_create_nonce("aprobar_bulk_nonce"); ?>',
                            seleccionados: seleccionados,
                            element: $(this)
                        };
                        $("#ga-modal-confirmacion").fadeIn(200);
                    } else {
                        alert('Por favor, selecciona al menos un estudiante.');
                    }
                });

                $("#ga-modal-confirmar").on("click", function() {
                    var btn = $(this);
                    btn.text('Procesando...').prop('disabled', true);
                    $("#ga-modal-cancelar").prop('disabled', true);

                    if (approvalData.type === 'single') {
                        $.post(ajaxurl, {
                            action: 'aprobar_para_matriculacion',
                            _ajax_nonce: approvalData.nonce,
                            post_id: approvalData.post_id,
                            row_index: approvalData.row_index,
                            cedula: approvalData.cedula,
                            nombre: approvalData.nombre
                        }).done(function(response) {
                            if (response.success) {
                                actualizarUIAprobacion(response.data);
                                approvalData.element.closest('tr').fadeOut(500, function() {
                                    $(this).remove();
                                    checkEmptyTable('#tabla-pendientes', 9, 'No hay estudiantes pendientes de aprobación.');
                                });
                            } else {
                                alert('Hubo un error: ' + (response.data || 'Error desconocido'));
                            }
                            closeModal();
                        }).fail(function(jqXHR, textStatus, errorThrown) {
                            console.error("AJAX Error:", textStatus, errorThrown);
                            alert('Hubo un error de comunicación con el servidor.');
                            closeModal();
                        });
                    } else if (approvalData.type === 'bulk') {
                        $.post(ajaxurl, {
                            action: 'aprobar_seleccionados',
                            _ajax_nonce: approvalData.nonce,
                            seleccionados: approvalData.seleccionados
                        }).done(function(response) {
                            if (response.success) {
                                if (response.data.approved && response.data.approved.length > 0) {
                                    response.data.approved.forEach(function(estudiante) {
                                        actualizarUIAprobacion(estudiante);
                                        $('#tabla-pendientes .bulk-checkbox[data-postid="' + estudiante.post_id + '"][data-rowindex="' + estudiante.row_index + '"]').closest('tr').fadeOut(500, function() {
                                            $(this).remove();
                                            checkEmptyTable('#tabla-pendientes', 9, 'No hay estudiantes pendientes de aprobación.');
                                        });
                                    });
                                }
                                if (response.data.failed && response.data.failed.length > 0) {
                                    var errorMsg = 'No se pudo aprobar a ' + response.data.failed.length + ' estudiante(s). Por favor, revisa la consola para más detalles o inténtalo de nuevo.';
                                    alert(errorMsg);
                                    console.log("Estudiantes no aprobados:", response.data.failed);
                                }
                            } else {
                                alert('Hubo un error al procesar la solicitud: ' + (response.data.message || 'Error desconocido'));
                            }
                            closeModal();
                            $("#select-all-pendientes").prop("checked", false);
                            $("#tabla-pendientes .bulk-checkbox").prop("checked", false);
                        }).fail(function(jqXHR, textStatus, errorThrown) {
                            console.error("AJAX Error:", textStatus, errorThrown);
                            alert('Hubo un error de comunicación con el servidor.');
                            closeModal();
                        });
                    }
                });

                $("#gestion-academica-app-container").on('change', '.sga-call-status-select', function(e) {
                    var select = $(this);
                    var post_id = select.data('postid');
                    var row_index = select.data('rowindex');
                    var nonce = select.data('nonce');
                    var status = select.val();
                    var spinner = select.next('.spinner');

                    select.prop('disabled', true);
                    spinner.addClass('is-active');

                    $.post(ajaxurl, {
                        action: 'sga_update_call_status',
                        _ajax_nonce: nonce,
                        post_id: post_id,
                        row_index: row_index,
                        status: status
                    }).done(function(response) {
                        if (!response.success) {
                            alert('Error: ' + (response.data.message || 'Error desconocido'));
                        } else {
                            // Update the data attribute on the row for live filtering
                            select.closest('tr').data('call-status', status);
                        }
                    }).fail(function() {
                        alert('Error de conexión.');
                    }).always(function() {
                        select.prop('disabled', false);
                        spinner.removeClass('is-active');
                    });
                });

                $("#tabla-pendientes").on("click", ".sga-marcar-llamado-btn", function() {
                    var btn = $(this);
                    var post_id = btn.data('postid');
                    var row_index = btn.data('rowindex');
                    var nonce = btn.data('nonce');
                    var cell = btn.parent(); // the <td>

                    btn.prop('disabled', true).text('Marcando...');

                    $.post(ajaxurl, {
                        action: 'sga_marcar_llamado',
                        _ajax_nonce: nonce,
                        post_id: post_id,
                        row_index: row_index,
                    }).done(function(response) {
                        if (response.success) {
                            cell.html(response.data.html);
                        } else {
                            alert('Error: ' + (response.data.message || 'Error desconocido'));
                            btn.prop('disabled', false).text('Marcar como Llamado');
                        }
                    }).fail(function() {
                        alert('Error de conexión.');
                        btn.prop('disabled', false).text('Marcar como Llamado');
                    });
                });

                function closeModal() {
                    $("#ga-modal-confirmacion").fadeOut(200);
                    $("#ga-modal-confirmar").text('Confirmar y Enviar').prop('disabled', false);
                    $("#ga-modal-cancelar").prop('disabled', false);
                    approvalData = {};
                }

                function actualizarUIAprobacion(data) {
                    var newRow = '<tr data-curso="' + data.nombre_curso + '">' +
                        '<td><strong>' + data.matricula + '</strong></td>' +
                        '<td>' + data.nombre + '</td>' +
                        '<td>' + data.cedula + '</td>' +
                        '<td>' + data.email + '</td>' +
                        '<td>' + data.telefono + '</td>' +
                        '<td>' + data.nombre_curso + '</td>' +
                        '</tr>';
                    $("#tabla-matriculados .no-results").remove();
                    $("#tabla-matriculados tbody").append(newRow);
                }

                function checkEmptyTable(tableId, colspan, message) {
                    if ($(tableId + ' tbody tr:not(.no-results-search)').length === 0 && !$(tableId + ' .no-results').length) {
                        $(tableId + ' tbody').append('<tr class="no-results"><td colspan="' + colspan + '">' + message + '</td></tr>');
                    }
                }

                $("#ga-modal-cancelar, .ga-modal").on("click", function(e) {
                    if (e.target == this || $(this).is("#ga-modal-cancelar")) {
                        closeModal();
                    }
                });

                $("#select-all-pendientes").on("click", function() {
                    $("#tabla-pendientes .bulk-checkbox").prop('checked', this.checked);
                });

                function filterPendientesTable() {
                    var searchTerm = $('#buscador-estudiantes-pendientes').val().toLowerCase();
                    var courseFilter = $('#filtro-curso-pendientes').val();
                    var callStatusFilter = $('#filtro-estado-llamada').val() || ''; 
                    var rowsFound = 0;

                    $('#tabla-pendientes tbody tr').each(function() {
                        var row = $(this);
                        if (row.hasClass('no-results') || row.hasClass('no-results-search')) {
                            return;
                        }

                        var rowText = row.text().toLowerCase();
                        var rowCourse = row.data('curso');
                        var rowCallStatus = row.data('call-status');

                        var matchesSearch = (searchTerm === '' || rowText.includes(searchTerm));
                        var matchesCourse = (courseFilter === '' || rowCourse === courseFilter);
                        var matchesCallStatus = (callStatusFilter === '' || rowCallStatus === callStatusFilter);

                        if (matchesSearch && matchesCourse && matchesCallStatus) {
                            row.show();
                            rowsFound++;
                        } else {
                            row.hide();
                        }
                    });

                    $('#tabla-pendientes .no-results-search').remove();
                    if (rowsFound === 0 && !$('#tabla-pendientes .no-results').is(':visible')) {
                        var colspan = $('#tabla-pendientes thead th').length;
                        $('#tabla-pendientes tbody').append('<tr class="no-results-search"><td colspan="' + colspan + '">No se encontraron resultados para los filtros aplicados.</td></tr>');
                    }
                }

                function filterTable(tableSelector, searchInputSelector, courseFilterSelector) {
                    var searchTerm = $(searchInputSelector).val().toLowerCase();
                    var courseFilter = courseFilterSelector ? $(courseFilterSelector).val() : '';
                    var rowsFound = 0;

                    $(tableSelector + ' tbody tr').each(function() {
                        var row = $(this);
                        if (row.hasClass('no-results') || row.hasClass('no-results-search')) {
                            return;
                        }

                        var rowText = row.text().toLowerCase();
                        var rowCourse = row.data('curso');

                        var matchesSearch = (searchTerm === '' || rowText.includes(searchTerm));
                        var matchesCourse = (!courseFilterSelector || courseFilter === '' || rowCourse === courseFilter);

                        if (matchesSearch && matchesCourse) {
                            row.show();
                            rowsFound++;
                        } else {
                            row.hide();
                        }
                    });

                    $(tableSelector + ' .no-results-search').remove();
                    if (rowsFound === 0 && !$(tableSelector + ' .no-results').is(':visible')) {
                        $(tableSelector + ' tbody').append('<tr class="no-results-search"><td colspan="100%">No se encontraron resultados para los filtros aplicados.</td></tr>');
                    }
                }
                
                function filterLogTable() {
                    var searchTerm = $('#buscador-log').val().toLowerCase();
                    var userFilter = $('#filtro-usuario-log').val();
                    var dateFrom = $('#filtro-fecha-inicio').val();
                    var dateTo = $('#filtro-fecha-fin').val();
                    var rowsFound = 0;

                    $('#tabla-log tbody tr').each(function() {
                        var row = $(this);
                        if (row.hasClass('no-results')) {
                            return;
                        }
                        var rowText = row.text().toLowerCase();
                        var rowUser = row.data('usuario');
                        var rowDate = row.data('fecha');

                        var matchesSearch = (searchTerm === '' || rowText.includes(searchTerm));
                        var matchesUser = (userFilter === '' || rowUser === userFilter);
                        var matchesDate = true;
                        
                        if(dateFrom && dateTo) {
                            matchesDate = rowDate >= dateFrom && rowDate <= dateTo;
                        } else if (dateFrom) {
                            matchesDate = rowDate >= dateFrom;
                        } else if (dateTo) {
                            matchesDate = rowDate <= dateTo;
                        }

                        if (matchesSearch && matchesUser && matchesDate) {
                            row.show();
                            rowsFound++;
                        } else {
                            row.hide();
                        }
                    });
                    
                    $('#tabla-log .no-results-search').remove();
                    if (rowsFound === 0 && !$('#tabla-log .no-results').is(':visible')) {
                         $('#tabla-log tbody').append('<tr class="no-results-search"><td colspan="4">No se encontraron resultados para los filtros aplicados.</td></tr>');
                    }
                }

                // --- Course Filter Logic ---
                function filterCourses() {
                    var searchTerm = $('#buscador-cursos').val().toLowerCase();
                    var escuelaFilter = $('#filtro-escuela-cursos').val();
                    var visibilidadFilter = $('#filtro-visibilidad-cursos').val();

                    $('.curso-card, #tabla-cursos-lista tbody tr').each(function() {
                        var element = $(this);
                        if (element.hasClass('no-results')) return;

                        var elementText = element.data('search-term');
                        var elementEscuelas = element.data('escuela').split(' ');
                        var elementVisibilidad = element.data('visibilidad');

                        var matchesSearch = (searchTerm === '' || elementText.includes(searchTerm));
                        var matchesEscuela = (escuelaFilter === '' || elementEscuelas.includes(escuelaFilter));
                        var matchesVisibilidad = (visibilidadFilter === '' || elementVisibilidad === visibilidadFilter);

                        if (matchesSearch && matchesEscuela && matchesVisibilidad) {
                            element.show();
                        } else {
                            element.hide();
                        }
                    });
                }
                $("#buscador-cursos, #filtro-escuela-cursos, #filtro-visibilidad-cursos").on("keyup change", filterCourses);

                $("#buscador-estudiantes-pendientes, #filtro-curso-pendientes, #filtro-estado-llamada").on("keyup change", function() { filterPendientesTable(); });
                $("#buscador-matriculados, #filtro-curso-matriculados").on("keyup change", function() { filterTable('#tabla-matriculados', '#buscador-matriculados', '#filtro-curso-matriculados'); });
                $("#buscador-general-estudiantes").on("keyup", function() { filterTable('#tabla-general-estudiantes', '#buscador-general-estudiantes', null); });
                
                $("#buscador-log, #filtro-usuario-log, #filtro-fecha-inicio, #filtro-fecha-fin").on("keyup change", function() { filterLogTable(); });

                $("#exportar-btn").on("click", function(e) {
                    e.preventDefault();
                    var format = $('#export-format-select').val();
                    var searchTerm = $('#buscador-matriculados').val();
                    var courseFilter = $('#filtro-curso-matriculados').val();
                    var nonce = '<?php echo wp_create_nonce("export_nonce"); ?>';
                    var url = new URL(ajaxurl);
                    url.searchParams.append('action', format === 'excel' ? 'exportar_excel' : 'exportar_moodle_csv');
                    url.searchParams.append('_wpnonce', nonce);
                    url.searchParams.append('search_term', searchTerm);
                    url.searchParams.append('course_filter', courseFilter);
                    window.location.href = url.href;
                });

                $("#panel-view-cursos").on("click", ".ver-matriculados-btn", function(e) {
                    e.preventDefault();
                    var courseName = $(this).data('curso-nombre');
                    $('.panel-view').removeClass('active').hide();
                    $('#panel-view-lista_matriculados').addClass('active').show();
                    $('#filtro-curso-matriculados').val(courseName).change();
                    $('#buscador-matriculados').val('');
                });
                
                $("#buscador-pagos").on("keyup", function() { filterTable('#tabla-pagos', '#buscador-pagos', null); });

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

                // Reports View Logic
                $('#report_type').on('change', function() {
                    const reportType = $(this).val();
                    const courseFilter = $('#report-curso-filter-container');
                    if (reportType === 'matriculados' || reportType === 'pendientes') {
                        courseFilter.slideDown();
                    } else {
                        courseFilter.slideUp();
                    }
                });
                
                $('.view-switcher').on('click', '.view-btn', function(e) {
                    e.preventDefault();
                    var $this = $(this);
                    if ($this.hasClass('active')) {
                        return;
                    }

                    var targetView = $this.data('view');
                    
                    $('.view-btn').removeClass('active');
                    $this.addClass('active');

                    if (targetView === 'grid') {
                        $('.cursos-list-view').fadeOut(200, function() {
                            $('.cursos-grid').fadeIn(200);
                        });
                    } else {
                        $('.cursos-grid').fadeOut(200, function() {
                            $('.cursos-list-view').fadeIn(200);
                        });
                    }
                });

                function renderInscriptionsChart() {
                    if (inscriptionsChart) {
                        inscriptionsChart.destroy();
                    }
                    var ctx = document.getElementById('inscriptionsChart').getContext('2d');
                    var chartContainer = $('.chart-container');
                    
                    chartContainer.html('<canvas id="inscriptionsChart"></canvas><div class="chart-loading" style="position:absolute;top:0;left:0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.7);"><span class="spinner is-active"></span></div>');
                    ctx = document.getElementById('inscriptionsChart').getContext('2d');

                    $.post(ajaxurl, {
                        action: 'sga_get_report_chart_data',
                        _ajax_nonce: '<?php echo wp_create_nonce("sga_chart_nonce"); ?>'
                    }).done(function(response) {
                        chartContainer.find('.chart-loading').remove();
                        if(response.success) {
                            const data = {
                                labels: response.data.labels,
                                datasets: [{
                                    label: 'Inscripciones por Mes',
                                    data: response.data.data,
                                    backgroundColor: 'rgba(79, 70, 229, 0.2)',
                                    borderColor: 'rgba(79, 70, 229, 1)',
                                    borderWidth: 2,
                                    tension: 0.4,
                                    fill: true
                                }]
                            };
                            inscriptionsChart = new Chart(ctx, {
                                type: 'line',
                                data: data,
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: { 
                                        y: { 
                                            beginAtZero: true,
                                            ticks: {
                                                stepSize: 1 
                                            }
                                        } 
                                    }
                                }
                            });
                        } else {
                            chartContainer.html('<p style="text-align:center;color:var(--sga-red);">No se pudieron cargar los datos del gráfico.</p>');
                        }
                    }).fail(function() {
                        chartContainer.find('.chart-loading').remove();
                        chartContainer.html('<p style="text-align:center;color:var(--sga-red);">Error al contactar al servidor para los datos del gráfico.</p>');
                    });
                }
            });
        </script>
        <?php
    }
}

