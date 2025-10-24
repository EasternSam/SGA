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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_shortcode_assets'));
    }

    /**
     * Encola los estilos y scripts específicos para el shortcode del panel.
     */
    public function enqueue_shortcode_assets() {
        // Solo encolar si el shortcode está en la página actual.
        if (has_shortcode(get_post(get_the_ID())->post_content, 'panel_gestion_academica')) {
            // Estilos CSS
            wp_enqueue_style('sga-panel-styles', SGA_PLUGIN_URL . 'assets/sga-panel-styles.css', array(), SGA_PLUGIN_VERSION);
            
            // Scripts JS
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array('jquery'), null, true);
            wp_enqueue_script('sga-panel-scripts', SGA_PLUGIN_URL . 'assets/sga-panel-scripts.js', array('jquery', 'chart-js'), SGA_PLUGIN_VERSION, true);

            // Pasar datos de configuración a JS
            $agents = SGA_Utils::_get_sga_agents();
            $agents_for_js = [];
            foreach($agents as $agent) {
                $agents_for_js[] = ['id' => $agent->ID, 'name' => $agent->display_name];
            }

            wp_localize_script('sga-panel-scripts', 'sgaPanelData', array(
                'ajaxurl'       => admin_url('admin-ajax.php'),
                'nonceGetView'  => wp_create_nonce('sga_get_view_nonce'),
                'nonceApprove'  => wp_create_nonce('aprobar_nonce'),
                'nonceApproveBulk' => wp_create_nonce('aprobar_bulk_nonce'),
                'nonceChart'    => wp_create_nonce('sga_chart_nonce'),
                'nonceUpdateProfile' => wp_create_nonce('sga_update_profile_nonce'),
                'nonceGetProfile' => wp_create_nonce('sga_get_profile_nonce'),
                'nonceBulkEmail' => wp_create_nonce('sga_send_bulk_email_nonce'),
                'nonceCallStatus' => wp_create_nonce('sga_update_call_status_'), // Base nonce
                'nonceMarcarLlamado' => wp_create_nonce('sga_marcar_llamado_'), // Base nonce
                'nonceExport'   => wp_create_nonce('export_nonce'),
                'nonceExportCalls' => wp_create_nonce('export_calls_nonce'),
                'nonceDistribute' => wp_create_nonce('sga_distribute_nonce'),
                'sgaAgents'     => $agents_for_js,
                'isAgent'       => $this->sga_user_has_role(['agente'])
            ));
        }
    }


    /**
     * Helper para verificar si el usuario actual tiene uno de los roles especificados.
     * @param array $roles_to_check Array de slugs de roles a verificar.
     * @return bool
     */
    private function sga_user_has_role($roles_to_check) {
        $user = wp_get_current_user();
        if (!$user->ID) return false;
        $user_roles = (array) $user->roles;
        foreach ((array) $roles_to_check as $role) {
            if (in_array($role, $user_roles)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Renderiza el shortcode del panel de gestión principal.
     * Carga las diferentes vistas (paneles) desde la clase.
     */
    public function render_panel() {
        if (!is_user_logged_in() || !$this->sga_user_has_role(['administrator', 'gestor_academico', 'agente', 'gestor_de_cursos'])) {
            return '<div class="notice notice-error" style="margin: 20px;"><p>No tienes los permisos necesarios para acceder a este panel. Por favor, contacta a un administrador.</p></div>';
        }

        ob_start();
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

        <div id="ga-modal-comentario-llamada" class="ga-modal" style="display:none;">
            <div class="ga-modal-content">
                <h4>Añadir Comentario a la Llamada</h4>
                <p>Puedes añadir una nota o comentario sobre esta llamada (opcional).</p>
                <textarea id="sga-comentario-llamada-texto" placeholder="Escribe tu comentario aquí..." rows="4" style="width: 100%;"></textarea>
                <div class="ga-modal-actions">
                    <button id="ga-modal-comentario-cancelar" class="button button-secondary">Cancelar</button>
                    <button id="ga-modal-comentario-guardar" class="button button-primary">Marcar y Guardar</button>
                </div>
            </div>
        </div>
        
        <div id="ga-modal-repartir-agentes" class="ga-modal" style="display:none;">
            <div class="ga-modal-content">
                <h4>Repartir Inscripciones</h4>
                <p>Selecciona los agentes entre los cuales quieres repartir las inscripciones pendientes no asignadas.</p>
                <div id="sga-distribute-agent-list" style="text-align: left; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-bottom: 20px;">
                    <!-- La lista de agentes se cargará aquí vía JS -->
                </div>
                <div class="ga-modal-actions">
                    <button id="ga-modal-repartir-cancelar" class="button button-secondary">Cancelar</button>
                    <button id="ga-modal-repartir-confirmar" class="button button-primary">Confirmar Reparto</button>
                </div>
            </div>
        </div>


        <div id="gestion-academica-app-container">
            <div class="gestion-academica-wrapper">
                <div id="sga-panel-loader" style="display: none;">
                    <div style="text-align:center; color: var(--sga-primary); font-weight: 600;">
                        <span class="spinner is-active" style="float:none; width:auto; height:auto; margin-bottom: 10px;"></span>
                        <p>Cargando...</p>
                    </div>
                </div>
                <div id="panel-view-principal" class="panel-view active"><?php $this->render_view_principal(); ?></div>
                <div id="panel-view-matriculacion" class="panel-view"><?php $this->render_view_matriculacion(); ?></div>
                <div id="panel-view-enviar_a_matriculacion" class="panel-view"><?php $this->render_view_enviar_a_matriculacion(); ?></div>
                <div id="panel-view-lista_matriculados" class="panel-view"><?php $this->render_view_lista_matriculados(); ?></div>
                <div id="panel-view-registro_llamadas" class="panel-view"><?php $this->render_view_registro_llamadas(); ?></div>
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
        $inscripciones_pendientes = SGA_Utils::_get_pending_inscriptions_count();
        $llamadas_pendientes = SGA_Utils::_get_pending_calls_count();
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
                <div class="stat-icon calls">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $llamadas_pendientes; ?></span>
                    <span class="stat-label">Pendientes a Llamar</span>
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
            <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico', 'agente'])) : ?>
            <a href="#" data-view="matriculacion" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12c2.2 0 4-1.8 4-4s-1.8-4-4-4-4 1.8-4 4 1.8 4 4 4z"/><path d="M20.6 20.4c-.4-3.3-3.8-5.9-8.6-5.9s-8.2 2.6-8.6 5.9"/><path d="M18 18.2c.4-.2.9-.4 1.3-.7"/><path d="M22 13.8c0-.6-.1-1.2-.3-1.8"/><path d="M11.3 2.2c-.4.2-.8.4-1.2.7"/><path d="M2 13.8c0 .6.1 1.2.3 1.8"/><path d="M4.7 17.5c-.4.3-.8.5-1.3.7"/><path d="M12.7 21.8c.4-.2.8-.4 1.2-.7"/></svg></div>
                <h2>Matriculación</h2>
                <p>Aprobar y gestionar matrículas</p>
            </a>
            <?php endif; ?>
            <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico'])) : ?>
            <a href="#" data-view="estudiantes" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                <h2>Estudiantes</h2>
                <p>Ver y editar perfiles</p>
            </a>
            <?php endif; ?>
            <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico', 'gestor_de_cursos', 'agente'])) : ?>
            <a href="#" data-view="cursos" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg></div>
                <h2>Cursos</h2>
                <p>Administrar cursos y horarios</p>
            </a>
            <?php endif; ?>
            <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico'])) : ?>
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
            <?php endif; ?>
            <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico', 'gestor_de_cursos', 'agente'])) : ?>
            <a href="#" data-view="reportes" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20V16"/></svg></div>
                <h2>Reportes</h2>
                <p>Visualizar y generar informes</p>
            </a>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_view_matriculacion() {
        ?>
        <a href="#" data-view="principal" class="back-link panel-nav-link">&larr; Volver al Panel Principal</a>
        <h1 class="panel-title">Sistema de Matriculación</h1>
        <div class="panel-grid">
            <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico'])) : // Botón de Aprobar ?>
            <a href="#" data-view="enviar_a_matriculacion" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/></svg></div>
                <h2>Aprobar Inscripciones</h2>
                <p>Validar y matricular nuevos estudiantes</p>
            </a>
            <?php else: // Botón de Seguimiento para Agente ?>
            <a href="#" data-view="enviar_a_matriculacion" class="panel-card panel-nav-link">
                 <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg></div>
                <h2>Seguimiento de Inscripciones</h2>
                <p>Contactar a estudiantes inscritos</p>
            </a>
            <?php endif; ?>
            <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico'])) : ?>
            <a href="#" data-view="lista_matriculados" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></div>
                <h2>Lista de Matriculados</h2>
                <p>Consultar y exportar estudiantes activos</p>
            </a>
            <?php endif; ?>
             <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico', 'agente'])) : ?>
            <a href="#" data-view="registro_llamadas" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path><path d="M14.05 2a9 9 0 0 1 8 7.94"></path><path d="M14.05 6A5 5 0 0 1 18 10"></path></svg></div>
                <h2>Registro de Llamadas</h2>
                <p>Consultar historial de llamadas</p>
            </a>
             <?php endif; ?>
            <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico', 'agente'])) : ?>
            <a href="<?php echo esc_url(site_url('/cursos/')); ?>" target="_blank" class="panel-card">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></div>
                <h2>Nueva Inscripción</h2>
                <p>Inscribir un estudiante manualmente</p>
            </a>
            <?php endif; ?>
        </div>
        <?php
    }

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
}
