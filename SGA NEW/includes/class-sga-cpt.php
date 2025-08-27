<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_CPT
 *
 * Gestiona la creación y configuración de todos los Custom Post Types (CPTs)
 * y las meta boxes asociadas.
 */
class SGA_CPT {

    public function __construct() {
        add_action('init', array($this, 'crear_cpts'));
        add_action('add_meta_boxes', array($this, 'sga_add_price_metabox'));
        add_action('save_post_sga_concepto_pago', array($this, 'sga_save_price_metabox_data'));
    }

    /**
     * Registra todos los Custom Post Types del plugin.
     */
    public function crear_cpts() {
        register_post_type('estudiante', array(
            'labels' => array('name' => 'Estudiantes', 'singular_name' => 'Estudiante'),
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-id-alt',
            'supports' => array('title', 'custom-fields'),
            'rewrite' => false,
            'show_in_menu' => 'sga_main_menu',
            'capability_type' => 'estudiante',
            'capabilities' => array(
                'edit_post' => 'edit_estudiante',
                'read_post' => 'read_estudiante',
                'delete_post' => 'delete_estudiante',
                'edit_posts' => 'edit_estudiantes',
                'edit_others_posts' => 'edit_others_estudiantes',
                'publish_posts' => 'publish_estudiantes',
                'read_private_posts' => 'read_private_estudiantes',
                'create_posts' => 'edit_estudiantes',
                'delete_posts' => 'delete_estudiantes',
                'delete_published_posts' => 'delete_published_estudiantes',
                'delete_others_posts' => 'delete_others_estudiantes',
                'edit_published_posts' => 'edit_published_estudiantes',
            ),
            'map_meta_cap' => true,
        ));

        if (!post_type_exists('curso')) {
            register_post_type('curso', array(
                'labels' => array('name' => 'Cursos', 'singular_name' => 'Curso'),
                'public' => true,
                'show_ui' => true,
                'menu_icon' => 'dashicons-welcome-learn-more',
                'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
                'rewrite' => array('slug' => 'cursos'),
                'show_in_menu' => 'sga_main_menu',
                'taxonomies' => array('category'),
            ));
        }

        register_post_type('gestion_log', array(
            'labels' => array('name' => 'Registro de Actividad', 'singular_name' => 'Registro'),
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-list-view',
            'supports' => array('title', 'editor'),
            'rewrite' => false,
            'show_in_menu' => 'sga_main_menu',
            'capability_type' => 'post',
            'capabilities' => array('create_posts' => 'do_not_allow'),
        ));

        register_post_type('sga_pago', array(
            'labels' => array('name' => 'Registro de Pagos', 'singular_name' => 'Pago'),
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-money-alt',
            'supports' => array('title', 'custom-fields'),
            'rewrite' => false,
            'show_in_menu' => 'sga_main_menu',
            'capability_type' => 'post',
            'capabilities' => array('create_posts' => 'do_not_allow'),
        ));

        register_post_type('sga_concepto_pago', array(
            'labels' => array('name' => 'Conceptos de Pago', 'singular_name' => 'Concepto de Pago'),
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-cart',
            'supports' => array('title'),
            'rewrite' => false,
            'show_in_menu' => 'sga_main_menu',
            'description' => 'Crea conceptos de pago generales como diplomas, carnets, etc.'
        ));
    }

    /**
     * Añade la meta box para el precio en 'sga_concepto_pago'.
     */
    public function sga_add_price_metabox() {
        add_meta_box(
            'sga_concepto_pago_price',
            'Precio del Concepto',
            array($this, 'sga_concepto_pago_price_metabox_html'),
            'sga_concepto_pago',
            'normal',
            'high'
        );
    }

    /**
     * HTML para la meta box de precio.
     */
    public function sga_concepto_pago_price_metabox_html($post) {
        $price = get_post_meta($post->ID, '_sga_precio', true);
        wp_nonce_field('sga_save_price_nonce', 'sga_price_nonce');
        ?>
        <p>
            <label for="sga_precio_field">Precio (ej: RD$500.00 o 500):</label>
            <br>
            <input type="text" name="sga_precio_field" id="sga_precio_field" class="widefat" value="<?php echo esc_attr($price); ?>">
        </p>
        <?php
    }

    /**
     * Guarda los datos de la meta box de precio.
     */
    public function sga_save_price_metabox_data($post_id) {
        if (!isset($_POST['sga_price_nonce']) || !wp_verify_nonce($_POST['sga_price_nonce'], 'sga_save_price_nonce')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (isset($_POST['sga_precio_field'])) {
            update_post_meta($post_id, '_sga_precio', sanitize_text_field($_POST['sga_precio_field']));
        }
    }
}
