<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_Integration
 *
 * Gestiona las integraciones con plugins de terceros y el sistema interno.
 * (Este archivo maneja la ENTRADA de datos desde Fluent Forms)
 */
class SGA_Integration {

    public function __construct() {
        add_action('fluentform/submission_inserted', array($this, 'procesar_inscripcion_y_crear_perfil'), 10, 3);
        
        // --- AÑADIDO PARA EL SHORTCODE DE PRUEBA ---
        add_shortcode('sga_cursos_laravel', array($this, 'render_cursos_laravel_shortcode'));
    }

    /**
     * Procesa el envío de un formulario de inscripción de Fluent Forms.
     * MODIFICADO PARA EL PUNTO 1:
     * Esta función ya no crea el estudiante en WP, sino que envía los datos
     * a la API pública de Laravel (EnrollmentController@store) para que Laravel
     * maneje la lógica de creación y acceso temporal.
     */
    public function procesar_inscripcion_y_crear_perfil($entryId, $formData, $form) {
        
        // --- 1. VALIDAR EL ID DEL FORMULARIO ---
        // Asegúrate de que este es el ID correcto de tu formulario
        // Usamos el '3' de tu código original
        if ($form->id != 3) {
            return;
        }

        // --- 2. MAPEO DE CAMPOS (DE FLUENT FORMS A LARAVEL CONTROLLER) ---
        // Usamos los 'name attributes' de tu formulario original (vistos en tu código)
        // para que coincidan con los que espera EnrollmentController@store en Laravel.

        $tipo_cedula = $formData['input_radio'] ?? 'No soy menor';
        $es_menor = $tipo_cedula !== 'No soy menor';
        $cedula_raw = $formData['cedula_o_identificacion'] ?? '';

        $datos_estudiante = [
            // Campos Principales (Requeridos por Laravel)
            'first_name' => $formData['names']['first_name'] ?? '',
            'last_name'  => $formData['names']['last_name'] ?? '',
            'cedula'     => $cedula_raw, // La cédula (del estudiante o tutor)
            'email'      => $formData['email'] ?? '',
            'phone'      => $formData['phone'] ?? '', // Laravel lo usa para 'home_phone'
            
            // Campos del Curso (Requeridos por Laravel)
            'course_name'     => $formData['nombre_del_curso'] ?? '',  // Tu controller espera el *Nombre* del Módulo
            'schedule_string' => $formData['horario_seleccionado'] ?? '', // Tu controller espera el *Nombre* de la Sección/Horario

            // Campos Opcionales (basado en tu controller de Laravel)
            'mobile_phone' => $formData['phone'] ?? null, // Usamos 'phone' como fallback si 'mobile_phone' no existe
            'address'      => $formData['address_1']['address_line_1'] ?? null,
            
            // Campos de Menor de Edad
            'is_minor_flag' => $tipo_cedula, // 'No soy menor' o el tipo de documento
            
            // Si es menor, la cédula original es la del tutor
            'tutor_cedula' => $es_menor ? $cedula_raw : null, 
            
            // --- CAMPOS ADICIONALES (AJUSTA SEGÚN TU FORMULARIO) ---
            // 'city'         => $formData['address_1']['city'] ?? null,
            // 'birth_date'   => $formData['birth_date'] ?? null,
            // 'gender'       => $formData['gender'] ?? null,
            // 'nationality'  => $formData['nationality'] ?? null,
            // 'how_found'    => $formData['how_found'] ?? null,
            // 'tutor_name'         => $formData['tutor_name'] ?? null,
            // 'tutor_phone'        => $formData['tutor_phone'] ?? null,
            // 'tutor_relationship' => $formData['tutor_relationship'] ?? null,
        ];

        // --- 3. VALIDACIÓN BÁSICA EN WORDPRESS ---
        if (empty($datos_estudiante['cedula']) || empty($datos_estudiante['email']) || empty($datos_estudiante['course_name']) || empty($datos_estudiante['schedule_string'])) {
            SGA_Utils::_log_activity(
                'Error Integración Fluent Forms', 
                'Faltan datos clave (cédula, email, course_name o schedule_string) en el envío del formulario ID: ' . $form->id,
                0,
                true
            );
            return;
        }

        // --- 4. LLAMADA A LA API PÚBLICA DE LARAVEL (/api/enroll) ---
        try {
            // Obtenemos la URL base de Laravel (ej: https://mi-sga.com)
            // Esta es la URL de tu sistema Laravel, que DEBE estar en tus Ajustes de WP
            $options = get_option('sga_integration_options', []);
            // Usamos 'sga_api_url' (del API Client) o 'webhook_url' como fallback.
            $api_base_url = $options['api_url'] ?? ($options['webhook_url'] ?? get_option('sga_api_url'));

            if (empty($api_base_url)) {
                SGA_Utils::_log_activity('Error Integración', 'La "URL de la API" no está configurada en Ajustes > SGA > Integración.', 0, true);
                return;
            }

            // ====================================================================
            // INICIO DE CORRECCIÓN DE URL
            // ====================================================================
            // Tu log muestra que $api_base_url tiene '/api/v1' al final. Lo limpiamos.
            // Esto quita '/api/v1' (y la barra final opcional) de la URL base.
            $api_base_url_limpia = preg_replace('#/api/v1/?$#', '', $api_base_url);

            // El endpoint público que SÍ existe en tu routes/api.php
            $endpoint = '/api/enroll'; 
            $url = rtrim($api_base_url_limpia, '/') . $endpoint;
            // ====================================================================
            // FIN DE CORRECCIÓN DE URL
            // ====================================================================


            SGA_Utils::_log_activity('Integración Fluent Forms', 'Enviando inscripción a Laravel (Ruta Pública): ' . $url . ' | Cédula: ' . $datos_estudiante['cedula'], 0, true);

            $response = wp_remote_post($url, [
                'method'    => 'POST',
                'timeout'   => 30, // 30 segundos
                'headers'   => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'body'      => json_encode($datos_estudiante),
                'sslverify' => false // Desactivar verificación SSL (útil para entornos de desarrollo)
            ]);

            if (is_wp_error($response)) {
                // Error de conexión de WordPress (no pudo conectar)
                $error_message = $response->get_error_message();
                SGA_Utils::_log_activity(
                    'Integración Fluent Forms: Error Conexión', 
                    'Error de cURL/WP_Error al llamar a ' . $url . ': ' . $error_message,
                    0,
                    true
                );
                return;
            }

            $body = wp_remote_retrieve_body($response);
            $response_code = wp_remote_retrieve_response_code($response);
            $data_respuesta = json_decode($body, true);

            if ($response_code >= 200 && $response_code < 300 && ($data_respuesta['status'] ?? '') === 'success') {
                // Éxito (Código 2xx y status: success)
                SGA_Utils::_log_activity(
                    'Integración Fluent Forms: Éxito', 
                    'Laravel procesó la inscripción para la cédula: ' . $datos_estudiante['cedula'] . '. Mensaje: ' . ($data_respuesta['message'] ?? ''),
                    0,
                    true
                );
                
                // --- 5. LÓGICA INTERNA DE WP (POST-INSCRIPCIÓN) ---
                // Ahora que Laravel confirmó, llamamos a la lógica interna de WP
                // para mantener el CPT 'estudiante' sincronizado.
                $this->sga_actualizar_wp_estudiante_post_api($datos_estudiante, $data_respuesta, $es_menor, $form->id);

            } else {
                // Error de lógica de Laravel (Código 4xx, 5xx o status: error)
                $error_message = $data_respuesta['message'] ?? 'Error desconocido.';
                if (!empty($data_respuesta['errors'])) {
                    $error_message .= ' Detalles: ' . json_encode($data_respuesta['errors']);
                }
                SGA_Utils::_log_activity(
                    'Integración Fluent Forms: Error API', 
                    'Laravel devolvió un error (HTTP ' . $response_code . '): ' . $error_message . ' | Payload: ' . json_encode($datos_estudiante),
                    0,
                    true
                );
            }

        } catch (\Exception $e) {
            // Error catastrófico
            SGA_Utils::_log_activity(
                'Integración Fluent Forms: Error PHP', 
                'No se pudo conectar con la API de Laravel: ' . $e->getMessage(),
                0,
                true
            );
        }
        
        // --- FIN DE LA NUEVA LÓGICA ---
    }


