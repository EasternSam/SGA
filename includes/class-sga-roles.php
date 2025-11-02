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
        // --- 1. Definir TODAS las capacidades del plugin ---
        $all_sga_caps = [
            // Capacidades de acceso a vistas/paneles
            'sga_access_panel'           => true,
            'sga_access_matriculacion'   => true,
            'sga_access_estudiantes'     => true,
            'sga_access_cursos'          => true,
            'sga_access_pagos'           => true,
            'sga_access_comunicacion'    => true,
            'sga_access_reportes'        => true,
            
            // Capacidades de acciones específicas
            'sga_approve_inscriptions'   => true,
            'sga_view_enrolled_list'     => true,
            'sga_view_call_log'          => true,
            'sga_create_inscriptions'    => true,
            'sga_edit_courses'           => true,
            'sga_edit_reports_settings'  => true,
            'sga_generate_all_reports'   => true,
            'sga_generate_call_reports'  => true,

            // Capacidades para CPT Estudiante
            'edit_estudiante'              => true, 'read_estudiante'              => true,
            'delete_estudiante'            => true, 'edit_estudiantes'             => true,
            'edit_others_estudiantes'      => true, 'publish_estudiantes'          => true,
            'read_private_estudiantes'     => true, 'delete_estudiantes'           => true,
            'delete_published_estudiantes' => true, 'delete_others_estudiantes'    => true,
            'edit_published_estudiantes'   => true,
        ];

        // Capacidades base de WordPress (equivalentes a Editor) para que los roles funcionen globalmente
        $base_wp_caps = [
            'read' => true, // Esta es la clave para acceder al admin!
            // Permisos de lectura
            'read_private_pages' => true,
            'read_private_posts' => true,
            // Permisos de edición de Posts
            'edit_posts' => true,
            'edit_published_posts' => true,
            'edit_others_posts' => true,
            'edit_private_posts' => true,
             // Permisos de edición de Pages
            'edit_pages' => true,
            'edit_published_pages' => true,
            'edit_others_pages' => true,
            'edit_private_pages' => true,
            // Permisos de publicación
            'publish_posts' => true,
            'publish_pages' => true,
            // Permisos de borrado de Posts
            'delete_posts' => true,
            'delete_published_posts' => true,
            'delete_others_posts' => true,
            'delete_private_posts' => true,
            // Permisos de borrado de Pages
            'delete_pages' => true,
            'delete_published_pages' => true,
            'delete_others_pages' => true,
            'delete_private_pages' => true,
            // Otros permisos de editor
            'manage_categories' => true,
            'manage_links' => true,
            'moderate_comments' => true,
            'upload_files' => true,
        ];

        // Combinar todas las capacidades para los nuevos roles
        $full_permissions = array_merge($base_wp_caps, $all_sga_caps);

        // --- 2. Crear/Actualizar Roles con TODOS los permisos ---
        // Se eliminan los roles primero para asegurar que se actualicen con el nuevo set de permisos
        remove_role('gestor_academico');
        remove_role('agente');
        remove_role('gestor_de_cursos');
        remove_role('agente_infotep'); // <--- Aseguramos la eliminación para actualización

        // <-- Creación de roles -->
        add_role('gestor_academico', __('Gestor Académico', 'sga-plugin'), $full_permissions);
        add_role('agente', __('Agente', 'sga-plugin'), $full_permissions);
        add_role('gestor_de_cursos', __('Gestor de Cursos', 'sga-plugin'), $full_permissions);
        add_role('agente_infotep', __('Agente de Infotep', 'sga-plugin'), $full_permissions); // <--- NUEVO ROL AÑADIDO

        // --- 3. Asignar todas las capacidades al Administrador también ---
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($all_sga_caps as $cap => $grant) {
                $admin_role->add_cap($cap);
            }
        }

        // --- 4. Programar Cron Jobs ---
        if (!wp_next_scheduled('sga_daily_report_cron')) {
            wp_schedule_event(strtotime('02:00:00'), 'daily', 'sga_daily_report_cron');
        }
        if (!wp_next_scheduled('sga_archive_and_reset_call_log')) {
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
