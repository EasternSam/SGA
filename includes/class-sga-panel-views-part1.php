<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_Panel_Views_Part1
 *
 * Contiene la estructura básica, el helper de roles, las vistas principales y los métodos
 * de estilos/scripts que deben ser cargados primero.
 */
class SGA_Panel_Views_Part1 {

    /**
     * Helper para verificar si el usuario actual tiene uno de los roles especificados.
     * @param array $roles_to_check Array de slugs de roles a verificar.
     * @return bool
     */
    protected function sga_user_has_role($roles_to_check) {
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
                <!-- Se espera que el JS (cargado abajo) complete estos IDs -->
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
            <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico', 'agente', 'agente_infotep'])) : ?>
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
            <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico', 'gestor_de_cursos', 'agente', 'agente_infotep'])) : ?>
            <a href="#" data-view="cursos" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg></div>
                <h2>Cursos</h2>
                <p>Administrar cursos y horarios</p>
            </a>
            <?php endif; ?>
            <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico'])) : ?>
            <?php
            $options = get_option('sga_payment_options');
            if (isset($options['enable_online_payments']) && $options->enable_online_payments == 1) :
            ?>
            <a href="#" data-view="registro_pagos" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
                <h2>Pagos</h2>
                <p>Consultar historial de pagos</p>
            </a>
            <?php endif; ?>
            <?php endif; ?>
            <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico', 'gestor_de_cursos'])) : ?>
            <a href="#" data-view="comunicacion" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
                <h2>Comunicación</h2>
                <p>Enviar correos masivos</p>
            </a>
            <?php endif; ?>
            <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico', 'gestor_de_cursos', 'agente', 'agente_infotep'])) : ?>
            <a href="#" data-view="reportes" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20V16"/></svg></div>
                <h2>Reportes</h2>
                <p>Visualizar y generar informes</p>
            </a>
            <?php endif; ?>
        </div>
        <?php
    }

