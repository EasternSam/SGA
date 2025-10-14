<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_Roles
 *
 * Gestiona la creación de roles de usuario, capacidades
 * y las tareas de activación/desactivación del plugin.
 */
class SGA_Roles {

    /**
     * Se ejecuta en la activación del plugin.
     * Crea roles, capacidades y programa el cron job.
     */
    public static function on_activation() {
        // Capacidades base para el rol
        $base_caps = array(
            'read' => true,
            'edit_posts' => true,
            'edit_published_posts' => true,
            'publish_posts' => true,
            'delete_posts' => true,
            'delete_published_posts' => true,
            'edit_others_posts' => true,
            'delete_others_posts' => true,
        );

        // Añadir o actualizar el rol con las capacidades base.
        add_role('gestor_academico', __('Gestor Académico', 'sga-plugin'), $base_caps);

        // Obtener los roles
        $gestor_role = get_role('gestor_academico');
        $admin_role = get_role('administrator');

        // Definir capacidades personalizadas para 'estudiante'
        $estudiante_caps = [
            'edit_estudiante'              => true,
            'read_estudiante'              => true,
            'delete_estudiante'            => true,
            'edit_estudiantes'             => true,
            'edit_others_estudiantes'      => true,
            'publish_estudiantes'          => true,
            'read_private_estudiantes'     => true,
            'delete_estudiantes'           => true,
            'delete_published_estudiantes' => true,
            'delete_others_estudiantes'    => true,
            'edit_published_estudiantes'   => true,
        ];

        // Añadir las capacidades personalizadas a los roles
        foreach ($estudiante_caps as $cap => $grant) {
            if ($gestor_role) {
                $gestor_role->add_cap($cap);
            }
            if ($admin_role) {
                $admin_role->add_cap($cap);
            }
        }

        // Programar el cron job de reportes
        if (!wp_next_scheduled('sga_daily_report_cron')) {
            wp_schedule_event(strtotime('02:00:00'), 'daily', 'sga_daily_report_cron');
        }

        // Programar el cron job para archivar y resetear llamadas
        if (!wp_next_scheduled('sga_archive_and_reset_call_log')) {
            // Se ejecuta a las 3:00 AM para no coincidir con el de reportes
            wp_schedule_event(strtotime('03:00:00'), 'daily', 'sga_archive_and_reset_call_log');
        }
    }

    /**
     * Se ejecuta en la desactivación del plugin.
     * Limpia los cron jobs programados.
     */
    public static function on_deactivation() {
        wp_clear_scheduled_hook('sga_daily_report_cron');
        wp_clear_scheduled_hook('sga_archive_and_reset_call_log');
    }
}
