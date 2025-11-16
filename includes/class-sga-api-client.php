<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_API_Client
 *
 * Maneja la comunicación saliente con la API del sistema de gestión (Laravel).
 * (Este archivo maneja la SALIDA de datos hacia Laravel)
 */
class SGA_API_Client {

    private $api_url;
    private $api_key;

    public function __construct() {
        // Obtener la URL y la clave de las opciones de WordPress
        $this->api_url = get_option('sga_api_url', '');
        $this->api_key = get_option('sga_api_key', '');
    }

    /**
     * Envía datos a un endpoint de la API (POST).
     * @param string $endpoint El endpoint de la API (ej. 'enroll')
     * @param array $data Los datos a enviar en el cuerpo.
     * @return array|WP_Error La respuesta de la API decodificada o un WP_Error.
     */
    public function post_data($endpoint, $data) {
        if (empty($this->api_url) || empty($this->api_key)) {
            return new WP_Error('sga_api_not_configured', 'La URL o la clave de la API no están configuradas.');
        }

        $url = trailingslashit($this->api_url) . $endpoint;

        $args = array(
            'method'  => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json; charset=utf-8',
                'Accept'        => 'application/json',
            ),
            'body'    => json_encode($data),
            'timeout' => 15,
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Obtiene datos de un endpoint de la API (GET).
     * @param string $endpoint El endpoint de la API (ej. 'students')
     * @return array|WP_Error La respuesta de la API decodificada o un WP_Error.
     */
    public function get_data($endpoint) {
        if (empty($this->api_url) || empty($this->api_key)) {
            return new WP_Error('sga_api_not_configured', 'La URL o la clave de la API no están configuradas.');
        }

        $url = trailingslashit($this->api_url) . $endpoint;

        $args = array(
            'method'  => 'GET',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept'        => 'application/json',
            ),
            'timeout' => 15,
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    // --- FUNCIONES DE EJEMPLO AÑADIDAS ---

    /**
     * Prueba la conexión con el endpoint /test de Laravel.
     *
     * @return array Respuesta de la API
     */
    public function test_connection() {
        // 'test' es el endpoint que añadimos en routes/api.php
        // la URL base ('https://.../api/v1/') ya está en $this->api_url
        // NOTA: trailingslashit() en get_data() se encarga de la barra,
        // así que 'test' es correcto.
        return $this->get_data('test');
    }

    /**
     * Obtiene todos los cursos del endpoint /courses de Laravel.
     *
     * @return array Respuesta de la API (lista de cursos)
     */
    public function get_all_courses() {
        return $this->get_data('courses');
    }

    /**
     * Obtiene un estudiante específico.
     *
     * @param int $student_id ID del estudiante
     * @return array Respuesta de la API (datos del estudiante)
     */
    // public function get_student($student_id) {
    //     return $this->get_data('student/' . $student_id);
    // }
}