<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_Shortcodes
 *
 * Gestiona la creación y renderizado de todos los shortcodes del plugin.
 * Extiende SGA_Panel_Views_Part3 para heredar todos los métodos de renderizado de vistas,
 * estilos y scripts, manteniendo esta clase concisa y enfocada en los shortcodes.
 */
class SGA_Shortcodes extends SGA_Panel_Views_Part3 { // Extiende la última parte

    public function __construct() {
        add_shortcode('panel_gestion_academica', array($this, 'render_panel'));
        add_shortcode('sga_pagina_pagos', array($this, 'render_pagos_page'));
    }

    /**
     * Renderiza el shortcode del panel de gestión principal.
     */
    public function render_panel() {
        // CORRECCIÓN: Añadir 'agente_infotep' a la lista de roles permitidos para acceder al panel.
        if (!is_user_logged_in() || !$this->sga_user_has_role(['administrator', 'gestor_academico', 'agente', 'agente_infotep', 'gestor_de_cursos'])) {
            return '<div class="notice notice-error" style="margin: 20px;"><p>No tienes los permisos necesarios para acceder a este panel. Por favor, contacta a un administrador.</p></div>';
        }

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

        <div id="ga-modal-comentario-llamada" class="ga-modal" style="display:none;">
            <div class="ga-modal-content">
                <h4>Gestionar Comentario de Llamada</h4>
                <p>Añade o edita tu comentario sobre esta interacción.</p>
                <textarea id="sga-comentario-llamada-texto" placeholder="Escribe tu comentario aquí..." rows="4" style="width: 100%;"></textarea>
                <div class="ga-modal-actions">
                    <button id="ga-modal-comentario-cancelar" class="button button-secondary">Cancelar</button>
                    <button id="ga-modal-comentario-guardar" class="button button-primary">Guardar</button>
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
}
