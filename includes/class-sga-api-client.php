<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_API_Client
 *
 * Cliente HTTP para realizar llamadas SALIENTES a la API de Laravel (SGA).
 * (Este archivo maneja la SALIDA de datos hacia Laravel)
 */
class SGA_API_Client {

    private $api_url;
    private $api_token;

    public function __construct() {
        // --- INICIO DE LA CORRECCIÓN ---
        // Cargar las opciones de API guardadas por class-sga-admin.php
        // Las opciones 'sga_api_url' y 'sga_api_key' se guardan como opciones
        // independientes, no dentro de 'sga_integration_options'.
        
        // $options = get_option('sga_integration_options', []); // <-- INCORRECTO
        // $this->api_url = $options['api_url'] ?? get_option('sga_api_url', ''); // <-- INCORRECTO
        // $this->api_token = $options['api_token'] ?? ''; // <-- INCORRECTO

        $this->api_url = get_option('sga_api_url', ''); 
        $this->api_token = get_option('sga_api_key', ''); // <-- Clave correcta: 'sga_api_key'
        // --- FIN DE LA CORRECCIÓN ---
    }

    /**
     * Realiza una petición GET a un endpoint protegido de la API de Laravel.
     *
     * @param string $endpoint El endpoint al que se llamará (ej. 'v1/courses')
     * @return array|WP_Error Los datos decodificados o un error.
     */
    private function get($endpoint) {
        if (empty($this->api_url) || empty($this->api_token)) {
            SGA_Utils::_log_activity('API Client Error', 'La URL de la API o el Token no están configurados en Ajustes > SGA.', 0, true);
            return new WP_Error('api_config_error', 'La URL de la API o el Token no están configurados.');
        }

        // --- INICIO DE LA CORRECCIÓN ---
        // La URL base ($this->api_url) ya contiene el prefijo /api/v1 (según las instrucciones en class-sga-admin.php).
        // No debemos añadir '/api/' otra vez.
        
        // $url = rtrim($this->api_url, '/') . '/api/' . ltrim($endpoint, '/'); // <-- INCORRECTO
        $url = rtrim($this->api_url, '/') . '/' . ltrim($endpoint, '/'); // <-- CORRECTO
        // --- FIN DE LA CORRECCIÓN ---


        $args = [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
            'sslverify' => false // Desactivar para desarrollo local (ej. Ngrok)
        ];

        SGA_Utils::_log_activity('API Client GET', 'Iniciando llamada GET a: ' . $url, 0, true);
        $response = wp_remote_get($url, $args);

        return $this->handle_response($response, $url);
    }

    // ====================================================================
    // NUEVO MÉTODO POST (PUNTO 1)
    // ====================================================================

    /**
     * Realiza una petición POST a un endpoint protegido de la API de Laravel.
     *
     * @param string $endpoint El endpoint al que se llamará (ej. 'v1/wordpress/new-inscription')
     * @param array $data Los datos (body) que se enviarán como JSON.
     * @return array|WP_Error Los datos decodificados o un error.
     */
    public function post($endpoint, $data = []) {
        if (empty($this->api_url) || empty($this->api_token)) {
            SGA_Utils::_log_activity('API Client Error', 'La URL de la API o el Token no están configurados en Ajustes > SGA.', 0, true);
            return new WP_Error('api_config_error', 'La URL de la API o el Token no están configurados.');
        }

        // --- INICIO DE LA CORRECCIÓN ---
        // Misma corrección de URL que en el método get()
        // $url = rtrim($this->api_url, '/') . '/api/' . ltrim($endpoint, '/'); // <-- INCORRECTO
        $url = rtrim($this->api_url, '/') . '/' . ltrim($endpoint, '/'); // <-- CORRECTO
        // --- FIN DE LA CORRECCIÓN ---

        $args = [
            'method'    => 'POST',
            'timeout'   => 30,
            'headers'   => [
                'Authorization' => 'Bearer ' . $this->api_token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
            'body'      => json_encode($data), // Enviar los datos como JSON
            'sslverify' => false // Desactivar para desarrollo local (ej. Ngrok)
        ];

        SGA_Utils::_log_activity('API Client POST', 'Iniciando llamada POST a: ' . $url, 0, true);
        $response = wp_remote_post($url, $args);

        return $this->handle_response($response, $url);
    }
    // ====================================================================
    // FIN DE NUEVO MÉTODO
    // ====================================================================


    /**
     * Manejador de respuestas centralizado para GET y POST.
     *
     * @param array|WP_Error $response La respuesta de wp_remote_get/post
     * @param string $url La URL que fue llamada (para logging)
     * @return array|WP_Error
     */
    private function handle_response($response, $url) {
        if (is_wp_error($response)) {
            // Error de conexión de WordPress (cURL, DNS, timeout)
            SGA_Utils::_log_activity('API Client Error: WP_Error', 'Error al llamar a ' . $url . ': ' . $response->get_error_message(), 0, true);
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // --- INICIO DE LA CORRECCIÓN ---
        // Manejar el caso en que la respuesta no sea JSON (ej. un error 500 de Laravel con HTML)
        if ($data === null && !empty($body)) {
             $error_message = 'La API devolvió una respuesta no-JSON (posiblemente un error 500 o 404 de Laravel). HTTP Status: ' . $response_code;
             SGA_Utils::_log_activity('API Client: Error Laravel', 'Llamada a ' . $url . ' falló. ' . $error_message, 0, true);
             return ['status' => $response_code, 'body' => ['message' => $error_message, 'raw_response' => $body]];
        }
        // --- FIN DE LA CORRECCIÓN ---

        if ($response_code >= 200 && $response_code < 300) {
            // Éxito (2xx)
            SGA_Utils::_log_activity('API Client: Éxito', 'Llamada exitosa a ' . $url . ' (HTTP ' . $response_code . ')', 0, true);
            // Devolvemos el body y el status
            // --- CORRECCIÓN: Devolver $data (el body decodificado) en lugar de $response
            return $data; // <-- Devolver directamente el array/JSON decodificado
        } else {
            // Error del servidor de Laravel (4xx, 5xx)
            $error_message = $data['message'] ?? (is_string($body) ? $body : 'Respuesta de error desconocida.');
            SGA_Utils::_log_activity('API Client: Error Laravel', 'Llamada a ' . $url . ' falló (HTTP ' . $response_code . '): ' . $error_message, 0, true);
            // Devolvemos el body y el status para que la función que llamó decida
            // --- CORRECCIÓN: Devolver $data (el body decodificado) en lugar de $response
            return $data; // <-- Devolver directamente el array/JSON decodificado
        }
    }

    /**
     * Función pública de ejemplo para obtener cursos.
     * Usada por el shortcode [sga_cursos_laravel]
     */
    public function get_all_courses() {
        // Llama a la ruta /api/v1/courses (asumiendo que $this->api_url es https://.../api/v1)
        return $this->get('courses'); 
    }

    /**
     * [NUEVA FUNCIÓN PÚBLICA]
     * Prueba la conexión con el endpoint /api/v1/test de Laravel.
     * Usado por el botón de debug en class-sga-admin.php.
     */
    public function test_connection() {
         // Llama a la ruta /api/v1/test (asumiendo que $this->api_url es https://.../api/v1)
        return $this->get('test');
    }
}