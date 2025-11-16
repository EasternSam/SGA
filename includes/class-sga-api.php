<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_API
 *
 * Gestiona los endpoints de la API REST para la comunicación con sistemas externos.
 */
class SGA_API {

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Registra todas las rutas de la API del plugin.
     */
    public function register_routes() {
        // Endpoint para que el sistema interno actualice el estado de un estudiante (ej. confirmar pago)
        register_rest_route('sga/v1', '/update-student-status/', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_update_student_status'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);

        // Endpoint para que el sistema interno consulte la lista de estudiantes
        register_rest_route('sga/v1', '/students/', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_students'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);

        // Endpoint para que el sistema interno obtenga la secuencia de matrícula
        register_rest_route('sga/v1', '/matricula-sequence/', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_matricula_sequence'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);
    }

    /**
     * Callback de permisos para verificar la clave secreta de la API.
     *
     * MODIFICADO: Ahora usa la misma clave 'sga_api_key' del panel de Ajustes.
     */
    public function check_api_permission($request) {
        // Leemos la clave API guardada desde el panel de Ajustes > SGA Settings
        $api_key = get_option('sga_api_key');
        
        // Obtenemos la clave enviada en el header
        $sent_secret = $request->get_header('X-SGA-Signature');
        
        if (empty($api_key)) {
            // Si no hay clave configurada, se bloquea por seguridad.
            return new WP_Error('rest_forbidden', 'La clave API no está configurada en Ajustes > SGA Settings.', ['status' => 403]);
        }
        
        if ($sent_secret === $api_key) {
            return true;
        }

        // Para peticiones locales (simulador), también validamos si el usuario es admin
        if (is_user_logged_in() && current_user_can('manage_options')) {
            return true;
        }
        
        return new WP_Error('rest_forbidden', 'Clave de API inválida.', ['status' => 403]);
    }

    /**
     * Maneja la petición para actualizar el estado de un estudiante.
     */
    public function handle_update_student_status($request) {
        $params = $request->get_json_params();
        $cedula = sanitize_text_field($params['cedula'] ?? '');
        $status = sanitize_text_field($params['status'] ?? '');
        $curso_nombre = sanitize_text_field($params['curso_nombre'] ?? '');

        $log_content = "Cédula: {$cedula}, Estado: {$status}, Curso: {$curso_nombre}\nParámetros recibidos: " . json_encode($params);
        SGA_Utils::_log_activity('API Entrante: Petición Recibida', $log_content, 0, true);

        if (empty($cedula) || empty($status) || empty($curso_nombre)) {
            $error_msg = 'Parámetros requeridos faltantes: cedula, status, curso_nombre.';
            SGA_Utils::_log_activity('API Entrante: Error', $error_msg, 0, true);
            return new WP_REST_Response(['error' => $error_msg], 400);
        }

        if ($status === 'pagado') {
            $integration_options = get_option('sga_integration_options', []);
            $auto_enroll_disabled = !empty($integration_options['disable_auto_enroll']) && $integration_options['disable_auto_enroll'] == 1;

            if ($auto_enroll_disabled) {
                SGA_Utils::_log_activity('API Entrante: Matriculación Omitida', "La matriculación automática está desactivada. La inscripción para {$cedula} en '{$curso_nombre}' no fue procesada.", 0, true);
                return new WP_REST_Response(['message' => 'Pago recibido. La matriculación automática está desactivada, se requiere aprobación manual.'], 200);
            }
            
            $student_query = get_posts([
                'post_type' => 'estudiante', 'posts_per_page' => 1,
                'meta_key' => 'cedula', 'meta_value' => $cedula,
            ]);

            if (!$student_query) {
                $error_msg = "Estudiante con cédula {$cedula} no encontrado.";
                SGA_Utils::_log_activity('API Entrante: Error', $error_msg, 0, true);
                return new WP_REST_Response(['error' => $error_msg], 404);
            }

            $student_post = $student_query[0];
            $cursos_inscritos = get_field('cursos_inscritos', $student_post->ID);
            $row_index_to_approve = -1;

            if ($cursos_inscritos) {
                foreach ($cursos_inscritos as $index => $curso) {
                    if ($curso['nombre_curso'] === $curso_nombre && $curso['estado'] === 'Inscrito') {
                        $row_index_to_approve = $index;
                        break;
                    }
                }
            }

            if ($row_index_to_approve !== -1) {
                $result = SGA_Utils::_aprobar_estudiante(
                    $student_post->ID,
                    $row_index_to_approve,
                    $cedula,
                    $student_post->post_title,
                    true // is_automatic_payment
                );

                if ($result) {
                    return new WP_REST_Response(['message' => 'Estudiante matriculado exitosamente.', 'matricula' => $result['matricula']], 200);
                } else {
                    $error_msg = "Error interno al intentar matricular al estudiante.";
                    SGA_Utils::_log_activity('API Entrante: Error', $error_msg, 0, true);
                    return new WP_REST_Response(['error' => $error_msg], 500);
                }
            } else {
                $error_msg = "No se encontró una inscripción pendiente para el estudiante {$cedula} en el curso '{$curso_nombre}'.";
                SGA_Utils::_log_activity('API Entrante: Error', $error_msg, 0, true);
                return new WP_REST_Response(['error' => $error_msg], 404);
            }
        }
        
        return new WP_REST_Response(['message' => 'Acción no reconocida.'], 400);
    }
    
    /**
     * Maneja la petición para obtener la lista de estudiantes.
     */
    public function handle_get_students($request) {
        $status_filter = sanitize_text_field($request->get_param('status')); // ej: 'matriculado'

        $students_data = SGA_Utils::_get_filtered_students('', '', $status_filter ?: '');
        $response = [];

        foreach($students_data as $data) {
             $response[] = [
                 'nombre' => $data['estudiante']->post_title,
                 'cedula' => get_field('cedula', $data['estudiante']->ID),
                 'email' => get_field('email', $data['estudiante']->ID),
                 'telefono' => get_field('telefono', $data['estudiante']->ID),
                 'curso' => [
                     'nombre' => $data['curso']['nombre_curso'],
                     'horario' => $data['curso']['horario'] ?? '',
                     'matricula' => $data['curso']['matricula'] ?? '',
                     'estado' => $data['curso']['estado'] ?? '',
                 ]
             ];
        }

        return new WP_REST_Response($response, 200);
    }
    
    /**
     * Maneja la petición para obtener la secuencia de matrícula.
     */
    public function handle_get_matricula_sequence($request) {
        $counter = get_option('sga_matricula_counter', 1);
        $year = date('y');
        $next_sequence = str_pad($counter, 4, '0', STR_PAD_LEFT);
        $last_generated = ($counter > 1) ? $year . '-' . str_pad($counter - 1, 4, '0', STR_PAD_LEFT) : 'Ninguna';

        $response = [
            'last_generated_matricula' => $last_generated,
            'next_sequence_number' => $counter,
            'next_matricula_example' => $year . '-' . $next_sequence
        ];

        return new WP_REST_Response($response, 200);
    }
}