    /**
     * NUEVA FUNCIÓN HELPER (PRIVADA)
     * * Esta función se llama DESPUÉS de que Laravel confirma la inscripción.
     * Mantiene la lógica original de crear/actualizar el CPT 'estudiante' en WordPress
     * y asignar el agente, pero YA NO maneja la lógica de inscripción (ACF Repeaters),
     * ya que eso ahora lo hace Laravel.
     *
     * @param array $datos_estudiante Datos enviados a Laravel
     * @param array $data_respuesta Datos recibidos de Laravel
     * @param bool $es_menor Flag de la lógica original
     * @param int $formId ID del formulario
     */
    private function sga_actualizar_wp_estudiante_post_api($datos_estudiante, $data_respuesta, $es_menor, $formId) {
        
        $cedula_raw = $datos_estudiante['cedula'];
        $nombre_completo = $datos_estudiante['first_name'] . ' ' . $datos_estudiante['last_name'];
        $post_id = null;
        $log_title = '';

        // 1. Determinar la cédula para buscar y guardar (lógica de menores original)
        $cedula_para_buscar = $cedula_raw;
        $cedula_para_guardar = $cedula_raw;

        if ($es_menor) {
            // Es menor, se crea un ID único: CEDULADELTUTOR-ID (incremental)
            $option_key = 'sga_tutor_counter_' . $cedula_raw;
            $counter = (int) get_option($option_key, 0);
            $new_counter = $counter + 1;
            $cedula_para_guardar = $cedula_raw . '-' . $new_counter;
            update_option($option_key, $new_counter);
        }

        // 2. Buscar al estudiante en WP
        $estudiante_existente_sga = null;
        if (!$es_menor) {
             $estudiante_existente_sga = get_posts(array(
                 'post_type' => 'estudiante', 
                 'meta_key' => 'cedula', 
                 'meta_value' => $cedula_para_buscar, 
                 'posts_per_page' => 1
            ));
        }

        // 3. Crear o Actualizar el CPT 'estudiante' en WP
        if ($estudiante_existente_sga && !$es_menor) {
            // Estudiante existe
            $post_id = $estudiante_existente_sga[0]->ID;
            $nombre_completo = $estudiante_existente_sga[0]->post_title; // Usar nombre existente (Regla 3)
            
            // Actualizar datos de contacto
            wp_update_post(['ID' => $post_id, 'post_title' => $nombre_completo]);
            update_field('email', $datos_estudiante['email'], $post_id);
            update_field('telefono', $datos_estudiante['phone'], $post_id);
            update_field('direccion', $datos_estudiante['address'], $post_id);
            $log_title = 'Perfil de Estudiante (WP) Actualizado';

        } else {
            // Estudiante es nuevo O es menor
            $post_data = array(
                'post_title' => $nombre_completo, // Usa el nombre del form
                'post_type' => 'estudiante',
                'post_status' => 'publish'
            );
            $post_id = wp_insert_post($post_data);
            
            update_field('cedula', $cedula_para_guardar, $post_id);
            update_field('email', $datos_estudiante['email'], $post_id);
            update_field('telefono', $datos_estudiante['phone'], $post_id);
            update_field('direccion', $datos_estudiante['address'], $post_id);
            $log_title = $es_menor ? 'Nuevo Perfil de Estudiante Menor Creado (WP)' : 'Nuevo Perfil de Estudiante Creado (WP)';
        }

        SGA_Utils::_log_activity($log_title, "Perfil de Estudiante CPT 'estudiante' (ID: $post_id) sincronizado para la cédula: {$cedula_para_guardar}.", 0);

        // --- 4. MANEJO DE INSCRIPCIÓN (SIMPLIFICADO) ---
        // Ya no manejamos duplicados o actualizaciones de horario aquí (Reglas 1 y 2)
        // Laravel lo hizo. Solo necesitamos añadir la fila de ACF si es la primera vez.
        
        if (function_exists('get_field') && function_exists('add_row')) {
            
            $cursos_actuales = get_field('cursos_inscritos', $post_id);
            $inscripcion_existente_index = -1;
            
            // Re-buscamos solo para la asignación de agente
            if (is_array($cursos_actuales)) {
                foreach ($cursos_actuales as $index => $curso) {
                    if (isset($curso['nombre_curso']) && $curso['nombre_curso'] === $datos_estudiante['course_name']) {
                        $inscripcion_existente_index = $index;
                        break;
                    }
                }
            }

            $new_row_index = -1;
            $send_email = false;

            if ($inscripcion_existente_index === -1) {
                // Si la fila de ACF no existe, la creamos (solo para registro y asignación)
                add_row('cursos_inscritos', array(
                    'nombre_curso' => $datos_estudiante['course_name'],
                    'horario' => $datos_estudiante['schedule_string'],
                    'fecha_inscripcion' => current_time('mysql'),
                    'estado' => 'Inscrito' // Estado base en WP
                ), $post_id);

                delete_transient('sga_pending_insc_count');
                delete_transient('sga_pending_calls_count');
                delete_transient('sga_pending_insc_counts_v2');
                delete_transient('sga_pending_calls_counts_v2');

                $cursos_nuevos = get_field('cursos_inscritos', $post_id);
                $new_row_index = count($cursos_nuevos) - 1;
                $send_email = true; // Fue una nueva inscripción (según Laravel)

            } else {
                // La fila de ACF ya existía. Laravel manejó la lógica.
                // Solo necesitamos el índice para la asignación de agente.
                $new_row_index = $inscripcion_existente_index;
                $send_email = false; // No es nueva, solo se actualizó (o ya existía)
            }

            // --- 5. ASIGNACIÓN DE AGENTE (Lógica original) ---
            $next_agent_id = null;
            $agent_role = 'agente'; 
            $course_post = get_posts(['post_type' => 'curso', 'title' => $datos_estudiante['course_name'], 'posts_per_page' => 1]);
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
                $log_content_agent = "La inscripción de {$nombre_completo} para '{$datos_estudiante['course_name']}' (Rol: {$agent_role}) fue asignada automáticamente al agente: {$agent_info->display_name}.";
                SGA_Utils::_log_activity('Inscripción Asignada (WP)', $log_content_agent, 0);
            }

            // --- 6. ENVIAR CORREO (Solo si es nueva inscripción) ---
            if ($send_email) {
                // Usamos la $cedula_raw (original) para el portal de pagos
                SGA_Utils::_send_pending_payment_email(
                    $nombre_completo, 
                    $datos_estudiante['email'], 
                    $cedula_raw, 
                    $datos_estudiante['course_name'], 
                    $datos_estudiante['schedule_string']
                );
            }
        }
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