    // --- VISTA DE MATRICULACIÓN (Menú Secundario) ---
    
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
            <?php else: // Botón de Seguimiento para Agente (y Agente Infotep) ?>
            <a href="#" data-view="enviar_a_matriculacion" class="panel-card panel-nav-link">
                 <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path><path d="M14.05 2a9 9 0 0 1 8 7.94"></path><path d="M14.05 6A5 5 0 0 1 18 10"></path></svg></div>
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
             <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico', 'agente', 'agente_infotep'])) : ?>
            <a href="#" data-view="registro_llamadas" class="panel-card panel-nav-link">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path><path d="M14.05 2a9 9 0 0 1 8 7.94"></path><path d="M14.05 6A5 5 0 0 1 18 10"></path></svg></div>
                <h2>Registro de Llamadas</h2>
                <p>Consultar historial de llamadas</p>
            </a>
             <?php endif; ?>
            <?php if ($this->sga_user_has_role(['administrator', 'gestor_academico', 'agente', 'agente_infotep'])) : ?>
            <a href="<?php echo esc_url(site_url('/cursos/')); ?>" target="_blank" class="panel-card">
                <div class="panel-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></div>
                <h2>Nueva Inscripción</h2>
                <p>Inscribir un estudiante manualmente</p>
            </a>
            <?php endif; ?>
        </div>
        <?php
    }

    // --- VISTA DE REGISTRO DE PAGOS ---

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

    // --- VISTA DE PERFIL DE ESTUDIANTE ---

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
    
    // --- MÉTODOS DE ESTILOS Y SCRIPTS ---

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
            .gestion-academica-wrapper { 
                position: relative;
                background-color: var(--sga-white); border-radius: 16px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; box-shadow: var(--shadow-lg); 
            }
            #sga-panel-loader {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(248, 250, 252, 0.85);
                backdrop-filter: blur(2px);
                z-index: 999;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 16px;
            }
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
            .stat-icon.calls { background: linear-gradient(135deg, var(--sga-pink), #f472b6); }
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
            /* .wp-list-table tbody tr:hover { background-color: #f1f5f9; } */
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
            
            /* Status Pills & Comments */
            .estado-inscrito { color: var(--sga-yellow); background-color: #fffbeb; padding: 4px 10px; border-radius: 999px; font-weight: 500; font-size: 12px; }
            .sga-call-comment { font-size: 12px; color: var(--sga-text-light); margin: 5px 0 0 0; padding-left: 5px; border-left: 2px solid var(--sga-gray); }
            .ga-pill { display: inline-block; padding: 4px 10px; font-size: 12px; font-weight: 500; border-radius: 16px; color: var(--sga-white); }
            .ga-pill-time { background-color: var(--sga-text-light); } .ga-pill-presencial { background-color: var(--sga-blue); }
            .ga-pill-virtual { background-color: var(--sga-purple); } .ga-pill-hibrido { background-color: var(--sga-pink); }
            .ga-pill-publico { background-color: var(--sga-green); margin-right: 15px;} .ga-pill-privado { background-color: var(--sga-text-light); margin-right: 15px;}
            .ga-pill-llamada-pendiente { background-color: var(--sga-yellow); }
            .ga-pill-llamada-contactado { background-color: var(--sga-green); }
            .ga-pill-llamada-no_contesta { background-color: #94a3b8; }
            .ga-pill-llamada-numero_incorrecto { background-color: var(--sga-pink); }
            .ga-pill-llamada-rechazado { background-color: var(--sga-red); }
            
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
            .horario-info { display: flex; align-items: center; gap: 8px; }
            .horario-dia-hora { font-weight: 500; }
            .horario-cupos span { color: var(--sga-text-light); }


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
            #sga-estudiantes-checkbox-list, #sga-distribute-agent-list { height: 200px; overflow-y: auto; border: 1px solid var(--sga-gray); padding: 10px; border-radius: 8px; margin-top: 10px; background-color: var(--sga-white); }
            #sga-distribute-agent-list label { display: block; }
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
            
            /* Call Log Accordion */
            #sga-call-log-accordion .user-log-section {
                border: 1px solid var(--sga-gray);
                margin-bottom: 10px;
                border-radius: 8px;
                overflow: hidden;
            }
            #sga-call-log-accordion .user-log-title {
                margin: 0;
                font-size: 1em;
            }
            #sga-call-log-accordion .user-log-title button {
                display: flex;
                align-items: center;
                width: 100%;
                padding: 12px 20px;
                border: none;
                background: var(--sga-light);
                cursor: pointer;
                text-align: left;
                font-size: 16px;
                font-weight: 600;
                color: var(--sga-primary);
                transition: background-color 0.2s;
            }
            #sga-call-log-accordion .user-log-title button:hover {
                background: var(--sga-gray);
            }
            #sga-call-log-accordion .user-log-title button .call-count {
                margin-left: 15px;
                font-size: 13px;
                font-weight: 500;
                color: var(--sga-text-light);
                background-color: var(--sga-white);
                padding: 3px 10px;
                border-radius: 12px;
                border: 1px solid var(--sga-gray);
            }
            #sga-call-log-accordion .toggle-icon {
                margin-left: auto;
                width: 20px;
                height: 20px;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath d='M14.83 7.17a1.002 1.002 0 00-1.41 0L10 10.59 6.59 7.17a1.002 1.002 0 10-1.41 1.41L10 13.41l4.83-4.83a1 1 0 000-1.41z' fill='%2350575e'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: center;
                transition: transform 0.2s ease-in-out;
            }
            #sga-call-log-accordion button[aria-expanded="true"] .toggle-icon {
                transform: rotate(180deg);
            }
            #sga-call-log-accordion .user-log-content {
                padding: 0;
                display: none;
            }
            #sga-call-log-accordion .user-log-content .tabla-wrapper {
                border: none;
                border-radius: 0;
                box-shadow: none;
                margin-bottom: 0;
            }
            #sga-call-log-accordion .user-log-content .wp-list-table {
                border-top: 1px solid var(--sga-gray);
            }
        </style>
        <?php
    }

    public function render_panel_navigation_js() {
        $agents = SGA_Utils::_get_sga_agents();
        $agents_for_js = [];
        foreach($agents as $agent) {
            $agents_for_js[] = ['id' => $agent->ID, 'name' => $agent->display_name];
        }
        ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            jQuery(document).ready(function($) {
                // Función para establecer la fecha y hora dinámica (¡SOLUCIÓN PARA EL PROBLEMA DE LA HORA!)
                function setDynamicDateTime() {
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
                
                // RESTO DEL CÓDIGO JS DE NAVEGACIÓN Y FUNCIONALIDAD
                var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
                var approvalData = {};
                var callData = {}; // Usado para Marcar/Editar Llamada
                var inscriptionsChart;
                var viewsToRefresh = {};
                var sgaAgents = <?php echo json_encode($agents_for_js); ?>;

                $("#gestion-academica-app-container").on("click", ".panel-nav-link", function(e) {
                    e.preventDefault();
                    var view = $(this).data("view");
                    var activePanel = $(".panel-view.active");
                    var targetPanel = $("#panel-view-" + view);
                    var loader = $("#sga-panel-loader");

                    if (activePanel.is(targetPanel)) {
                        return;
                    }

                    loader.fadeIn(150);

                    function switchView() {
                        activePanel.removeClass("active").hide();
                        targetPanel.addClass("active").show();
                        if (view === 'reportes' && (!inscriptionsChart || viewsToRefresh['reportes'])) {
                            renderInscriptionsChart();
                        }
                        loader.fadeOut(150);
                    }

                    if (viewsToRefresh[view]) {
                        $.post(ajaxurl, {
                            action: 'sga_get_panel_view_html',
                            view: view,
                            _ajax_nonce: '<?php echo wp_create_nonce("sga_get_view_nonce"); ?>'
                        }).done(function(response) {
                            if (response.success) {
                                targetPanel.html(response.data.html);
                                delete viewsToRefresh[view];
                            } else {
                                targetPanel.html('<div class="sga-profile-error">Error al recargar la vista.</div>');
                            }
                        }).fail(function() {
                            targetPanel.html('<div class="sga-profile-error">Error de comunicación al recargar la vista.</div>');
                        }).always(function() {
                            switchView();
                        });
                    } else {
                        setTimeout(switchView, 200); 
                    }
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
                                viewsToRefresh['lista_matriculados'] = true;
                                viewsToRefresh['cursos'] = true;
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
                                    viewsToRefresh['lista_matriculados'] = true;
                                    viewsToRefresh['cursos'] = true;
                                    response.data.approved.forEach(function(estudiante) {
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
                            select.closest('tr').data('call-status', status);
                            viewsToRefresh['registro_llamadas'] = true;
                        }
                    }).fail(function() {
                        alert('Error de conexión.');
                    }).always(function() {
                        select.prop('disabled', false);
                        spinner.removeClass('is-active');
                    });
                });

                $("#gestion-academica-app-container").on("click", ".sga-marcar-llamado-btn", function() {
                    var btn = $(this);
                    // Reiniciar y configurar campos del modal para la acción MARCAR
                    $('#sga-comentario-action-type').val('marcar');
                    $('#ga-modal-comentario-title').text('Añadir Comentario a la Llamada');
                    $('#ga-modal-comentario-guardar').text('Marcar y Guardar');
                    
                    // Almacenar data de la nueva llamada
                    callData = {
                        post_id: btn.data('postid'),
                        row_index: btn.data('rowindex'),
                        nonce: btn.data('nonce'), // Este nonce es para 'sga_marcar_llamado'
                        element: btn
                    };
                    
                    $('#sga-comentario-post-id').val(callData.post_id);
                    $('#sga-comentario-row-index').val(callData.row_index);
                    $('#sga-comentario-nonce').val(callData.nonce);
                    $('#sga-comentario-log-id').val(''); // No hay log ID al marcar por primera vez

                    $('#sga-comentario-llamada-texto').val('');
                    $('#ga-modal-comentario-llamada').fadeIn(200);
                });

                // NUEVO: Click en el botón Editar/Añadir Comentario
                $("#gestion-academica-app-container").on("click", ".sga-edit-llamado-btn", function() {
                    var btn = $(this);
                    
                    // Configurar campos del modal para la acción EDITAR
                    $('#sga-comentario-action-type').val('editar');
                    
                    var currentComment = btn.data('comment');
                    
                    if(currentComment && currentComment.length > 0) {
                         $('#ga-modal-comentario-title').text('Editar Comentario de Llamada');
                         $('#sga-comentario-llamada-texto').val(currentComment);
                    } else {
                         $('#ga-modal-comentario-title').text('Añadir Comentario a la Llamada');
                         $('#sga-comentario-llamada-texto').val('');
                    }
                    $('#ga-modal-comentario-guardar').text('Guardar Comentario');
                    
                    // Almacenar data de la llamada existente
                    callData = {
                        post_id: btn.data('postid'),
                        row_index: btn.data('rowindex'),
                        log_id: btn.data('log-id'),
                        nonce: btn.data('nonce'), // Este nonce es para 'sga_edit_llamado_comment'
                        element: btn
                    };
                    
                    $('#sga-comentario-post-id').val(callData.post_id);
                    $('#sga-comentario-row-index').val(callData.row_index);
                    $('#sga-comentario-log-id').val(callData.log_id);
                    $('#sga-comentario-nonce').val(callData.nonce);

                    $('#ga-modal-comentario-llamada').fadeIn(200);
                });

                $('#ga-modal-comentario-guardar').on('click', function() {
                    var btn = $(this);
                    var comment = $('#sga-comentario-llamada-texto').val();
                    var actionType = $('#sga-comentario-action-type').val();
                    
                    var post_id = $('#sga-comentario-post-id').val();
                    var row_index = $('#sga-comentario-row-index').val();
                    var nonce = $('#sga-comentario-nonce').val();
                    var log_id = $('#sga-comentario-log-id').val();
                    
                    var ajaxAction, postData;

                    if (actionType === 'marcar') {
                        ajaxAction = 'sga_marcar_llamado';
                        postData = {
                            action: ajaxAction,
                            _ajax_nonce: nonce,
                            post_id: post_id,
                            row_index: row_index,
                            comment: comment
                        };
                    } else if (actionType === 'editar') {
                        ajaxAction = 'sga_edit_llamado_comment';
                        // El nonce de edición es el mismo que el guardado en el data del botón de editar/añadir
                        postData = {
                            action: ajaxAction,
                            _ajax_nonce: nonce, 
                            student_id: post_id,
                            row_index: row_index,
                            log_id: log_id,
                            comment: comment
                        };
                    } else {
                        alert('Acción de comentario desconocida.');
                        return;
                    }

                    btn.prop('disabled', true).text('Guardando...');
                    $('#ga-modal-comentario-cancelar').prop('disabled', true);

                    // Deshabilitar elemento original si existe (solo aplica al marcar la primera vez)
                    if (callData.element && actionType === 'marcar') {
                        callData.element.prop('disabled', true);
                    }
                    
                    $.post(ajaxurl, postData).done(function(response) {
                        if (response.success) {
                            // Reemplazamos la celda de acción con el nuevo HTML
                            if (actionType === 'marcar') {
                                // Reemplaza 'Marcar como Llamado' con el HTML de la llamada + botón aprobar (si aplica)
                                callData.element.parent().html(response.data.html + (callData.element.parent().find('.aprobar-btn').prop('outerHTML') || ''));
                            } else {
                                // Buscamos la celda de la columna "Acción" para actualizar los botones de Aprobar/Marcar
                                var actionCell = $('.sga-edit-llamado-btn[data-postid="' + post_id + '"][data-rowindex="' + row_index + '"]').closest('td').get(0);
                                if(actionCell) {
                                    // Preservamos el botón 'Aprobar' (si existe) y actualizamos la información de la llamada.
                                    var aprobarBtn = $(actionCell).find('.aprobar-btn').prop('outerHTML');
                                    var newContent = response.data.html + (aprobarBtn ? aprobarBtn : '');
                                    $(actionCell).html(newContent);
                                }
                            }
                            
                            viewsToRefresh['registro_llamadas'] = true; // Forzar recarga del log
                            $('#ga-modal-comentario-llamada').fadeOut(200);
                        } else {
                            // Manejo de error más detallado
                            var errorMsg = response.data && response.data.message ? response.data.message : 'Error desconocido al guardar.';
                            alert('Error: ' + errorMsg);
                            console.error('Error AJAX:', response);
                        }
                    }).fail(function(jqXHR, textStatus, errorThrown) {
                        // Error de comunicación con el servidor (código 500, timeout, etc.)
                        var errorMessage = 'Error de comunicación. Revisa la consola para más detalles.';
                        if (jqXHR.status) {
                            errorMessage += ' (Status: ' + jqXHR.status + ')';
                        }
                        alert(errorMessage);
                        console.error("AJAX Fail:", textStatus, errorThrown, jqXHR);
                    }).always(function() {
                        btn.prop('disabled', false).text(actionType === 'marcar' ? 'Marcar y Guardar' : 'Guardar Comentario');
                        $('#ga-modal-comentario-cancelar').prop('disabled', false);
                        if (callData.element && actionType === 'marcar') {
                            callData.element.prop('disabled', false);
                        }
                    });
                });

                $('#ga-modal-comentario-cancelar').on('click', function() {
                    $('#ga-modal-comentario-llamada').fadeOut(200);
                });

                function closeModal() {
                    $("#ga-modal-confirmacion").fadeOut(200);
                    $("#ga-modal-confirmar").text('Confirmar y Enviar').prop('disabled', false);
                    $("#ga-modal-cancelar").prop('disabled', false);
                    approvalData = {};
                }

                function checkEmptyTable(tableId, colspan, message) {
                    if ($(tableId + ' tbody tr:not(.no-results-search)').length === 0 && !$(tableId + ' .no-results').length) {
                        $(tableId + ' tbody').append('<tr class="no-results"><td colspan="' + colspan + '">' + message + '</td></tr>');
                    }
                }

                $("#ga-modal-cancelar, .ga-modal").on("click", function(e) {
                    if (e.target == this || $(this).is("#ga-modal-cancelar")) {
                        closeModal();
                        $('#ga-modal-repartir-agentes').fadeOut(200);
                    }
                });

                $("#select-all-pendientes").on("click", function() {
                    $("#tabla-pendientes .bulk-checkbox").prop('checked', this.checked);
                });

                function filterPendientesTable() {
                    var searchTerm = $('#buscador-estudiantes-pendientes').val().toLowerCase();
                    var courseFilter = $('#filtro-curso-pendientes').val();
                    var callStatusFilter = $('#filtro-estado-llamada').val() || ''; 
                    var agentFilter = $('#filtro-agente-asignado').val() || '';
                    var rowsFound = 0;

                    $('#tabla-pendientes tbody tr').each(function() {
                        var row = $(this);
                        if (row.hasClass('no-results') || row.hasClass('no-results-search')) {
                            return;
                        }

                        var rowText = row.text().toLowerCase();
                        var rowCourse = row.data('curso');
                        var rowCallStatus = row.data('call-status');
                        var rowAgentId = String(row.data('agent-id'));

                        var matchesSearch = (searchTerm === '' || rowText.includes(searchTerm));
                        var matchesCourse = (courseFilter === '' || rowCourse === courseFilter);
                        var matchesCallStatus = (callStatusFilter === '' || rowCallStatus === callStatusFilter);
                        var matchesAgent = (agentFilter === '' || rowAgentId === agentFilter);

                        if (matchesSearch && matchesCourse && matchesCallStatus && matchesAgent) {
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
                $("#buscador-estudiantes-pendientes, #filtro-curso-pendientes, #filtro-estado-llamada, #filtro-agente-asignado").on("keyup change", function() { filterPendientesTable(); });
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

                $("#gestion-academica-app-container").on("click", "#exportar-llamadas-btn", function(e) {
                    e.preventDefault();
                    var searchTerm = $('#buscador-registro-llamadas').val();
                    var agentFilter = $('#filtro-agente-llamadas').val();
                    var statusFilter = $('#filtro-estado-llamadas-registro').val();
                    var nonce = '<?php echo wp_create_nonce("export_calls_nonce"); ?>';

                    var url = new URL(ajaxurl);
                    url.searchParams.append('action', 'sga_exportar_registro_llamadas');
                    url.searchParams.append('_wpnonce', nonce);
                    url.searchParams.append('search_term', searchTerm);
                    url.searchParams.append('agent_filter', agentFilter);
                    url.searchParams.append('status_filter', statusFilter);
                    
                    window.location.href = url.href;
                });

                function filterCallLog() {
                    var searchTerm = $('#buscador-registro-llamadas').val().toLowerCase();
                    var agentFilter = $('#filtro-agente-llamadas').val();
                    var statusFilter = $('#filtro-estado-llamadas-registro').val();
                    
                    $('#sga-call-log-accordion .user-log-section').each(function() {
                        var userSection = $(this);
                        var agentName = userSection.data('agent');
                        var matchesAgent = (agentFilter === '' || agentName === agentFilter);
                        
                        if (!matchesAgent) {
                            userSection.hide();
                            return; 
                        }

                        var tableRows = userSection.find('.user-log-content tbody tr');
                        var matchingRowsInSection = 0;

                        tableRows.each(function() {
                            var row = $(this);
                            var rowText = row.text().toLowerCase();
                            var rowStatus = row.data('status');

                            var matchesSearch = (searchTerm === '' || rowText.includes(searchTerm));
                            var matchesStatus = (statusFilter === '' || rowStatus === statusFilter);

                            if (matchesSearch && matchesStatus) {
                                row.show();
                                matchingRowsInSection++;
                            } else {
                                row.hide();
                            }
                        });

                        if (matchingRowsInSection > 0) {
                            userSection.show();
                        } else {
                            userSection.hide();
                        }
                    });
                }
                $('#buscador-registro-llamadas, #filtro-agente-llamadas, #filtro-estado-llamadas-registro').on('keyup change', filterCallLog);


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

                // NUEVO: Manejo del botón de impresión directa
                $("#gestion-academica-app-container").on("click", "#sga-print-expediente-btn", function(e) {
                    e.preventDefault();
                    var btn = $(this);
                    var studentId = btn.data('student-id');
                    var nonce = btn.data('nonce');
                    var originalText = btn.html();
                    
                    btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none; margin: 0 5px;"></span> Cargando...');
                    
                    $.post(ajaxurl, {
                        action: 'sga_render_student_profile_for_print',
                        _ajax_nonce: nonce,
                        student_id: studentId
                    }).done(function(response) {
                        if (response.success) {
                            var printWindow = window.open('', '_blank', 'height=600,width=800');
                            
                            // Verificar que la ventana se haya abierto correctamente
                            if (printWindow) {
                                printWindow.document.write(response.data.html);
                                printWindow.document.close();
                                
                                // Esperar a que el contenido esté cargado antes de imprimir
                                printWindow.onload = function() {
                                    // La espera es crucial para que los estilos CSS se apliquen
                                    setTimeout(function() {
                                        printWindow.focus();
                                        printWindow.print();
                                        printWindow.close();
                                    }, 250); // Pequeña pausa adicional para asegurar la carga
                                };
                            } else {
                                // Si el pop-up fue bloqueado
                                alert('El diálogo de impresión fue bloqueado por el navegador. Por favor, permítelo.');
                            }
                            
                        } else {
                            alert('Error al preparar la impresión: ' + response.data.message);
                        }
                    }).fail(function() {
                        alert('Error de comunicación con el servidor al intentar imprimir.');
                    }).always(function() {
                        btn.prop('disabled', false).html(originalText);
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
                            viewsToRefresh['estudiantes'] = true;
                            viewsToRefresh['lista_matriculados'] = true;
                            viewsToRefresh['enviar_a_matriculacion'] = true;
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

                $('#report_type').on('change', function() {
                    const reportType = $(this).val();
                    const cursoFilter = $('#report-curso-filter-container');
                    const agenteFilter = $('#report-agente-filter-container');
                    cursoFilter.hide();
                    agenteFilter.hide();
                    if (reportType === 'matriculados' || reportType === 'pendientes' || reportType === 'historial_llamadas') {
                        cursoFilter.slideDown();
                    }
                    if (reportType === 'historial_llamadas') {
                        agenteFilter.slideDown();
                    }
                }).trigger('change');
                
                $('.view-switcher').on('click', '.view-btn', function(e) {
                    e.preventDefault();
                    var $this = $(this);
                    if ($this.hasClass('active')) return;
                    var targetView = $this.data('view');
                    $('.view-btn').removeClass('active');
                    $this.addClass('active');
                    if (targetView === 'grid') {
                        $('.cursos-list-view').fadeOut(200, function() { $('.cursos-grid').fadeIn(200); });
                    } else {
                        $('.cursos-grid').fadeOut(200, function() { $('.cursos-list-view').fadeIn(200); });
                    }
                });

                function renderInscriptionsChart() {
                    if (inscriptionsChart) { inscriptionsChart.destroy(); }
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
                            inscriptionsChart = new Chart(ctx, {
                                type: 'line',
                                data: { labels: response.data.labels, datasets: [{ label: 'Inscripciones por Mes', data: response.data.data, backgroundColor: 'rgba(79, 70, 229, 0.2)', borderColor: 'rgba(79, 70, 229, 1)', borderWidth: 2, tension: 0.4, fill: true }] },
                                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
                            });
                        } else {
                            chartContainer.html('<p>No se pudieron cargar los datos.</p>');
                        }
                    }).fail(function() {
                        chartContainer.find('.chart-loading').remove();
                        chartContainer.html('<p>Error al contactar al servidor.</p>');
                    });
                }
                
                $('#gestion-academica-app-container').on('click', '#sga-call-log-accordion .user-log-title button', function() {
                    var button = $(this);
                    var content = button.closest('.user-log-section').find('.user-log-content');
                    var isExpanded = button.attr('aria-expanded') === 'true';
                    button.attr('aria-expanded', !isExpanded);
                    content.slideToggle(200);
                });

                // --- Lógica de Reparto de Agentes ---
                $('#panel-view-enviar_a_matriculacion').on('click', '#sga-distribute-btn', function() {
                    var agentListHtml = '';
                    if (sgaAgents.length > 0) {
                        sgaAgents.forEach(function(agent) {
                            agentListHtml += '<label><input type="checkbox" class="sga-distribute-agent" value="' + agent.id + '"> ' + agent.name + '</label><br>';
                        });
                    } else {
                        agentListHtml = '<p>No hay agentes disponibles para asignar.</p>';
                    }
                    $('#sga-distribute-agent-list').html(agentListHtml);
                    $('#ga-modal-repartir-agentes').fadeIn(200);
                });

                $('#ga-modal-repartir-confirmar').on('click', function() {
                    var btn = $(this);
                    var selectedAgents = [];
                    $('.sga-distribute-agent:checked').each(function() {
                        selectedAgents.push($(this).val());
                    });

                    if (selectedAgents.length === 0) {
                        alert('Por favor, selecciona al menos un agente.');
                        return;
                    }

                    btn.prop('disabled', true).text('Repartiendo...');
                    $('#sga-panel-loader').fadeIn(150);

                    $.post(ajaxurl, {
                        action: 'sga_distribute_inscriptions',
                        security: '<?php echo wp_create_nonce("sga_distribute_nonce"); ?>',
                        agent_ids: selectedAgents
                    }).done(function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            viewsToRefresh['enviar_a_matriculacion'] = true;
                            // Forzar recarga de la vista actual
                            $(".panel-nav-link[data-view='enviar_a_matriculacion']").first().trigger('click');
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    }).fail(function() {
                        alert('Error de comunicación con el servidor.');
                    }).always(function() {
                        btn.prop('disabled', false).text('Confirmar Reparto');
                        $('#ga-modal-repartir-agentes').fadeOut(200);
                        $('#sga-panel-loader').fadeOut(150);
                    });
                });

                 $('#ga-modal-repartir-cancelar').on('click', function() {
                    $('#ga-modal-repartir-agentes').fadeOut(200);
                });

            });
        </script>
        <?php
    }
}
