<?php
/**
 * Plugin Name: Sistema de Gestión Académica CENTU
 * Description: Panel a medida para gestionar inscripciones, cursos, matriculación, pagos (Azul, Cardnet) y reportes avanzados en PDF.
 * Version:20.5
 * Author: Samuel Diaz Pilier
 * Author URI: https://90s.agency/sam
 * @requires PHP 7.1+
 * NOTA: Este plugin requiere la librería Dompdf. La forma recomendada es ejecutar `composer require dompdf/dompdf`.
 * Alternativamente, puede descargar la librería desde GitHub y subir la carpeta descomprimida con el nombre 'dompdf' a este directorio.
 */

if (!defined('ABSPATH')) exit; // Salir si se accede directamente.

// --- 1. Definir Constantes del Plugin ---
define('SGA_PLUGIN_VERSION', '13.6');
define('SGA_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SGA_PLUGIN_URL', plugin_dir_url(__FILE__));

// --- 2. Cargar la librería Dompdf ---
// Prioriza el autoloader de Composer si existe.
if (file_exists(SGA_PLUGIN_PATH . 'vendor/autoload.php')) {
    require_once SGA_PLUGIN_PATH . 'vendor/autoload.php';
}
// Como alternativa, busca una carpeta de Dompdf subida manualmente.
elseif (file_exists(SGA_PLUGIN_PATH . 'dompdf/autoload.inc.php')) {
    require_once SGA_PLUGIN_PATH . 'dompdf/autoload.inc.php';
}

// --- 3. Incluir los archivos del Core del Plugin ---
require_once SGA_PLUGIN_PATH . 'includes/class-sga-utils.php';
require_once SGA_PLUGIN_PATH . 'includes/class-sga-roles.php';
require_once SGA_PLUGIN_PATH . 'includes/class-sga-cpt.php';
require_once SGA_PLUGIN_PATH . 'includes/class-sga-admin.php';
require_once SGA_PLUGIN_PATH . 'includes/class-sga-ajax.php';
require_once SGA_PLUGIN_PATH . 'includes/class-sga-shortcodes.php';
require_once SGA_PLUGIN_PATH . 'includes/class-sga-payments.php';
require_once SGA_PLUGIN_PATH . 'includes/class-sga-reports.php';
require_once SGA_PLUGIN_PATH . 'includes/class-sga-integration.php';
require_once SGA_PLUGIN_PATH . 'includes/class-sga-api.php';
require_once SGA_PLUGIN_PATH . 'includes/class-sga-main.php';

// --- 4. Registrar Hooks de Activación y Desactivación ---
// Estos hooks llaman a métodos estáticos en la clase de Roles para mantener el archivo principal limpio.
register_activation_hook(__FILE__, array('SGA_Roles', 'on_activation'));
register_deactivation_hook(__FILE__, array('SGA_Roles', 'on_deactivation'));

// --- 5. Iniciar el Plugin ---
/**
 * Función principal que instancia la clase principal del plugin.
 */
function sga_run_plugin() {
    new SGA_Main();
}
// Enganchar la función de inicio al hook 'plugins_loaded' de WordPress.
add_action('plugins_loaded', 'sga_run_plugin');
