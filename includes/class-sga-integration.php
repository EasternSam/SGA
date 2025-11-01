<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_Integration
 *
 * Gestiona las integraciones con plugins de terceros y el sistema interno.
 */
class SGA_Integration {

    public function __construct() {
        add_action('fluentform/submission_inserted', array($this, 'procesar_inscripcion_y_crear_perfil'), 10, 3);
    }

    /**
     * Procesa el envío de un formulario de inscripción de Fluent Forms.
     */
    public function procesar_inscripcion_y_crear_perfil($entryId, $formData, $form) {
        if ($form->id != 3) return; // Asegúrate de que este es el ID correcto de tu formulario

        $cedula = isset($formData['cedula_o_identificacion']) ? sanitize_text_field($formData['cedula_o_identificacion']) : '';
        if (empty($cedula)) return;

        $curso_inscrito = isset($formData['nombre_del_curso']) ? sanitize_text_field($formData['nombre_del_curso']) : '';
        $horario_inscrito = isset($formData['horario_seleccionado']) ? sanitize_text_field($formData['horario_seleccionado']) : '';
        $nombre = isset($formData['names']['first_name']) ? sanitize_text_field($formData['names']['first_name']) : '';
        $apellido = isset($formData['names']['last_name']) ? sanitize_text_field($formData['names']['last_name']) : '';
        $email = isset($formData['email']) ? sanitize_email($formData['email']) : '';
        $telefono = isset($formData['phone']) ? sanitize_text_field($formData['phone']) : '';
        $direccion = isset($formData['address_1']['address_line_1']) ? sanitize_text_field($formData['address_1']['address_line_1']) : '';
        $nombre_completo = $nombre . ' ' . $apellido;

        // --- Verificación de Cupos (si aplica) ---
        // ... (código de verificación de cupos) ...

        // --- Integración con Sistema Interno ---
        $existing_internal_student = self::query_internal_system_for_student($cedula);
        
        // --- Lógica de Creación/Actualización de Estudiante en SGA ---
        $estudiante_existente_sga = get_posts(array('post_type' => 'estudiante', 'meta_key' => 'cedula', 'meta_value' => $cedula, 'posts_per_page' => 1));

        if ($estudiante_existente_sga) {
            $post_id = $estudiante_existente_sga[0]->ID;
            // Actualizar datos si es necesario
            wp_update_post(['ID' => $post_id, 'post_title' => $nombre_completo]);
            update_field('email', $email, $post_id);
            update_field('telefono', $telefono, $post_id);
            update_field('direccion', $direccion, $post_id);
            $log_title = 'Inscripción Añadida a Estudiante Existente';
        } else {
            $post_data = array(
                'post_title' => $nombre_completo,
                'post_type' => 'estudiante',
                'post_status' => 'publish'
            );
            $post_id = wp_insert_post($post_data);
            update_field('cedula', $cedula, $post_id);
            update_field('email', $email, $post_id);
            update_field('telefono', $telefono, $post_id);
            update_field('direccion', $direccion, $post_id);
            $log_title = 'Nuevo Estudiante Creado';
        }

        // Si el estudiante existe en el sistema interno, guardamos su matrícula
        if ($existing_internal_student && isset($existing_internal_student['matricula'])) {
            update_post_meta($post_id, '_matricula_externa', $existing_internal_student['matricula']);
        }

        // Añadir el curso al perfil del estudiante en SGA
        if (function_exists('add_row')) {
            add_row('cursos_inscritos', array(
                'nombre_curso' => $curso_inscrito,
                'horario' => $horario_inscrito,
                'fecha_inscripcion' => current_time('mysql'),
                'estado' => 'Inscrito'
            ), $post_id);

            // *** INICIO CORRECCIÓN CONTADORES (TIEMPO REAL) ***
            // Borramos los transients (caché) de los contadores.
            // Esto fuerza a que la próxima vez que se cargue el panel principal (o el AJAX de admin),
            // se recalculen los números de "Pendientes" y "Llamadas".
            delete_transient('sga_pending_insc_count');
            delete_transient('sga_pending_calls_count');
            // *** FIN CORRECCIÓN CONTADORES ***

            // --- Lógica de Asignación a Agente Específico (Infotep o General) ---
            $cursos = get_field('cursos_inscritos', $post_id);
            $new_row_index = count($cursos) - 1; // El índice del repeater recién añadido
            
            $next_agent_id = null;
            $agent_role = 'agente'; // Rol por defecto

            // 1. Determinar si el curso es de Infotep
            $course_post = get_posts(['post_type' => 'curso', 'title' => $curso_inscrito, 'posts_per_page' => 1]);
            $is_infotep_course = false;
            
            if ($course_post) {
                $terms = wp_get_post_terms($course_post[0]->ID, 'category', ['fields' => 'slugs']);
                if (!is_wp_error($terms) && in_array('cursos-infotep', $terms)) {
                    $is_infotep_course = true;
                    $agent_role = 'agente_infotep'; // Cambiar rol al rol exclusivo
                }
            }

            // 2. Obtener el siguiente agente en rotación para el rol determinado
            $next_agent_id = SGA_Utils::_get_next_agent_for_assignment($agent_role);

            // 3. Asignar
            if ($next_agent_id) {
                SGA_Utils::_assign_inscription_to_agent($post_id, $new_row_index, $next_agent_id);
                $agent_info = get_userdata($next_agent_id);
                $log_content_agent = "La inscripción de {$nombre_completo} para '{$curso_inscrito}' (Rol: {$agent_role}) fue asignada automáticamente al agente: {$agent_info->display_name}.";
                SGA_Utils::_log_activity('Inscripción Asignada', $log_content_agent, 0);
            }
        }

        // --- Notificar al sistema interno sobre la nueva inscripción (Webhook Saliente) ---
        $webhook_data = [
            'type' => 'new_inscription',
            'student' => [
                'nombre' => $nombre_completo, 'cedula' => $cedula, 'email' => $email,
                'telefono' => $telefono, 'direccion' => $direccion,
            ],
            'course' => [
                'nombre' => $curso_inscrito, 'horario' => $horario_inscrito,
                'fecha_inscripcion' => current_time('c'),
            ],
        ];
        self::send_inscription_webhook($webhook_data);
        
        // --- Enviar Correo de Confirmación al Estudiante ---
        SGA_Utils::_send_pending_payment_email($nombre_completo, $email, $cedula, $curso_inscrito, $horario_inscrito);
        SGA_Utils::_log_activity($log_title, "Estudiante: {$nombre_completo} (Cédula: {$cedula}) se ha inscrito en '{$curso_inscrito}'.", 0);
    }

