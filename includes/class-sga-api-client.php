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
        $options = get_option('sga_integration_options', []);
        
        // 'api_url' debe ser la URL base de Laravel, ej: https://mi-sga.com/
        $this->api_url = $options['api_url'] ?? get_option('sga_api_url', ''); 
        
        // 'api_token' es el Token de Sanctum de Laravel (ej: 1|Abc...)
        $this->api_token = $options['api_token'] ?? '';
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

        // Construir la URL completa
        // rtrim quita la barra final de la URL base
        // ltrim quita la barra inicial del endpoint
        // '/api/' es el prefijo estándar de Laravel para rutas de API
        $url = rtrim($this->api_url, '/') . '/api/' . ltrim($endpoint, '/');

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

        // Construir la URL completa
        $url = rtrim($this->api_url, '/') . '/api/' . ltrim($endpoint, '/');

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

        if ($response_code >= 200 && $response_code < 300) {
            // Éxito (2xx)
            SGA_Utils::_log_activity('API Client: Éxito', 'Llamada exitosa a ' . $url . ' (HTTP ' . $response_code . ')', 0, true);
            // Devolvemos el body y el status
            return ['status' => $response_code, 'body' => $data];
        } else {
            // Error del servidor de Laravel (4xx, 5xx)
            $error_message = $data['message'] ?? $body;
            SGA_Utils::_log_activity('API Client: Error Laravel', 'Llamada a ' . $url . ' falló (HTTP ' . $response_code . '): ' . $error_message, 0, true);
            // Devolvemos el body y el status para que la función que llamó decida
            return ['status' => $response_code, 'body' => $data];
        }
    }

    /**
     * Función pública de ejemplo para obtener cursos.
     * Usada por el shortcode [sga_cursos_laravel]
     */
    public function get_all_courses() {
        return $this->get('v1/courses'); // Llama a la ruta /api/v1/courses
    }
}