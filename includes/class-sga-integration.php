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

    // ... (Función _sga_format_schedule_string eliminada, ya no es necesaria) ...

    /**
     * Procesa el envío de un formulario de inscripción de Fluent Forms.
     * --- MODIFICADO DE NUEVO ---
     * Ahora "traduce" los nombres de texto del formulario a IDs antes de enviarlos a Laravel.
     */
    public function procesar_inscripcion_y_crear_perfil($entryId, $formData, $form) {
        
        // --- 1. VALIDAR EL ID DEL FORMULARIO ---
        if ($form->id != 3) {
            return;
        }

        // ====================================================================
        // INICIO: LIMPIEZA DE DEBUG (Logs de debug eliminados)
        // ====================================================================

        // --- 2. MAPEO DE CAMPOS (DE FLUENT FORMS A LARAVEL CONTROLLER) ---
        $tipo_cedula = $formData['input_radio'] ?? 'No soy menor';
        $es_menor = $tipo_cedula !== 'No soy menor';
        $cedula_raw = $formData['cedula_o_identificacion'] ?? '';
        
        // ====================================================================
        // INICIO DE LA LÓGICA DE "TRADUCCIÓN"
        // ====================================================================

        // 2A. OBTENER LOS TEXTOS DEL FORMULARIO
        // *** CORRECCIÓN BASADA EN EL LOG: Se usa 'nombre_del_curso' ***
        $course_name_from_form = $formData['nombre_del_curso'] ?? null; 
        $schedule_text_from_form = $formData['horario_seleccionado'] ?? null; // Esto estaba correcto

        
        // 2B. "TRADUCIR" EL NOMBRE DEL CURSO A WP_COURSE_ID
        $wp_course_id = null;
        if (!empty($course_name_from_form)) {
            // Buscar el post CPT 'curso' que tenga ESE título exacto
            $course_post = get_page_by_title($course_name_from_form, OBJECT, 'curso');
            if ($course_post) {
                $wp_course_id = $course_post->ID;
            }
        }

        // 2C. "TRADUCIR" EL TEXTO DEL HORARIO A WP_SCHEDULE_STRING (ID)
        $wp_schedule_id_string = null;
        if ($wp_course_id && !empty($schedule_text_from_form) && class_exists('SGA_Utils')) {
            
            // 1. Obtener la lista de horarios "oficiales" de este curso
            $schedules_from_util = SGA_Utils::get_schedules_for_wp_course($wp_course_id);

            // 2. Necesitamos recrear el texto
            $schedules_raw_acf = get_field('horarios_del_curso', $wp_course_id);

            if ($schedules_raw_acf && $schedules_from_util) {
                foreach ($schedules_raw_acf as $index => $fila_acf) {
                    
                    // Recrear el texto exacto del formulario (basado en tu shortcode original)
                    $current_schedule_text = "{$fila_acf['dias_de_la_semana']} a las {$fila_acf['hora']} ({$fila_acf['modalidad']})";
                    
                    // 3. Comparar con el texto que envió el formulario
                    if ($current_schedule_text === $schedule_text_from_form) {
                        if(isset($schedules_from_util[$index])) {
                             $wp_schedule_id_string = $schedules_from_util[$index]['id']; // ej: "sabado_0900_1200"
                             break;
                        }
                    }
                }
            }
        }
        
        // ====================================================================
        // FIN DE LA LÓGICA DE "TRADUCCIÓN"
        // ====================================================================


        $datos_estudiante = [
            // Campos Principales (Requeridos por Laravel)
            'first_name' => $formData['names']['first_name'] ?? '',
            'last_name'  => $formData['names']['last_name'] ?? '',
            'cedula'     => $cedula_raw, 
            'email'      => $formData['email'] ?? '',
            'phone'      => $formData['phone'] ?? '', 
            
            // --- DATOS "TRADUCIDOS" ---
            'wp_schedule_string' => $wp_schedule_id_string, // <-- El ID del horario (ej: sabado_0900_1200)
            'wp_course_id'       => $wp_course_id,          // <-- El ID del curso (ej: 6804)
            // --- FIN DATOS "TRADUCIDOS" ---
            
            'course_name_from_wp' => $course_name_from_form ?? 'Curso no encontrado', // <-- El texto original del curso

            // Campos Opcionales
            'mobile_phone' => $formData['phone'] ?? null, 
            'address'      => $formData['address_1']['address_line_1'] ?? null,
            
            // Campos de Menor de Edad
            'is_minor_flag' => $tipo_cedula, 
            'tutor_cedula' => $es_menor ? $cedula_raw : null, 
        ];

        // --- 3. VALIDACIÓN BÁSICA EN WORDPRESS (MODIFICADA) ---
        // Esta validación ahora usa los IDs "traducidos"
        if (empty($datos_estudiante['cedula']) || empty($datos_estudiante['email']) || empty($datos_estudiante['wp_course_id']) || empty($datos_estudiante['wp_schedule_string'])) {
            
            SGA_Utils::_log_activity(
                'Error Integración Fluent Forms', 
                'Faltan datos clave (cédula, email, wp_course_id o wp_schedule_string) DESPUÉS de la traducción. Formulario ID: ' . $form->id . ' | Payload: ' . json_encode($datos_estudiante),
                0,
                true
            );
            return;
        }

        // --- 4. LLAMADA A LA API PÚBLICA DE LARAVEL (/api/enroll) ---
        try {
            // (El resto de la función: wp_remote_post, sga_actualizar_wp_estudiante_post_api, etc. sigue igual)
            
            // ... (Código idéntico al de tu archivo) ...
            
            // Obtenemos la URL base de Laravel
            $options = get_option('sga_integration_options', []);
            $api_base_url = $options['api_url'] ?? ($options['webhook_url'] ?? get_option('sga_api_url'));

            if (empty($api_base_url)) {
                SGA_Utils::_log_activity('Error Integración', 'La "URL de la API" no está configurada en Ajustes > SGA > Integración.', 0, true);
                return;
            }

            // Limpiar /api/v1 del final
            $api_base_url_limpia = preg_replace('#/api/v1/?$#', '', $api_base_url);
            $endpoint = '/api/enroll'; 
            $url = rtrim($api_base_url_limpia, '/') . $endpoint;

            SGA_Utils::_log_activity('Integración Fluent Forms', 'Enviando inscripción a Laravel (Ruta Pública): ' . $url . ' | Cédula: ' . $datos_estudiante['cedula'] . ' | WP Course ID: ' . $datos_estudiante['wp_course_id'], 0, true);

            $response = wp_remote_post($url, [
                'method'    => 'POST',
                'timeout'   => 30, 
                'headers'   => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'body'      => json_encode($datos_estudiante), // <-- Enviamos el payload con los IDs traducidos
                'sslverify' => false 
            ]);

            if (is_wp_error($response)) {
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
                SGA_Utils::_log_activity(
                    'Integración Fluent Forms: Éxito', 
                    'Laravel procesó la inscripción para la cédula: ' . $datos_estudiante['cedula'] . '. Mensaje: ' . ($data_respuesta['message'] ?? ''),
                    0,
                    true
                );
                
                // --- 5. LÓGICA INTERNA DE WP (POST-INSCRIPCIÓN) ---
                // Esta función ahora recibe los IDs correctos en $datos_estudiante
                $this->sga_actualizar_wp_estudiante_post_api($datos_estudiante, $data_respuesta, $es_menor, $form->id); 

            } else {
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
            SGA_Utils::_log_activity(
                'Integración Fluent Forms: Error PHP', 
                'No se pudo conectar con la API de Laravel: ' . $e->getMessage(),
                0,
                true
            );
        }
    }


    /**
     * NUEVA FUNCIÓN HELPER (PRIVADA)
     * ... (Esta función no necesita cambios, ya que ahora recibe los IDs correctos) ...
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
        // ... (resto de la función) ...
        
        if (function_exists('get_field') && function_exists('add_row')) {
            
            $cursos_actuales = get_field('cursos_inscritos', $post_id);
            $inscripcion_existente_index = -1;
            
            // Re-buscamos solo para la asignación de agente
            if (is_array($cursos_actuales)) {
                foreach ($cursos_actuales as $index => $curso) {
                    if (isset($curso['nombre_curso']) && $curso['nombre_curso'] === $datos_estudiante['course_name_from_wp']) {
                        $inscripcion_existente_index = $index;
                        break;
                    }
                }
            }

            $new_row_index = -1;
            $send_email = false;

            if ($inscripcion_existente_index === -1) {
                // Si la fila de ACF no existe, la creamos (solo para registro y asignación)
                
                // CORRECCIÓN: El campo 'horario' de ACF espera el texto, no el ID.
                // Vamos a buscar el texto del horario que corresponde al ID.
                $horario_texto_legible = $datos_estudiante['wp_schedule_string']; // Fallback
                
                // $datos_estudiante['wp_schedule_string'] ES EL ID (ej: sabado_0900_1200)
                // $datos_estudiante['wp_course_id'] ES EL ID (ej: 6804)
                
                // (La función de Utils ya la tenemos cargada, no hace falta llamarla de nuevo)
                // (Necesitamos el texto que el usuario seleccionó, que *ya no* está en $datos_estudiante)
                
                // --- SOLUCIÓN: Re-buscar el texto legible ---
                // (Esto es un poco ineficiente, pero es la única forma si no pasamos el texto original)
                // Vamos a buscar el texto del horario que corresponde al ID.
                $horario_texto_legible = 'Horario no encontrado'; // Fallback
                if (class_exists('SGA_Utils')) {
                    $horarios_del_curso = SGA_Utils::get_schedules_for_wp_course($datos_estudiante['wp_course_id']);
                    foreach ($horarios_del_curso as $sched) {
                        if ($sched['id'] === $datos_estudiante['wp_schedule_string']) {
                            $start_text = date('h:i A', strtotime($sched['start_time']));
                            $end_text = date('h:i A', strtotime($sched['end_time']));
                            $horario_texto_legible = "{$sched['day']} de {$start_text} a {$end_text}";
                            break;
                        }
                    }
                }
                // --- FIN SOLUCIÓN ---


                add_row('cursos_inscritos', array(
                    'nombre_curso' => $datos_estudiante['course_name_from_wp'], // Usar el nombre original
                    'horario' => $horario_texto_legible, // <-- Guardar el TEXTO LEGIBLE
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
                $new_row_index = $inscripcion_existente_index;
                $send_email = false; // No es nueva
            }

            // --- 5. ASIGNACIÓN DE AGENTE (Lógica original) ---
            $next_agent_id = null;
            $agent_role = 'agente'; 
            $course_post_id = $datos_estudiante['wp_course_id'];
            $is_infotep_course = false;
            
            if ($course_post_id) {
                $terms = wp_get_post_terms($course_post_id, 'category', ['fields' => 'slugs']);
                if (!is_wp_error($terms) && in_array('cursos-infotep', $terms)) {
                    $is_infotep_course = true;
                    $agent_role = 'agente_infotep'; 
                }
            }

            $next_agent_id = SGA_Utils::_get_next_agent_for_assignment($agent_role);

            if ($next_agent_id && $new_row_index !== -1) {
                SGA_Utils::_assign_inscription_to_agent($post_id, $new_row_index, $next_agent_id);
                $agent_info = get_userdata($next_agent_id);
                $log_content_agent = "La inscripción de {$nombre_completo} para '{$datos_estudiante['course_name_from_wp']}' (Rol: {$agent_role}) fue asignada automáticamente al agente: {$agent_info->display_name}.";
                SGA_Utils::_log_activity('Inscripción Asignada (WP)', $log_content_agent, 0);
            }

            // --- 6. ENVIAR CORREO (Solo si es nueva inscripción) ---
            if ($send_email) {
                // Re-buscar el texto legible del horario si no lo teníamos
                if (!isset($horario_texto_legible)) {
                    $horario_texto_legible = 'No especificado';
                    if (class_exists('SGA_Utils')) {
                        $horarios_del_curso = SGA_Utils::get_schedules_for_wp_course($datos_estudiante['wp_course_id']);
                        foreach ($horarios_del_curso as $sched) {
                            if ($sched['id'] === $datos_estudiante['wp_schedule_string']) {
                                $start_text = date('h:i A', strtotime($sched['start_time']));
                                $end_text = date('h:i A', strtotime($sched['end_time']));
                                $horario_texto_legible = "{$sched['day']} de {$start_text} a {$end_text}";
                                break;
                            }
                        }
                    }
                }
                
                SGA_Utils::_send_pending_payment_email(
                    $nombre_completo, 
                    $datos_estudiante['email'], 
                    $cedula_raw, 
                    $datos_estudiante['course_name_from_wp'], 
                    $horario_texto_legible // <-- Usar el TEXTO LEGIBLE para el correo
                );
            }
        }
    }


    /**
     * Envía una petición de prueba a la URL del webhook saliente.
     */
    public static function _send_webhook_test() {
        // ... (código sin cambios) ...
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
        // ... (código sin cambios) ...
        $test_cedula = '00000000000'; // Cédula de prueba
        self::query_internal_system_for_student($test_cedula, true);
    }

    /**
     * Envía los datos de una nueva inscripción al sistema interno.
     * @param array $data Datos a enviar.
     * @param bool $is_test Si es una prueba de conexión.
     */
    public static function send_inscription_webhook($data, $is_test = false) {
        // ... (código sin cambios) ...
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
        // ... (código sin cambios) ...
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
        // ... (código sin cambios) ...
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
                     // --- INICIO DE LA CORRECCIÓN DE SINTAXIS ---
                     echo '<li>' . esc_html($course['name']) . ' (ID: ' . esc_html($course['id']) . ')</li>';
                     // --- FIN DE LA CORRECCIÓN DE SINTAXIS ---
                 }
                 echo '</ul>';
            }
        } else {
            // Error devuelto por la API de Laravel (ej. 401, 404, 500)
            $error_message = $response['message'] ?? 'Error desconocido al contactar la API.';
            echo '<div style="color: red; border: 1px solid red; padding: 10px;">';
            echo '<strong>Error de la API de Laravel:</strong> ' . esc_html($error_message);
            echo '<p><small>Esto puede ser un error 401 (Token incorrecto) o 404 (URL incorrecta, ¿olvidaste /api/v1?).</Vp>';
            echo '<!-- ' . esc_html(print_r($response, true)) . ' -->'; // Comentario HTML para debug
        }
        
        return ob_get_clean();
    }
    // --- FIN DE NUEVA FUNCIÓN ---
}