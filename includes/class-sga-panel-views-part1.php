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
            if (isset($options['enable_online_payments']) && $options['enable_online_payments'] == 1) :
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
            .sga-call-log-cell .sga-call-comment-block { margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px dashed var(--sga-gray); }
            .sga-call-log-cell .sga-call-comment-block:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
            .sga-call-comment-meta { font-size: 11px; color: var(--sga-text-light); margin-bottom: 3px; }
            .sga-call-comment-meta strong { color: var(--sga-text); }
            .sga-call-comment-text { font-size: 13px; color: var(--sga-text); margin: 0; white-space: pre-wrap; word-wrap: break-word; }
            .sga-call-comment-text em { color: var(--sga-text-light); } /* Para el "(editado)" */
            .sga-manage-comment-btn { margin-top: 10px; display: inline-flex; align-items: center; gap: 5px; } /* Ajuste del botón */
            .sga-manage-comment-btn svg { width: 16px; height: 16px; }

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
        // Pasar el ID del usuario actual al JavaScript
        wp_localize_script('sga-panel-script', 'sgaPanelData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'current_user_id' => get_current_user_id(),
            'agents' => $agents_for_js,
            'nonces' => [
                'get_view' => wp_create_nonce('sga_get_view_nonce'),
                'approve_single' => wp_create_nonce('aprobar_nonce'),
                'approve_bulk' => wp_create_nonce('aprobar_bulk_nonce'),
                'update_call_status' => wp_create_nonce('sga_update_call_status_general_nonce'), // Usar un nonce general o generar dinámicamente
                'manage_comment' => wp_create_nonce('sga_manage_comment_nonce'),
                'get_profile' => wp_create_nonce('sga_get_profile_nonce'),
                'update_profile' => wp_create_nonce('sga_update_profile_nonce'),
                'send_bulk_email' => wp_create_nonce('sga_send_bulk_email_nonce'),
                'chart' => wp_create_nonce('sga_chart_nonce'),
                'distribute' => wp_create_nonce('sga_distribute_nonce'),
                'export_excel' => wp_create_nonce('export_nonce'),
                'export_moodle' => wp_create_nonce('export_nonce'), // Mismo nonce para exportación
                'export_calls' => wp_create_nonce('export_calls_nonce'),
                 'test_api' => wp_create_nonce('sga_test_api_nonce'),
                 'pending_check' => wp_create_nonce("sga_pending_nonce")
            ]
        ));
        // Asegúrate de que 'sga-panel-script' sea el handle de tu script principal si lo tienes encolado
        // Si no, puedes simplemente imprimir los datos JS directamente.
        ?>
        <script>
            // Imprimir los datos localizados si no usas wp_enqueue_script/wp_localize_script
             if (typeof sgaPanelData === 'undefined') {
                var sgaPanelData = {
                    ajax_url: "<?php echo admin_url('admin-ajax.php'); ?>",
                    current_user_id: <?php echo get_current_user_id(); ?>,
                    agents: <?php echo json_encode($agents_for_js); ?>,
                    nonces: {
                        get_view: '<?php echo wp_create_nonce("sga_get_view_nonce"); ?>',
                        approve_single: '<?php echo wp_create_nonce("aprobar_nonce"); ?>',
                        approve_bulk: '<?php echo wp_create_nonce("aprobar_bulk_nonce"); ?>',
                        update_call_status: '<?php echo wp_create_nonce("sga_update_call_status_general_nonce"); ?>',
                        manage_comment: '<?php echo wp_create_nonce("sga_manage_comment_nonce"); ?>',
                        get_profile: '<?php echo wp_create_nonce("sga_get_profile_nonce"); ?>',
                        update_profile: '<?php echo wp_create_nonce("sga_update_profile_nonce"); ?>',
                        send_bulk_email: '<?php echo wp_create_nonce("sga_send_bulk_email_nonce"); ?>',
                        chart: '<?php echo wp_create_nonce("sga_chart_nonce"); ?>',
                        distribute: '<?php echo wp_create_nonce("sga_distribute_nonce"); ?>',
                        export_excel: '<?php echo wp_create_nonce("export_nonce"); ?>',
                        export_moodle: '<?php echo wp_create_nonce("export_nonce"); ?>',
                        export_calls: '<?php echo wp_create_nonce("export_calls_nonce"); ?>',
                        test_api: '<?php echo wp_create_nonce("sga_test_api_nonce"); ?>',
                        pending_check: '<?php echo wp_create_nonce("sga_pending_nonce"); ?>'
                    }
                };
             }
        </script>
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

                var ajaxurl = sgaPanelData.ajax_url;
                var currentUserId = sgaPanelData.current_user_id;
                var nonces = sgaPanelData.nonces;
                var approvalData = {};
                var commentData = {}; // Objeto para guardar datos del comentario actual
                var inscriptionsChart;
                var viewsToRefresh = {};
                var sgaAgents = sgaPanelData.agents;

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
                            _ajax_nonce: nonces.get_view
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

                // --- Lógica Aprobación (Sin cambios) ---
                $("#tabla-pendientes").on("click", ".aprobar-btn", function() {
                    var btn = $(this);
                    approvalData = {
                        type: 'single',
                        nonce: nonces.approve_single, // Usar nonce general si no se genera dinámico
                        post_id: btn.data('postid'),
                        row_index: btn.data('rowindex'),
                        cedula: btn.data('cedula'),
                        nombre: btn.data('nombre'),
                        element: btn
                    };
                    $("#ga-modal-confirmacion").fadeIn(200);
                });
                $("#apply-bulk-action").on("click", function() { /* ... */ });
                $("#ga-modal-confirmar").on("click", function() { /* ... */ });
                $("#ga-modal-cancelar, .ga-modal").on("click", function(e) {
                     if (e.target == this || $(this).is("#ga-modal-cancelar")) {
                         closeModal();
                         $('#ga-modal-comentario-llamada').fadeOut(200); // Cerrar modal comentario también
                         $('#ga-modal-repartir-agentes').fadeOut(200);
                     }
                 });
                function closeModal() {
                     $("#ga-modal-confirmacion").fadeOut(200);
                     $("#ga-modal-confirmar").text('Confirmar y Enviar').prop('disabled', false);
                     $("#ga-modal-cancelar").prop('disabled', false);
                     approvalData = {};
                 }

                // --- Lógica Comentarios Llamada (Actualizada) ---
                $("#gestion-academica-app-container").on("click", ".sga-manage-comment-btn", function() {
                    var btn = $(this);
                    var cell = btn.closest('.sga-call-log-cell');
                    var postId = cell.data('postid');
                    var rowIndex = cell.data('rowindex');
                    
                    // --- LÓGICA JS MODIFICADA ---
                    var lastCommentAuthorId = btn.data('last-author-id') || 0;
                    var lastCommentText = btn.data('last-comment') || '';
                    var hasComments = btn.data('has-comments') == '1';
                    
                    // Determinar modo edición
                    var editMode = (hasComments && lastCommentAuthorId == currentUserId);

                    // Capturar el texto del botón (sin el HTML del icono)
                    var buttonText = btn.clone().children().remove().end().text().trim(); 
                    // --- FIN LÓGICA JS MODIFICADA ---


                    commentData = { // Guardar datos para el guardado
                        post_id: postId,
                        row_index: rowIndex,
                        edit_mode: editMode,
                        cell_element: cell // Guardar la celda para actualizarla después
                    };

                    // Configurar el modal
                    var modal = $('#ga-modal-comentario-llamada');
                    
                    // --- LÓGICA DE MODAL ACTUALIZADA ---
                    if (buttonText === 'Editar comentario') {
                        modal.find('h4').text('Editar Último Comentario');
                        modal.find('#sga-comentario-llamada-texto').val(lastCommentText);
                        modal.find('#ga-modal-comentario-guardar').text('Guardar Cambios');
                    } else if (buttonText === 'Añadir comentario') {
                        modal.find('h4').text('Añadir Nuevo Comentario');
                        modal.find('#sga-comentario-llamada-texto').val('');
                        modal.find('#ga-modal-comentario-guardar').text('Añadir Comentario');
                    } else { // 'Marcar como llamado'
                        modal.find('h4').text('Marcar como Llamado y Añadir Comentario');
                        modal.find('#sga-comentario-llamada-texto').val('');
                        modal.find('#ga-modal-comentario-guardar').text('Marcar y Guardar');
                    }
                    // --- FIN LÓGICA DE MODAL ACTUALIZADA ---

                    modal.fadeIn(200);
                });

                // Guardar comentario (Añadir o Editar)
                $('#ga-modal-comentario-guardar').on('click', function() {
                    var btn = $(this);
                    var commentText = $('#sga-comentario-llamada-texto').val();

                    btn.prop('disabled', true).text('Guardando...');
                    $('#ga-modal-comentario-cancelar').prop('disabled', true); // Deshabilitar cancelar también

                    $.post(ajaxurl, {
                        action: 'sga_manage_call_comment',
                        security: nonces.manage_comment, // Usar el nonce correcto
                        post_id: commentData.post_id,
                        row_index: commentData.row_index,
                        comment: commentText,
                        edit_mode: commentData.edit_mode
                    }).done(function(response) {
                        if (response.success) {
                            // Reemplazar contenido de la celda con el HTML actualizado
                            commentData.cell_element.html(response.data.html);
                            
                            // Si fue una acción de "Marcar como llamado" (nuevo comentario), actualizamos el dropdown
                            if (response.data.status_updated) {
                                var dropdown = commentData.cell_element.closest('tr').find('.sga-call-status-select');
                                if (dropdown.length) {
                                    dropdown.val('contactado');
                                    // Actualizar el data-attribute para el filtrado
                                    dropdown.closest('tr').data('call-status', 'contactado');
                                }
                            }

                            viewsToRefresh['registro_llamadas'] = true; // Marcar para refrescar si se navega
                            $('#ga-modal-comentario-llamada').fadeOut(200);
                        } else {
                            alert('Error al guardar: ' + (response.data.message || 'Error desconocido'));
                        }
                    }).fail(function() {
                        alert('Error de conexión al guardar comentario.');
                    }).always(function() {
                        // Restaurar botón y modal independientemente del resultado
                        btn.prop('disabled', false);
                        $('#ga-modal-comentario-cancelar').prop('disabled', false);
                        // El texto del botón se restaura al abrir el modal la próxima vez
                    });
                });

                 $('#ga-modal-comentario-cancelar').on('click', function() {
                     $('#ga-modal-comentario-llamada').fadeOut(200);
                 });


                // --- Lógica Filtros, Exportación, Perfil, Comunicación, Reportes (Sin cambios funcionales relevantes aquí) ---
                function filterPendientesTable() { /* ... */ }
                function filterTable(tableSelector, searchInputSelector, courseFilterSelector) { /* ... */ }
                function filterLogTable() { /* ... */ }
                function filterCourses() { /* ... */ }
                $("#buscador-cursos, #filtro-escuela-cursos, #filtro-visibilidad-cursos").on("keyup change", filterCourses);
                $("#buscador-estudiantes-pendientes, #filtro-curso-pendientes, #filtro-estado-llamada, #filtro-agente-asignado").on("keyup change", function() { filterPendientesTable(); });
                $("#buscador-matriculados, #filtro-curso-matriculados").on("keyup change", function() { filterTable('#tabla-matriculados', '#buscador-matriculados', '#filtro-curso-matriculados'); });
                $("#buscador-general-estudiantes").on("keyup", function() { filterTable('#tabla-general-estudiantes', '#buscador-general-estudiantes', null); });
                $("#buscador-log, #filtro-usuario-log, #filtro-fecha-inicio, #filtro-fecha-fin").on("keyup change", function() { filterLogTable(); });
                $("#exportar-btn").on("click", function(e) { /* ... */ });
                $("#gestion-academica-app-container").on("click", "#exportar-llamadas-btn", function(e) { /* ... */ });
                function filterCallLog() { /* ... */ }
                $('#buscador-registro-llamadas, #filtro-agente-llamadas, #filtro-estado-llamadas-registro').on('keyup change', filterCallLog);
                $("#panel-view-cursos").on("click", ".ver-matriculados-btn", function(e) { /* ... */ });
                $("#buscador-pagos").on("keyup", function() { filterTable('#tabla-pagos', '#buscador-pagos', null); });
                $("#tabla-general-estudiantes").on("click", ".ver-perfil-btn", function() { /* ... */ });
                $("#gestion-academica-app-container").on("click", "#sga-profile-back-btn", function() { /* ... */ });
                $("#gestion-academica-app-container").on("click", "#sga-profile-save-btn", function() { /* ... */ });
                $('#sga-email-recipient-group').on('change', function() { /* ... */ });
                $('#sga-estudiantes-search').on('keyup', function() { /* ... */ });
                $('#sga-send-bulk-email-btn').on('click', function() { /* ... */ });
                $('#report_type').on('change', function() { /* ... */ }).trigger('change');
                $('.view-switcher').on('click', '.view-btn', function(e) { /* ... */ });
                function renderInscriptionsChart() { /* ... */ }
                $('#gestion-academica-app-container').on('click', '#sga-call-log-accordion .user-log-title button', function() { /* ... */ });

                // --- Lógica de Reparto de Agentes (Sin cambios) ---
                $('#panel-view-enviar_a_matriculacion').on('click', '#sga-distribute-btn', function() { /* ... */ });
                $('#ga-modal-repartir-confirmar').on('click', function() { /* ... */ });
                $('#ga-modal-repartir-cancelar').on('click', function() { /* ... */ });

                 // Checkbox general
                 $("#select-all-pendientes").on("click", function() {
                     $("#tabla-pendientes .bulk-checkbox").prop('checked', this.checked);
                 });

                 // Helper para tabla vacía
                 function checkEmptyTable(tableId, colspan, message) {
                     if ($(tableId + ' tbody tr:not(.no-results-search)').length === 0 && !$(tableId + ' .no-results').length) {
                         $(tableId + ' tbody').append('<tr class="no-results"><td colspan="' + colspan + '">' + message + '</td></tr>');
                     }
                 }

                 // Actualizar estado llamada (sin cambios funcionales)
                 $("#gestion-academica-app-container").on('change', '.sga-call-status-select', function(e) {
                     var select = $(this);
                     var post_id = select.data('postid');
                     var row_index = select.data('rowindex');
                     var nonce = select.data('nonce'); // Asegúrate que el nonce se genera correctamente en PHP
                     var status = select.val();
                     var spinner = select.next('.spinner');

                     select.prop('disabled', true);
                     spinner.addClass('is-active');

                     $.post(ajaxurl, {
                         action: 'sga_update_call_status',
                         _ajax_nonce: nonce, // Usa el nonce específico si lo generas por fila
                         // O usa el general: security: nonces.update_call_status,
                         post_id: post_id,
                         row_index: row_index,
                         status: status
                     }).done(function(response) {
                         if (!response.success) {
                             alert('Error: ' + (response.data.message || 'Error desconocido'));
                         } else {
                             select.closest('tr').data('call-status', status); // Actualizar el data-attribute
                             viewsToRefresh['registro_llamadas'] = true;
                         }
                     }).fail(function() {
                         alert('Error de conexión.');
                     }).always(function() {
                         select.prop('disabled', false);
                         spinner.removeClass('is-active');
                     });
                 });


            });
        </script>
        <?php
    }
}