    /**
     * Envía una petición de prueba a la URL del webhook saliente.
     */
    public static function _send_webhook_test() {
        $test_data = [
            'type' => 'test_connection',
            'message' => 'Esta es una prueba de conexión desde el plugin SGA.',
            'timestamp' => current_time('mysql'),
        ];
        self::send_inscription_webhook($test_data, true);
    }

    /**
     * Envía una petición de prueba a la URL de consulta de estudiantes.
     */
    public static function _query_student_test() {
        $test_cedula = '00000000000'; // Cédula de prueba
        self::query_internal_system_for_student($test_cedula, true);
    }

    /**
     * Envía los datos de una nueva inscripción al sistema interno.
     * @param array $data Datos a enviar.
     * @param bool $is_test Si es una prueba de conexión.
     */
    public static function send_inscription_webhook($data, $is_test = false) {
        $options = get_option('sga_integration_options', []);
        $webhook_url = $options['webhook_url'] ?? '';
        $api_secret = $options['api_secret_key'] ?? '';

        if (empty($webhook_url)) {
            if ($is_test) SGA_Utils::_log_activity('API Test: Webhook Saliente', 'Prueba omitida: La URL no está configurada.', get_current_user_id(), true);
            return;
        }

        $response = wp_remote_post($webhook_url, [
            'method'    => 'POST',
            'headers'   => [
                'Content-Type' => 'application/json',
                'X-SGA-Signature' => $api_secret
            ],
            'body'      => json_encode($data),
            'timeout'   => 30,
        ]);

        $log_title = $is_test ? 'API Test: Webhook Saliente' : 'API Saliente: Webhook Enviado';
        
        if (is_wp_error($response)) {
            $log_content = "Error al contactar la URL: {$webhook_url}. Mensaje: " . $response->get_error_message();
            SGA_Utils::_log_activity($log_title, $log_content, 0, true);
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $log_content = "URL: {$webhook_url}\nCuerpo Enviado: " . json_encode($data, JSON_PRETTY_PRINT) . "\nRespuesta Recibida (Código: {$response_code}):\n" . $response_body;
            SGA_Utils::_log_activity($log_title, $log_content, 0, true);
        }
    }

    /**
     * Consulta el sistema interno para ver si un estudiante ya existe.
     * @param string $cedula La cédula a buscar.
     * @param bool $is_test Si es una prueba de conexión.
     * @return array|false Los datos del estudiante si se encuentra, o false si no.
     */
    public static function query_internal_system_for_student($cedula, $is_test = false) {
        $options = get_option('sga_integration_options', []);
        $query_url = $options['student_query_url'] ?? '';
        $api_secret = $options['api_secret_key'] ?? '';

        if (empty($query_url)) {
             if ($is_test) SGA_Utils::_log_activity('API Test: Consulta Estudiante', 'Prueba omitida: La URL no está configurada.', get_current_user_id(), true);
            return false;
        }

        $full_url = add_query_arg('cedula', $cedula, $query_url);

        $response = wp_remote_get($full_url, [
            'headers' => ['X-SGA-Signature' => $api_secret],
            'timeout' => 30,
        ]);

        $log_title = $is_test ? 'API Test: Consulta Estudiante' : 'API Entrante: Buscando Estudiante';
        
        if (is_wp_error($response)) {
            $log_content = "Error al consultar la URL: {$full_url}. Mensaje: " . $response->get_error_message();
            SGA_Utils::_log_activity($log_title, $log_content, 0, true);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $log_content = "URL Consultada: {$full_url}\nRespuesta Recibida (Código: {$response_code}):\n" . $response_body;
        SGA_Utils::_log_activity($log_title, $log_content, 0, true);

        if ($response_code === 200) {
            $student_data = json_decode($response_body, true);
            return $student_data;
        }

        return false;
    }
}