    // --- INICIO DE NUEVA FUNCIÓN PARA SHORTCODE DE PRUEBA ---
            
    /**
     * Renderiza el shortcode [sga_cursos_laravel]
     *
     * Llama a la API de Laravel y muestra una lista de cursos.
     */
    public function render_cursos_laravel_shortcode($atts) {
        // 1. Cargar las clases necesarias (API Client y Utils para logs)
        if (!class_exists('SGA_API_Client')) {
            $client_file = plugin_dir_path(__FILE__) . 'class-sga-api-client.php';
            if (file_exists($client_file)) {
                require_once $client_file;
            } else {
                return '<div style="color: red;">Error: No se pudo cargar class-sga-api-client.php.</div>';
            }
        }
        
        if (!class_exists('SGA_Utils')) {
            $utils_file = plugin_dir_path(__FILE__) . 'class-sga-utils.php';
            if (file_exists($utils_file)) {
                require_once $utils_file;
            }
        }

        // 2. Iniciar el cliente y llamar a la API
        $api_client = new SGA_API_Client();
        $response = $api_client->get_all_courses(); // Llama a /api/v1/courses
        
        // 3. Preparar el HTML
        ob_start();

        if (is_wp_error($response)) {
            // Error de WordPress (ej. cURL timeout, ngrok caído)
            echo '<div style="color: red; border: 1px solid red; padding: 10px;">';
            echo '<strong>Error de Conexión de WordPress:</strong> ' . esc_html($response->get_error_message());
            echo '<p><small>Asegúrate de que ngrok y el servidor de Laravel (php artisan serve) estén corriendo.</small></p>';
            echo '</div>';
            
        } elseif (isset($response['status']) && $response['status'] === 'success') {
            // Éxito
            $courses = $response['data'];
            
            echo '<h3>Cursos Disponibles (conectado a Laravel):</h3>';
            
            if (empty($courses)) {
                 echo '<p>La conexión fue exitosa, pero no se encontraron cursos en el sistema base.</p>';
            } else {
                 echo '<ul>';
                 foreach ($courses as $course) {
                     // Asumo que la columna se llama 'name' (de Laravel)
                     // Si se llama 'nombre' o 'nombre_curso', debes cambiar 'name' aquí abajo
                     echo '<li>' . esc_html($course['name']) . ' (ID: ' . esc_html($course['id']) . ')</li>';
                 }
                 echo '</ul>';
            }
        } else {
            // Error devuelto por la API de Laravel (ej. 401, 404, 500)
            $error_message = $response['message'] ?? 'Error desconocido al contactar la API.';
            echo '<div style="color: red; border: 1px solid red; padding: 10px;">';
            echo '<strong>Error de la API de Laravel:</strong> ' . esc_html($error_message);
            echo '<p><small>Esto puede ser un error 401 (Token incorrecto) o 404 (URL incorrecta, ¿olvidaste /api/v1?).</small></p>';
            echo '<!-- ' . esc_html(print_r($response, true)) . ' -->'; // Comentario HTML para debug
        }
        
        return ob_get_clean();
    }
    // --- FIN DE NUEVA FUNCIÓN ---
}