<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase principal del plugin SGA.
 *
 * Responsable de inicializar todas las funcionalidades,
 * cargando las diferentes clases y registrando los hooks necesarios.
 */
class SGA_Main {

    public function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Carga e instancia las clases necesarias para el funcionamiento del plugin.
     */
    private function load_dependencies() {
        // Cargar los tres archivos de vistas
        require_once SGA_PLUGIN_PATH . 'includes/class-sga-panel-views-part1.php';
        require_once SGA_PLUGIN_PATH . 'includes/class-sga-panel-views-part2.php';
        require_once SGA_PLUGIN_PATH . 'includes/class-sga-panel-views-part3.php';

        // Cada clase se encarga de una funcionalidad específica.
        new SGA_CPT();
        new SGA_Admin();
        new SGA_Ajax();
        new SGA_Shortcodes();
        new SGA_Payments();
        new SGA_Reports();
        new SGA_Integration();
        new SGA_API(); // <-- Clase de la API REST
    }
    
    /**
     * Añade hooks iniciales que no pertenecen a una clase específica.
     */
    private function init_hooks() {
        // Hook para verificar dependencias como Dompdf.
        add_action('admin_init', array($this, 'check_dependencies'));
    }

    /**
     * Verifica si la librería Dompdf está disponible y muestra un aviso si no lo está.
     */
    public function check_dependencies() {
        if (!class_exists('Dompdf\Dompdf')) {
            add_action('admin_notices', array($this, 'missing_dompdf_notice'));
        }
    }

    /**
     * Muestra el aviso en el panel de administración si falta Dompdf.
     */
    public function missing_dompdf_notice() {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php _e('Plugin Sistema de Gestión Académica: Dependencia Faltante', 'sga-plugin'); ?></strong><br>
                <?php _e('La librería Dompdf no se encuentra. Por favor, instálela usando Composer (<code>composer require dompdf/dompdf</code>) o descargue la librería y suba la carpeta descomprimida con el nombre <code>dompdf</code> al directorio de este plugin.', 'sga-plugin'); ?>
            </p>
        </div>
        <?php
    }
}
