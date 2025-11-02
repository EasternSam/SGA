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

        // --- 1. CAPTURAR DATOS DEL FORMULARIO ---
        $cedula_raw = isset($formData['cedula_o_identificacion']) ? sanitize_text_field($formData['cedula_o_identificacion']) : '';
        if (empty($cedula_raw)) return; // Cédula es obligatoria

        $curso_inscrito = isset($formData['nombre_del_curso']) ? sanitize_text_field($formData['nombre_del_curso']) : '';
        $horario_inscrito = isset($formData['horario_seleccionado']) ? sanitize_text_field($formData['horario_seleccionado']) : '';
        $nombre = isset($formData['names']['first_name']) ? sanitize_text_field($formData['names']['first_name']) : '';
        $apellido = isset($formData['names']['last_name']) ? sanitize_text_field($formData['names']['last_name']) : '';
        $email = isset($formData['email']) ? sanitize_email($formData['email']) : '';
        $telefono = isset($formData['phone']) ? sanitize_text_field($formData['phone']) : '';
        $direccion = isset($formData['address_1']['address_line_1']) ? sanitize_text_field($formData['address_1']['address_line_1']) : '';
        $nombre_completo_raw = trim($nombre . ' ' . $apellido);

        // --- 2. MANEJO DE LÓGICA DE MENORES (REGLA 4) ---
        // *** CORRECCIÓN: Usando 'input_radio' como nombre del campo ***
        $tipo_cedula = $formData['input_radio'] ?? 'No soy menor';
        $es_menor = $tipo_cedula !== 'No soy menor';

        $cedula_para_buscar = $cedula_raw;
        $cedula_para_guardar = $cedula_raw;
        $estudiante_existente_sga = null;
        $nombre_completo = $nombre_completo_raw;
        $post_id = null;
        $log_title = '';

        if ($es_menor) {
            // Es menor, se crea un ID único: CEDULADELTUTOR-ID (incremental)
            
            // 1. Definir la clave única para el contador de este tutor
            $option_key = 'sga_tutor_counter_' . $cedula_raw;
            
            // 2. Obtener el contador actual, o 0 si no existe
            $counter = (int) get_option($option_key, 0);
            
            // 3. Incrementar el contador
            $new_counter = $counter + 1;
            
            // 4. Crear el ID único para guardar
            $cedula_para_guardar = $cedula_raw . '-' . $new_counter;
            
            // 5. Actualizar el contador en la base de datos
            update_option($option_key, $new_counter);
            
            // No buscamos estudiante existente, forzamos creación
        } else {
            // No es menor, buscar por cédula
            $estudiante_existente_sga = get_posts(array('post_type' => 'estudiante', 'meta_key' => 'cedula', 'meta_value' => $cedula_para_buscar, 'posts_per_page' => 1));
        }

        // --- 3. CREAR O ACTUALIZAR PERFIL DE ESTUDIANTE (REGLA 3) ---
        if ($estudiante_existente_sga && !$es_menor) {
            // Estudiante existe Y NO es menor
            $post_id = $estudiante_existente_sga[0]->ID;
            
            // **REGLA 3: Misma cédula, diferente nombre -> Usar nombre existente**
            $nombre_completo = $estudiante_existente_sga[0]->post_title; // Sobrescribir nombre del form con el de la BD
            
            // Actualizar datos de contacto
            wp_update_post(['ID' => $post_id, 'post_title' => $nombre_completo]); // Asegurar el título
            update_field('email', $email, $post_id);
            update_field('telefono', $telefono, $post_id);
            update_field('direccion', $direccion, $post_id);
            $log_title = 'Inscripción Añadida a Estudiante Existente';
        
        } else {
            // Estudiante es nuevo O es menor (forzamos creación)
            $post_data = array(
                'post_title' => $nombre_completo, // Usa el nombre del form
                'post_type' => 'estudiante',
                'post_status' => 'publish'
            );
            $post_id = wp_insert_post($post_data);
            
            // Guardar la cédula (raw o la modificada para menores)
            update_field('cedula', $cedula_para_guardar, $post_id);
            update_field('email', $email, $post_id);
            update_field('telefono', $telefono, $post_id);
            update_field('direccion', $direccion, $post_id);
            
            $log_title = $es_menor ? 'Nuevo Estudiante Menor Creado' : 'Nuevo Estudiante Creado';
        }

        // --- (Lógica de sistema interno existente) ---
        $existing_internal_student = self::query_internal_system_for_student($cedula_raw);
        if ($existing_internal_student && isset($existing_internal_student['matricula'])) {
            update_post_meta($post_id, '_matricula_externa', $existing_internal_student['matricula']);
        }

        // --- 4. MANEJO DE INSCRIPCIÓN (REGLAS 1 y 2) ---
        if (function_exists('get_field') && function_exists('add_row') && function_exists('update_sub_field')) {
            
            $cursos_actuales = get_field('cursos_inscritos', $post_id);
            $inscripcion_existente_index = -1;
            $horario_existente = null;
            
            if (is_array($cursos_actuales)) {
                foreach ($cursos_actuales as $index => $curso) {
                    if (isset($curso['nombre_curso']) && $curso['nombre_curso'] === $curso_inscrito) {
                        $inscripcion_existente_index = $index;
                        $horario_existente = $curso['horario'];
                        break;
                    }
                }
            }

            $new_row_index = -1; // Inicializar
            $send_email = false; // Flag para decidir si se envía email

            if ($inscripcion_existente_index !== -1) {
                // El curso ya existe para este estudiante
                
                if ($horario_existente === $horario_inscrito) {
                    // **REGLA 1: Duplicado exacto. Descartar.**
                    SGA_Utils::_log_activity('Inscripción Duplicada Descartada', "Estudiante: {$nombre_completo} (Cédula: {$cedula_para_buscar}) ya estaba inscrito en '{$curso_inscrito}' con el mismo horario.", 0);
                    return; // Termina la ejecución
                
                } else {
                    // **REGLA 2: Actualizar horario.**
                    update_sub_field(array('cursos_inscritos', $inscripcion_existente_index + 1, 'horario'), $horario_inscrito, $post_id);
                    SGA_Utils::_log_activity('Horario Actualizado', "Estudiante: {$nombre_completo} cambió horario de '{$curso_inscrito}' ('{$horario_existente}') a '{$horario_inscrito}'.", 0);
                    $new_row_index = $inscripcion_existente_index; // Usar este índice para asignación de agente
                    $send_email = false; // No enviar email por solo actualizar horario
                }
            
            } else {
                // Inscripción nueva
                add_row('cursos_inscritos', array(
                    'nombre_curso' => $curso_inscrito,
                    'horario' => $horario_inscrito,
                    'fecha_inscripcion' => current_time('mysql'),
                    'estado' => 'Inscrito'
                ), $post_id);

                // Borrar transients (caché) de contadores
                delete_transient('sga_pending_insc_count');
                delete_transient('sga_pending_calls_count');
                delete_transient('sga_pending_insc_counts_v2'); // Borrar el nuevo transient plural
                delete_transient('sga_pending_calls_counts_v2'); // Borrar el nuevo transient plural

                $cursos_nuevos = get_field('cursos_inscritos', $post_id);
                $new_row_index = count($cursos_nuevos) - 1;
                $send_email = true; // Enviar email para nuevas inscripciones
                
                SGA_Utils::_log_activity($log_title, "Estudiante: {$nombre_completo} (Cédula: {$cedula_para_guardar}) se ha inscrito en '{$curso_inscrito}'.", 0);
            }

            // --- 5. ASIGNACIÓN DE AGENTE (Lógica original) ---
            // Esta lógica ahora se ejecuta para inscripciones nuevas O actualizadas
            
            $next_agent_id = null;
            $agent_role = 'agente'; 
            $course_post = get_posts(['post_type' => 'curso', 'title' => $curso_inscrito, 'posts_per_page' => 1]);
            $is_infotep_course = false;
            
            if ($course_post) {
                $terms = wp_get_post_terms($course_post[0]->ID, 'category', ['fields' => 'slugs']);
                if (!is_wp_error($terms) && in_array('cursos-infotep', $terms)) {
                    $is_infotep_course = true;
                    $agent_role = 'agente_infotep'; 
                }
            }

            $next_agent_id = SGA_Utils::_get_next_agent_for_assignment($agent_role);

            if ($next_agent_id && $new_row_index !== -1) {
                SGA_Utils::_assign_inscription_to_agent($post_id, $new_row_index, $next_agent_id);
                $agent_info = get_userdata($next_agent_id);
                $log_content_agent = "La inscripción de {$nombre_completo} para '{$curso_inscrito}' (Rol: {$agent_role}) fue asignada automáticamente al agente: {$agent_info->display_name}.";
                SGA_Utils::_log_activity('Inscripción Asignada', $log_content_agent, 0);
            }

            // --- 6. NOTIFICAR SISTEMA EXTERNO (Lógica original) ---
            $webhook_data = [
                'type' => 'new_inscription',
                'student' => [
                    'nombre' => $nombre_completo, 'cedula' => $cedula_raw, 'email' => $email,
                    'telefono' => $telefono, 'direccion' => $direccion,
                ],
                'course' => [
                    'nombre' => $curso_inscrito, 'horario' => $horario_inscrito,
                    'fecha_inscripcion' => current_time('c'),
                ],
            ];
            self::send_inscription_webhook($webhook_data);
            
            // --- 7. ENVIAR CORREO (Solo si es nueva inscripción) ---
            if ($send_email) {
                // Usamos la $cedula_raw (original) para el portal de pagos, no la modificada
                SGA_Utils::_send_pending_payment_email($nombre_completo, $email, $cedula_raw, $curso_inscrito, $horario_inscrito);
            }

        } // fin if function_exists
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
            $log_content = "Error al contactar la URL: {$full_url}. Mensaje: " . $response->get_error_message();
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

