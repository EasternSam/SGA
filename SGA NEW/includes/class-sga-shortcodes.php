<?php

if (!defined('ABSPATH')) exit;

/**
 * Clase SGA_Shortcodes
 *
 * Gestiona la creación y renderizado de todos los shortcodes del plugin.
 * También se encarga de inyectar los estilos y scripts necesarios para los shortcodes.
 */
class SGA_Shortcodes {

    public function __construct() {
        add_shortcode('panel_gestion_academica', array($this, 'render_panel'));
        add_shortcode('sga_pagina_pagos', array($this, 'render_pagos_page'));
    }

    /**
     * Renderiza el shortcode del panel de gestión principal.
     * Carga las diferentes vistas (paneles) desde la clase.
     */
    public function render_panel() {
        ob_start();
        $this->render_panel_styles();
        ?>
        <div id="ga-modal-confirmacion" class="ga-modal" style="display:none;">
            <div class="ga-modal-content">
                <h4>Confirmar Aprobación</h4>
                <p>Se enviará un correo de notificación al estudiante informando que ha sido matriculado.</p>
                <div class="ga-modal-actions">
                    <button id="ga-modal-cancelar" class="button button-secondary">Cancelar</button>
                    <button id="ga-modal-confirmar" class="button button-primary">Confirmar y Enviar</button>
                </div>
            </div>
        </div>

        <div id="gestion-academica-app-container">
            <div class="gestion-academica-wrapper">
                <div id="panel-view-principal" class="panel-view active"><?php $this->render_view_principal(); ?></div>
                <div id="panel-view-matriculacion" class="panel-view" style="display: none;"><?php $this->render_view_matriculacion(); ?></div>
                <div id="panel-view-enviar_a_matriculacion" class="panel-view" style="display: none;"><?php $this->render_view_enviar_a_matriculacion(); ?></div>
                <div id="panel-view-lista_matriculados" class="panel-view" style="display: none;"><?php $this->render_view_lista_matriculados(); ?></div>
                <div id="panel-view-estudiantes" class="panel-view" style="display: none;"><?php $this->render_view_lista_estudiantes(); ?></div>
                <div id="panel-view-cursos" class="panel-view" style="display: none;"><?php $this->render_view_lista_cursos(); ?></div>
                <div id="panel-view-registro_pagos" class="panel-view" style="display: none;"><?php $this->render_view_registro_pagos(); ?></div>
                <div id="panel-view-log" class="panel-view" style="display: none;"><?php $this->render_view_log(); ?></div>
                <div id="panel-view-perfil_estudiante" class="panel-view" style="display: none;"><?php $this->render_view_perfil_estudiante(); ?></div>
                <div id="panel-view-comunicacion" class="panel-view" style="display: none;"><?php $this->render_view_comunicacion(); ?></div>
            </div>
        </div>
        <?php
        $this->render_panel_navigation_js();
        return ob_get_clean();
    }

    /**
     * Renderiza el shortcode de la página de pagos.
     */
    public function render_pagos_page() {
        // La instancia de la clase de Pagos se crea en SGA_Main,
        // así que podemos llamar a sus métodos directamente.
        $payments_handler = new SGA_Payments();
        return $payments_handler->render_pagos_page();
    }

    // --- MÉTODOS PARA RENDERIZAR VISTAS DEL PANEL ---

    public function render_view_principal() {
        $total_estudiantes_obj = wp_count_posts('estudiante');
        $total_cursos_obj = wp_count_posts('curso');
        $total_estudiantes = isset($total_estudiantes_obj->publish) ? $total_estudiantes_obj->publish : 0;
        $total_cursos = isset($total_cursos_obj->publish) ? $total_cursos_obj->publish : 0;
        $inscripciones_pendientes = 0;
        if (function_exists('get_field')) {
            $estudiantes = get_posts(array('post_type' => 'estudiante', 'posts_per_page' => -1, 'fields' => 'ids'));
            if($estudiantes){
                foreach ($estudiantes as $estudiante_id) {
                    $cursos = get_field('cursos_inscritos', $estudiante_id);
                    if ($cursos) {
                        foreach ($cursos as $curso) {
                            if (isset($curso['estado']) && $curso['estado'] == 'Inscrito') {
                                $inscripciones_pendientes++;
                            }
                        }
                    }
                }
            }
        }
        $current_user = wp_get_current_user();
        ?>
        <div class="panel-header-info">
            <div class="header-item user-info">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <span><?php echo esc_html($current_user->display_name); ?></span>
            </div>
            <div class="header-item">
                 <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                 <span id="dynamic-date"></span>
            </div>
            <div class="header-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <span id="dynamic-time"></span>
            </div>
        </div>

        <h1 class="panel-title">GESTIÓN ACADÉMICA</h1>

        <div class="panel-stats-grid">
            <div class="stat-card"><span><?php echo $total_estudiantes; ?></span>Estudiantes Totales</div>
            <div class="stat-card"><span><?php echo $inscripciones_pendientes; ?></span>Inscripciones Pendientes</div>
            <div class="stat-card"><span><?php echo $total_cursos; ?></span>Cursos Activos</div>
        </div>

        <div class="panel-grid main-menu">
            <a href="#" data-view="matriculacion" class="panel-card panel-nav-link"><h2>SISTEMA DE MATRICULACIÓN</h2></a>
            <a href="#" data-view="estudiantes" class="panel-card panel-nav-link"><h2>GESTIÓN DE ESTUDIANTES</h2></a>
            <a href="#" data-view="cursos" class="panel-card panel-nav-link"><h2>GESTIÓN DE CURSOS</h2></a>
            <a href="#" data-view="registro_pagos" class="panel-card panel-nav-link"><h2>REGISTRO DE PAGOS</h2></a>
            <a href="#" data-view="comunicacion" class="panel-card panel-nav-link"><h2>COMUNICACIÓN</h2></a>
            <a href="#" data-view="log" class="panel-card panel-nav-link"><h2>REGISTRO DE ACTIVIDAD</h2></a>
        </div>
        <?php
    }

    public function render_view_matriculacion() {
        ?>
        <a href="#" data-view="principal" class="back-link panel-nav-link">&larr; Volver al Panel Principal</a>
        <h1 class="panel-title">SISTEMA DE MATRICULACIÓN</h1>
        <div class="panel-grid">
            <a href="#" data-view="enviar_a_matriculacion" class="panel-card panel-nav-link"><h2>APROBAR INSCRIPCIONES</h2></a>
            <a href="#" data-view="lista_matriculados" class="panel-card panel-nav-link"><h2>LISTA DE MATRICULADOS</h2></a>
            <a href="<?php echo esc_url(site_url('/cursos/')); ?>" target="_blank" class="panel-card"><h2>NUEVA INSCRIPCIÓN</h2></a>
        </div>
        <?php
    }

    public function render_view_enviar_a_matriculacion() {
        $estudiantes_inscritos = get_posts(array('post_type' => 'estudiante', 'posts_per_page' => -1));
        $cursos_disponibles = get_posts(array('post_type' => 'curso', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        ?>
        <a href="#" data-view="matriculacion" class="back-link panel-nav-link">&larr; Volver a Matriculación</a>
        <h1 class="panel-title">APROBAR INSCRIPCIONES PENDIENTES</h1>

        <div class="filtros-tabla">
            <select name="bulk-action" id="bulk-action-select">
                <option value="-1">Acciones en lote</option>
                <option value="aprobar">Aprobar seleccionados</option>
            </select>
            <button id="apply-bulk-action" class="button">Aplicar</button>
            <input type="text" id="buscador-estudiantes-pendientes" placeholder="Buscar por nombre, cédula o curso...">
            <select id="filtro-curso-pendientes">
                <option value="">Todos los cursos</option>
                <?php foreach ($cursos_disponibles as $curso_filtro) : ?>
                    <option value="<?php echo esc_attr($curso_filtro->post_title); ?>"><?php echo esc_html($curso_filtro->post_title); ?></option>
                <?php endforeach; ?>
            </select>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sga-aprobar-inscripciones')); ?>" class="button" target="_blank" style="margin-left: auto;">Gestión Avanzada</a>
        </div>

        <div class="tabla-wrapper">
            <table class="wp-list-table widefat striped" id="tabla-pendientes">
                <thead><tr><th class="ga-check-column"><input type="checkbox" id="select-all-pendientes"></th><th>Nombre</th><th>Cédula</th><th>Email</th><th>Teléfono</th><th>Curso</th><th>Horario</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php
                    $hay_pendientes = false;
                    if ($estudiantes_inscritos && function_exists('get_field')) {
                        foreach ($estudiantes_inscritos as $estudiante) {
                            $cursos = get_field('cursos_inscritos', $estudiante->ID);
                            if ($cursos) {
                                foreach ($cursos as $index => $curso) {
                                    if (isset($curso['estado']) && $curso['estado'] == 'Inscrito') {
                                        $hay_pendientes = true;
                                        ?>
                                        <tr data-curso="<?php echo esc_attr($curso['nombre_curso']); ?>">
                                            <td class="ga-check-column"><input type="checkbox" class="bulk-checkbox" data-postid="<?php echo $estudiante->ID; ?>" data-rowindex="<?php echo $index; ?>" data-cedula="<?php echo esc_attr(get_field('cedula', $estudiante->ID)); ?>" data-nombre="<?php echo esc_attr($estudiante->post_title); ?>"></td>
                                            <td><?php echo esc_html($estudiante->post_title); ?></td>
                                            <td><?php echo esc_html(get_field('cedula', $estudiante->ID)); ?></td>
                                            <td><?php echo esc_html(get_field('email', $estudiante->ID)); ?></td>
                                            <td><?php echo esc_html(get_field('telefono', $estudiante->ID)); ?></td>
                                            <td><?php echo esc_html($curso['nombre_curso']); ?></td>
                                            <td><?php echo esc_html($curso['horario']); ?></td>
                                            <td><span class="estado-inscrito">Inscrito</span></td>
                                            <td><button class="button button-primary aprobar-btn" data-postid="<?php echo $estudiante->ID; ?>" data-rowindex="<?php echo $index; ?>" data-cedula="<?php echo esc_attr(get_field('cedula', $estudiante->ID)); ?>" data-nombre="<?php echo esc_attr($estudiante->post_title); ?>" data-nonce="<?php echo wp_create_nonce('aprobar_nonce'); ?>">Aprobar</button></td>
                                        </tr>
                                        <?php
                                    }
                                }
                            }
                        }
                    }
                    if (!$hay_pendientes) {
                        echo '<tr class="no-results"><td colspan="9">No hay estudiantes pendientes de aprobación.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_view_lista_matriculados() {
        $estudiantes_matriculados = get_posts(array('post_type' => 'estudiante', 'posts_per_page' => -1));
        $cursos_disponibles = get_posts(array('post_type' => 'curso', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        ?>
        <a href="#" data-view="matriculacion" class="back-link panel-nav-link">&larr; Volver a Matriculación</a>
        <h1 class="panel-title">Lista de Estudiantes Matriculados</h1>

        <div class="filtros-tabla">
            <input type="text" id="buscador-matriculados" placeholder="Buscar por matrícula, nombre, cédula...">
            <select id="filtro-curso-matriculados">
                <option value="">Todos los cursos</option>
                <?php foreach ($cursos_disponibles as $curso_filtro) : ?>
                    <option value="<?php echo esc_attr($curso_filtro->post_title); ?>"><?php echo esc_html($curso_filtro->post_title); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="export-format-select">
                <option value="excel">Exportar a Excel</option>
                <option value="moodle">Exportar para Moodle (CSV)</option>
            </select>
            <button id="exportar-btn" class="button">Exportar</button>
        </div>

        <div class="tabla-wrapper">
            <table class="wp-list-table widefat striped" id="tabla-matriculados">
                <thead><tr><th>Matrícula</th><th>Nombre</th><th>Cédula</th><th>Email</th><th>Teléfono</th><th>Curso</th></tr></thead>
                <tbody>
                    <?php
                    $hay_matriculados = false;
                    if ($estudiantes_matriculados && function_exists('get_field')) {
                        foreach ($estudiantes_matriculados as $estudiante) {
                            $cursos = get_field('cursos_inscritos', $estudiante->ID);
                            if ($cursos) {
                                foreach ($cursos as $curso) {
                                    if (isset($curso['estado']) && $curso['estado'] == 'Matriculado') {
                                        $hay_matriculados = true;
                                        echo '<tr data-curso="' . esc_attr($curso['nombre_curso']) . '">';
                                        echo '<td><strong>' . esc_html(isset($curso['matricula']) ? $curso['matricula'] : '') . '</strong></td>';
                                        echo '<td>' . esc_html($estudiante->post_title) . '</td>';
                                        echo '<td>' . esc_html(get_field('cedula', $estudiante->ID)) . '</td>';
                                        echo '<td>' . esc_html(get_field('email', $estudiante->ID)) . '</td>';
                                        echo '<td>' . esc_html(get_field('telefono', $estudiante->ID)) . '</td>';
                                        echo '<td>' . esc_html($curso['nombre_curso']) . '</td>';
                                        echo '</tr>';
                                    }
                                }
                            }
                        }
                    }
                    if (!$hay_matriculados) {
                        echo '<tr class="no-results"><td colspan="6">No hay estudiantes matriculados.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function render_view_lista_estudiantes() {
        $todos_estudiantes = get_posts(array('post_type' => 'estudiante', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        ?>
        <a href="#" data-view="principal" class="back-link panel-nav-link">&larr; Volver al Panel Principal</a>
        <h1 class="panel-title">Lista General de Estudiantes</h1>
        <div class="filtros-tabla">
            <select name="bulk-action-estudiantes" id="bulk-action-estudiantes-select">
                <option value="-1">Acciones en lote</option>
                <option value="enviar_correo">Enviar correo a seleccionados</option>
            </select>
            <button id="apply-bulk-action-estudiantes" class="button">Aplicar</button>
            <input type="text" id="buscador-general-estudiantes" placeholder="Buscar por nombre, cédula o email..." style="margin-left: auto;">
        </div>
        <div class="tabla-wrapper">
            <table class="wp-list-table widefat striped" id="tabla-general-estudiantes">
                <thead><tr>
                    <th class="ga-check-column"><input type="checkbox" id="select-all-estudiantes"></th>
                    <th>Nombre</th>
                    <th>Cédula</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Acción</th>
                </tr></thead>
                <tbody>
                    <?php if ($todos_estudiantes) : ?>
                        <?php foreach ($todos_estudiantes as $estudiante) : ?>
                            <tr>
                                <td class="ga-check-column"><input type="checkbox" class="student-checkbox" value="<?php echo $estudiante->ID; ?>"></td>
                                <td><?php echo esc_html($estudiante->post_title); ?></td>
                                <td><?php echo esc_html(get_field('cedula', $estudiante->ID)); ?></td>
                                <td><?php echo esc_html(get_field('email', $estudiante->ID)); ?></td>
                                <td><?php echo esc_html(get_field('telefono', $estudiante->ID)); ?></td>
                                <td><button class="button button-secondary ver-perfil-btn" data-estudiante-id="<?php echo $estudiante->ID; ?>">Ver Perfil</button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr class="no-results"><td colspan="6">No se encontraron estudiantes.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_view_lista_cursos() {
        $cursos_activos = get_posts(array('post_type' => 'curso', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        ?>
        <a href="#" data-view="principal" class="back-link panel-nav-link">&larr; Volver al Panel Principal</a>
        <h1 class="panel-title">Lista de Cursos Activos</h1>

        <div class="filtros-tabla">
            <input type="text" id="buscador-cursos" placeholder="Buscar por nombre de curso...">
        </div>

        <div class="panel-grid two-cols">
            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=curso')); ?>" target="_blank" class="panel-card"><h2>NUEVO CURSO</h2></a>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=curso')); ?>" target="_blank" class="panel-card"><h2>EDITAR CURSOS</h2></a>
        </div>

        <div class="tabla-wrapper" style="margin-top: 25px;">
            <table class="wp-list-table widefat striped" id="tabla-cursos">
                <thead>
                    <tr>
                        <th>Nombre del Curso</th><th>Horarios y Modalidades</th><th>Cupos</th><th>Escuela</th>
                        <th>Precio</th><th>Mensualidad</th><th>Duración</th><th>Fecha de Inicio</th><th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($cursos_activos && function_exists('get_field')) : ?>
                        <?php
                        foreach ($cursos_activos as $curso) :
                            $horarios_repeater = get_field('horarios_del_curso', $curso->ID);
                            $horarios_html = '';
                            $cupos_display = '';
                            $fecha_inicio_display = '';

                            if ($horarios_repeater) {
                                $fechas_inicio_array = [];
                                $cupos_array = [];
                                foreach ($horarios_repeater as $horario) {
                                    $horario_completo = $horario['dias_de_la_semana'] . ' ' . $horario['hora'];
                                    $modalidad = !empty($horario['modalidad']) ? $horario['modalidad'] : 'N/A';
                                    $modalidad_class = 'ga-pill-default';
                                    switch (strtolower($modalidad)) {
                                        case 'presencial': $modalidad_class = 'ga-pill-presencial'; break;
                                        case 'virtual': $modalidad_class = 'ga-pill-virtual'; break;
                                        case 'híbrido': case 'hibrido': $modalidad_class = 'ga-pill-hibrido'; break;
                                    }

                                    $horarios_html .= '<div style="margin-bottom: 5px; display: flex; flex-wrap: wrap; gap: 6px;">';
                                    $horarios_html .= '<span class="ga-pill ga-pill-time">' . esc_html($horario_completo) . '</span>';
                                    $horarios_html .= '<span class="ga-pill ' . $modalidad_class . '">' . esc_html($modalidad) . '</span>';
                                    $horarios_html .= '</div>';

                                    $total_cupos = !empty($horario['numero_de_cupos']) ? intval($horario['numero_de_cupos']) : 0;
                                    if ($total_cupos > 0) {
                                        $cupos_ocupados = SGA_Utils::_get_cupos_ocupados($curso->post_title, $horario_completo);
                                        $cupos_array[] = "{$cupos_ocupados} / {$total_cupos}";
                                    } else {
                                        $cupos_array[] = 'Ilimitados';
                                    }

                                    if (!empty($horario['fecha_de_inicio'])) {
                                        $fechas_inicio_array[] = $horario['fecha_de_inicio'];
                                    }
                                }
                                $cupos_display = implode('<br>', $cupos_array);
                                $fecha_inicio_display = implode('<br>', array_unique($fechas_inicio_array));
                            }

                            $escuelas = get_the_terms($curso->ID, 'category');
                            $escuela_display = 'N/A';
                            if ($escuelas && !is_wp_error($escuelas)) {
                                $escuela_names = array();
                                foreach ($escuelas as $escuela) $escuela_names[] = $escuela->name;
                                $escuela_display = implode(', ', $escuela_names);
                            }
                            ?>
                            <tr>
                                <td><?php echo esc_html($curso->post_title); ?></td>
                                <td><?php echo $horarios_html; // WPCS: XSS ok. ?></td>
                                <td><?php echo $cupos_display; // WPCS: XSS ok. ?></td>
                                <td><?php echo esc_html($escuela_display); ?></td>
                                <td><?php echo esc_html(get_field('precio_del_curso', $curso->ID)); ?></td>
                                <td><?php echo esc_html(get_field('mensualidad', $curso->ID)); ?></td>
                                <td><?php echo esc_html(get_field('duracion_del_curso', $curso->ID)); ?></td>
                                <td><?php echo $fecha_inicio_display; // WPCS: XSS ok. ?></td>
                                <td style="display: flex; gap: 5px;">
                                    <a href="#" class="button button-secondary ver-matriculados-btn" data-curso-nombre="<?php echo esc_attr($curso->post_title); ?>">Matriculados</a>
                                    <a href="<?php echo get_edit_post_link($curso->ID); ?>" class="button" target="_blank">Editar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr class="no-results"><td colspan="9">No se encontraron cursos.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_view_log() {
        global $wpdb;
        $log_entries = get_posts(array('post_type' => 'gestion_log', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC'));
        $user_ids = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_log_user_id'");
        $users = array();
        foreach ($user_ids as $user_id) {
            if ($user_id == 0) {
                $users[0] = 'Sistema';
            } else {
                $user_data = get_userdata($user_id);
                if ($user_data) $users[$user_id] = $user_data->display_name;
            }
        }
        ?>
        <a href="#" data-view="principal" class="back-link panel-nav-link">&larr; Volver al Panel Principal</a>
        <h1 class="panel-title">Registro de Actividad del Sistema</h1>
        <div class="filtros-tabla">
            <input type="text" id="buscador-log" placeholder="Buscar en el registro...">
            <select id="filtro-usuario-log">
                <option value="">Todos los usuarios</option>
                <?php foreach ($users as $id => $name) : ?>
                    <option value="<?php echo esc_attr($name); ?>"><?php echo esc_html($name); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" id="filtro-fecha-inicio">
            <input type="date" id="filtro-fecha-fin">
        </div>
        <div class="tabla-wrapper">
            <table class="wp-list-table widefat striped" id="tabla-log">
                <thead><tr><th>Acción</th><th>Detalles</th><th>Usuario</th><th>Fecha</th></tr></thead>
                <tbody>
                    <?php if ($log_entries) : ?>
                        <?php
                        foreach ($log_entries as $entry) :
                            $user_id = get_post_meta($entry->ID, '_log_user_id', true);
                            $user_name = ($user_id != 0 && isset($users[$user_id])) ? $users[$user_id] : 'Sistema';
                            $entry_date = get_the_date('Y-m-d', $entry);
                            ?>
                            <tr data-usuario="<?php echo esc_attr($user_name); ?>" data-fecha="<?php echo esc_attr($entry_date); ?>">
                                <td><?php echo esc_html($entry->post_title); ?></td>
                                <td><?php echo wp_kses_post($entry->post_content); ?></td>
                                <td><?php echo esc_html($user_name); ?></td>
                                <td><?php echo get_the_date('Y-m-d H:i:s', $entry); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr class="no-results"><td colspan="4">No hay registros de actividad.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_view_registro_pagos() {
        $pagos = get_posts(array('post_type' => 'sga_pago', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC'));
        ?>
        <a href="#" data-view="principal" class="back-link panel-nav-link">&larr; Volver al Panel Principal</a>
        <h1 class="panel-title">Registro de Pagos</h1>

        <div class="filtros-tabla">
            <input type="text" id="buscador-pagos" placeholder="Buscar por estudiante, concepto o ID de transacción...">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-left: auto;">
                <input type="hidden" name="action" value="sga_print_payment_history">
                <?php wp_nonce_field('sga_print_history_nonce', '_wpnonce_print_history'); ?>
                <button type="submit" class="button button-primary">Imprimir Historial Completo</button>
            </form>
        </div>

        <div class="tabla-wrapper">
            <table class="wp-list-table widefat striped" id="tabla-pagos">
                <thead><tr><th>Fecha</th><th>Estudiante</th><th>Concepto</th><th>Monto Pagado</th><th>ID Transacción</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php if ($pagos) : ?>
                        <?php
                        foreach ($pagos as $pago) :
                            $print_url = add_query_arg([
                                'action' => 'sga_print_invoice',
                                'payment_id' => $pago->ID,
                                '_wpnonce' => wp_create_nonce('sga_print_invoice_' . $pago->ID)
                            ], admin_url('admin-ajax.php'));
                            ?>
                            <tr>
                                <td><?php echo get_the_date('Y-m-d H:i:s', $pago); ?></td>
                                <td><?php echo esc_html(get_post_meta($pago->ID, '_student_name', true)); ?></td>
                                <td><?php echo esc_html(get_post_meta($pago->ID, '_payment_description', true)); ?></td>
                                <td><?php echo esc_html(get_post_meta($pago->ID, '_payment_amount', true)); ?> <?php echo esc_html(get_post_meta($pago->ID, '_payment_currency', true)); ?></td>
                                <td><?php echo esc_html(get_post_meta($pago->ID, '_transaction_id', true)); ?></td>
                                <td><a href="<?php echo esc_url($print_url); ?>" class="button button-secondary" target="_blank">Imprimir Factura</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr class="no-results"><td colspan="6">No hay pagos registrados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_view_perfil_estudiante() {
        ?>
        <div id="sga-student-profile-content">
            <div class="sga-profile-loading">Cargando perfil del estudiante...</div>
        </div>
        <?php
    }

    public function render_view_comunicacion() {
        $cursos = get_posts(array('post_type' => 'curso', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        $all_students = get_posts(array('post_type' => 'estudiante', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        ?>
        <a href="#" data-view="principal" class="back-link panel-nav-link">&larr; Volver al Panel Principal</a>
        <h1 class="panel-title">Comunicación y Correo Masivo</h1>
        <div id="sga-comunicacion-wrapper">
            <div class="sga-comunicacion-form">
                <div class="sga-form-group">
                    <label for="sga-email-recipient-group"><strong>Enviar a:</strong></label>
                    <select id="sga-email-recipient-group">
                        <option value="todos">Todos los Estudiantes</option>
                        <option value="matriculados">Estudiantes Matriculados</option>
                        <option value="pendientes">Estudiantes Pendientes de Aprobación</option>
                        <option value="por_curso">Estudiantes por Curso Específico</option>
                        <option value="especificos">Estudiantes Específicos (Selección Manual)</option>
                        <option value="manual" style="display: none;">Selección Manual</option>
                    </select>
                    <input type="hidden" id="sga-manual-recipient-ids" name="manual_ids">
                </div>
                <div class="sga-form-group" id="sga-curso-selector-group" style="display: none;">
                    <label for="sga-email-curso-select"><strong>Seleccionar Curso:</strong></label>
                    <select id="sga-email-curso-select">
                        <?php foreach ($cursos as $curso): ?>
                            <option value="<?php echo esc_attr($curso->post_title); ?>"><?php echo esc_html($curso->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sga-form-group" id="sga-estudiantes-especificos-group" style="display: none;">
                    <label for="sga-estudiantes-search"><strong>Seleccionar Estudiantes:</strong></label>
                    <input type="text" id="sga-estudiantes-search" placeholder="Buscar estudiante por nombre o cédula...">
                    <div id="sga-estudiantes-checkbox-list" style="height: 200px; overflow-y: auto; border: 1px solid #cbd5e1; padding: 10px; border-radius: 6px; margin-top: 10px;">
                        <?php foreach ($all_students as $student):
                            $cedula = get_field('cedula', $student->ID);
                        ?>
                            <div class="sga-student-item" data-search-term="<?php echo esc_attr(strtolower($student->post_title . ' ' . $cedula)); ?>">
                                <label>
                                    <input type="checkbox" class="sga-specific-student-checkbox" value="<?php echo esc_attr($student->ID); ?>">
                                    <?php echo esc_html($student->post_title); ?> (Cédula: <?php echo esc_html($cedula); ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="sga-form-group">
                    <label for="sga-email-subject"><strong>Asunto:</strong></label>
                    <input type="text" id="sga-email-subject" placeholder="Asunto del correo">
                </div>
                <div class="sga-form-group">
                    <label><strong>Mensaje:</strong></label>
                    <?php 
                    $default_editor_content = '<p>Hola,</p><p>Escribe aquí tu mensaje...</p>';
                    wp_editor($default_editor_content, 'sga-email-body', array('textarea_name' => 'sga-email-body', 'media_buttons' => false, 'textarea_rows' => 10)); 
                    ?>
                </div>
                <div class="sga-form-group">
                    <button id="sga-send-bulk-email-btn" class="button button-primary button-large">Enviar Correo</button>
                    <span class="spinner"></span>
                </div>
            </div>
            <div id="sga-email-status"></div>
        </div>
        <?php
    }

    // --- MÉTODOS PARA ESTILOS Y SCRIPTS ---

    public function render_panel_styles() {
        ?>
        <style>
            :root{--ga-bg-color:#fff;--ga-text-primary:#1e293b;--ga-text-secondary:#64748b;--ga-border-color:#e2e8f0;--ga-primary-color:#0052cc;--ga-primary-hover:#0041a3;--ga-green-color:#16a34a;--ga-red-color:#dc2626}#gestion-academica-app-container{padding:20px}#gestion-academica-app-container .gestion-academica-wrapper{background-color:var(--ga-bg-color);color:var(--ga-text-primary);padding:25px;border-radius:12px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;width:100%;border:1px solid var(--ga-border-color)}#gestion-academica-app-container .panel-title{display:flex;align-items:center;font-size:22px;margin:25px 0;padding-top:20px;text-transform:uppercase;letter-spacing:1px;color:var(--ga-text-primary);font-weight:600;border-top:1px solid var(--ga-border-color)}#gestion-academica-app-container .panel-header-info{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;margin-bottom:20px;padding-bottom:20px;color:var(--ga-text-secondary)}#gestion-academica-app-container .header-item{display:flex;align-items:center;gap:8px;font-size:14px}#gestion-academica-app-container .header-item.user-info{margin-right:auto}#gestion-academica-app-container .header-item svg{width:20px;height:20px;stroke-width:1.5}#gestion-academica-app-container .panel-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px}#gestion-academica-app-container .panel-grid.two-cols{grid-template-columns:repeat(auto-fit,minmax(300px,1fr))}#gestion-academica-app-container .panel-card{background-color:#f8fafc;border:1px solid var(--ga-border-color);border-radius:8px;padding:25px;text-align:center;text-decoration:none;color:var(--ga-text-primary);transition:all .2s ease-in-out;cursor:pointer}#gestion-academica-app-container .panel-card:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(30,41,59,.1);border-color:var(--ga-primary-color)}#gestion-academica-app-container .panel-card h2{margin:0;font-size:16px;font-weight:600}#gestion-academica-app-container .back-link{color:var(--ga-primary-color);text-decoration:none;display:inline-block;margin-bottom:25px;font-weight:500;cursor:pointer}#gestion-academica-app-container .tabla-wrapper{overflow-x:auto;width:100%;border:1px solid var(--ga-border-color);border-radius:8px}#gestion-academica-app-container .wp-list-table{background:#fff;margin:0;width:100%;border-collapse:collapse}#gestion-academica-app-container .wp-list-table thead th{background-color:var(--ga-primary-color);color:#fff;font-weight:600;border:none;text-align:left;padding:12px 15px}#gestion-academica-app-container .wp-list-table tbody tr:nth-child(2n){background-color:#f8fafc}#gestion-academica-app-container .wp-list-table td{padding:12px 15px;vertical-align:middle}#gestion-academica-app-container .wp-list-table th.ga-check-column,#gestion-academica-app-container .wp-list-table td.ga-check-column{width:2.5em;padding:12px 10px;text-align:center}#gestion-academica-app-container .filtros-tabla{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:25px;padding:20px;background-color:#f8fafc;border-radius:8px;border:1px solid var(--ga-border-color)}#gestion-academica-app-container .filtros-tabla>*{margin:0}#gestion-academica-app-container .filtros-tabla input[type=text],#gestion-academica-app-container .filtros-tabla input[type=date]{flex:1 1 200px;padding:10px 15px;border-radius:6px;border:1px solid #cbd5e1;background-color:#fff}#gestion-academica-app-container .filtros-tabla select,#gestion-academica-app-container .filtros-tabla button{padding:10px 15px;border-radius:6px;border:1px solid #cbd5e1;background-color:#fff;cursor:pointer;flex-shrink:0}#gestion-academica-app-container .filtros-tabla button{background-color:var(--ga-primary-color);color:#fff;border-color:var(--ga-primary-color)}#gestion-academica-app-container .filtros-tabla button#exportar-btn{background-color:var(--ga-green-color);border-color:var(--ga-green-color)}#gestion-academica-app-container .panel-view{display:none}#gestion-academica-app-container .panel-view.active{display:block}#gestion-academica-app-container .panel-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:40px}#gestion-academica-app-container .stat-card{background-color:#fff;padding:25px;border-radius:8px;text-align:center;border:1px solid var(--ga-border-color)}#gestion-academica-app-container .stat-card span{display:block;font-size:2.5em;font-weight:700;color:var(--ga-primary-color)}#gestion-academica-app-container .estado-inscrito{color:#f59e0b;background-color:#fffbeb;padding:3px 8px;border-radius:12px;font-weight:500;font-size:12px}#gestion-academica-app-container .estado-matriculado{color:#15803d;background-color:#f0fdf4;padding:3px 8px;border-radius:12px;font-weight:500;font-size:12px}.ga-modal{position:fixed;z-index:10000;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center}.ga-modal-content{background-color:#fff;padding:25px;border-radius:8px;width:90%;max-width:400px;box-shadow:0 5px 15px rgba(0,0,0,.3)}.ga-modal-content h4{margin-top:0;font-size:18px;color:var(--ga-text-primary)}.ga-modal-actions{margin-top:20px;display:flex;justify-content:flex-end;gap:10px} .ga-pill{display:inline-block;padding:4px 10px;font-size:12px;font-weight:500;line-height:1.5;text-align:center;white-space:nowrap;vertical-align:baseline;border-radius:16px;color:#fff} .ga-pill-time{background-color:var(--ga-text-secondary);color:#fff} .ga-pill-presencial{background-color:#0ea5e9;color:#fff} .ga-pill-virtual{background-color:#8b5cf6;color:#fff} .ga-pill-hibrido{background-color:#db2777;color:#fff} .ga-pill-default{background-color:#64748b;color:#fff}
            .sga-profile-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px}.sga-profile-card{background-color:#f8fafc;border:1px solid var(--ga-border-color);border-radius:8px;padding:20px}.sga-profile-card h3{margin-top:0;border-bottom:1px solid var(--ga-border-color);padding-bottom:10px;font-size:16px}.sga-profile-form-group{margin-bottom:15px}.sga-profile-form-group label{display:block;font-weight:500;margin-bottom:5px;font-size:14px}.sga-profile-form-group input,.sga-profile-form-group select{width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:6px}.sga-profile-actions{margin-top:20px;display:flex;gap:10px}.sga-comunicacion-form .sga-form-group{margin-bottom:20px}.sga-comunicacion-form input[type=text],.sga-comunicacion-form select{width:100%;padding:10px 15px;border-radius:6px;border:1px solid #cbd5e1}#sga-email-status{margin-top:20px;padding:15px;border-radius:6px;display:none}#sga-email-status.success{background-color:#dcfce7;color:#166534;border:1px solid #bbf7d0}#sga-email-status.error{background-color:#fee2e2;color:#991b1b;border:1px solid #fecaca}
        </style>
        <?php
    }

    public function render_panel_navigation_js() {
        ?>
        <script>
            jQuery(document).ready(function($){var ajaxurl="<?php echo admin_url('admin-ajax.php');?>",approvalData={};function setDynamicDateTime(){if(!$("#dynamic-date").length)return;const e=new Date,t={weekday:"long",year:"numeric",month:"long",day:"numeric"},o={hour:"2-digit",minute:"2-digit",hour12:!0};let a=new Intl.DateTimeFormat("es-ES",t).format(e);a=a.charAt(0).toUpperCase()+a.slice(1);const n=e.toLocaleTimeString("en-US",o);$("#dynamic-date").text(a),$("#dynamic-time").text(n)}setDynamicDateTime(),setInterval(setDynamicDateTime,6e4),$("#gestion-academica-app-container").on("click",".panel-nav-link",function(e){e.preventDefault();var t="#panel-view-"+$(this).data("view");$("#gestion-academica-app-container .panel-view").removeClass("active").hide(),$(t).addClass("active").show()}),$("#tabla-pendientes").on("click",".aprobar-btn",function(){var e=$(this);approvalData={type:"single",nonce:e.data("nonce"),post_id:e.data("postid"),row_index:e.data("rowindex"),cedula:e.data("cedula"),nombre:e.data("nombre"),element:e},$("#ga-modal-confirmacion").show()}),$("#apply-bulk-action").on("click",function(){if("aprobar"!==$("#bulk-action-select").val())return void alert("Por favor, selecciona una acción válida.");var e=[];$("#tabla-pendientes .bulk-checkbox:checked").each(function(){var t=$(this);e.push({post_id:t.data("postid"),row_index:t.data("rowindex")})}),e.length?(approvalData={type:"bulk",nonce:"<?php echo wp_create_nonce("aprobar_bulk_nonce");?>",seleccionados:e,element:$(this)},$("#ga-modal-confirmacion").show()):alert("Por favor, selecciona al menos un estudiante.")}),$("#ga-modal-confirmar").on("click",function(){var e=$(this);e.text("Procesando...").prop("disabled",!0),$("#ga-modal-cancelar").prop("disabled",!0),"single"===approvalData.type?$.post(ajaxurl,{action:"aprobar_para_matriculacion",_ajax_nonce:approvalData.nonce,post_id:approvalData.post_id,row_index:approvalData.row_index,cedula:approvalData.cedula,nombre:approvalData.nombre}).done(function(e){e.success?(actualizarUIAprobacion(e.data),approvalData.element.closest("tr").fadeOut(500,function(){$(this).remove(),checkEmptyTable("#tabla-pendientes",9,"No hay estudiantes pendientes de aprobación.")})):alert("Hubo un error: "+(e.data||"Error desconocido")),closeModal()}).fail(function(e,t,o){console.error("AJAX Error:",t,o),alert("Hubo un error de comunicación con el servidor."),closeModal()}):"bulk"===approvalData.type&&$.post(ajaxurl,{action:"aprobar_seleccionados",_ajax_nonce:approvalData.nonce,seleccionados:approvalData.seleccionados}).done(function(e){if(e.success){if(e.data.approved&&e.data.approved.length>0&&e.data.approved.forEach(function(e){actualizarUIAprobacion(e),$('#tabla-pendientes .bulk-checkbox[data-postid="'+e.post_id+'"][data-rowindex="'+e.row_index+'"]').closest("tr").fadeOut(500,function(){$(this).remove(),checkEmptyTable("#tabla-pendientes",9,"No hay estudiantes pendientes de aprobación.")})}),e.data.failed&&e.data.failed.length>0){var t="No se pudo aprobar a "+e.data.failed.length+" estudiante(s). Por favor, revisa la consola para más detalles o inténtalo de nuevo.";alert(t),console.log("Estudiantes no aprobados:",e.data.failed)}}else alert("Hubo un error al procesar la solicitud: "+(e.data.message||"Error desconocido"));closeModal(),$("#select-all-pendientes").prop("checked",!1),$("#tabla-pendientes .bulk-checkbox").prop("checked",!1)}).fail(function(e,t,o){console.error("AJAX Error:",t,o),alert("Hubo un error de comunicación con el servidor."),closeModal()})});function closeModal(){$("#ga-modal-confirmacion").hide(),$("#ga-modal-confirmar").text("Confirmar y Enviar").prop("disabled",!1),$("#ga-modal-cancelar").prop("disabled",!1),approvalData={}}function actualizarUIAprobacion(e){var t='<tr data-curso="'+e.nombre_curso+'"><td><strong>'+e.matricula+"</strong></td><td>"+e.nombre+"</td><td>"+e.cedula+"</td><td>"+e.email+"</td><td>"+e.telefono+"</td><td>"+e.nombre_curso+"</td></tr>";$("#tabla-matriculados .no-results").remove(),$("#tabla-matriculados tbody").append(t)}function checkEmptyTable(e,t,o){0===$(e+" tbody tr:not(.no-results-search)").length&&!$(e+" .no-results").length&&$(e+" tbody").append('<tr class="no-results"><td colspan="'+t+'">'+o+"</td></tr>")}$("#ga-modal-cancelar").on("click",closeModal),$("#select-all-pendientes").on("click",function(){$("#tabla-pendientes .bulk-checkbox").prop("checked",this.checked)});function actualizarFiltros(e,t,o){var a=$(t).val().toLowerCase(),n=o?$(o).val():"",r=0;$(e+" tbody tr").each(function(){var t=$(this);if(!t.hasClass("no-results")&&!t.hasClass("no-results-search")){var l=t.text().toLowerCase(),d=t.data("curso"),s=(""===a||l.includes(a))&&(!o||""===n||d===n);s?(t.show(),r++):t.hide()}}),$(e+" .no-results-search").remove(),0===r&&!$(e+" .no-results").is(":visible")&&$(e+" tbody").append('<tr class="no-results-search"><td colspan="100%">No se encontraron resultados para los filtros aplicados.</td></tr>')}function actualizarFiltrosLog(){var e=$("#buscador-log").val().toLowerCase(),t=$("#filtro-usuario-log").val(),o=$("#filtro-fecha-inicio").val(),a=$("#filtro-fecha-fin").val(),n=0;$("#tabla-log tbody tr").each(function(){var r=$(this);if(!r.hasClass("no-results")){var l=r.text().toLowerCase(),d=r.data("usuario"),s=r.data("fecha"),c=(""===e||l.includes(e))&&(""===t||d===t),i=!0;i=o&&a?s>=o&&s<=a:o?s>=o:a?s<=a:!0,c&&i?(r.show(),n++):r.hide()}}),$("#tabla-log .no-results-search").remove(),0===n&&!$("#tabla-log .no-results").is(":visible")&&$("#tabla-log tbody").append('<tr class="no-results-search"><td colspan="4">No se encontraron resultados para los filtros aplicados.</td></tr>')}$("#buscador-estudiantes-pendientes, #filtro-curso-pendientes").on("keyup change",function(){actualizarFiltros("#tabla-pendientes","#buscador-estudiantes-pendientes","#filtro-curso-pendientes")}),$("#buscador-matriculados, #filtro-curso-matriculados").on("keyup change",function(){actualizarFiltros("#tabla-matriculados","#buscador-matriculados","#filtro-curso-matriculados")}),$("#buscador-general-estudiantes").on("keyup",function(){actualizarFiltros("#tabla-general-estudiantes","#buscador-general-estudiantes",null)}),$("#buscador-cursos").on("keyup",function(){actualizarFiltros("#tabla-cursos","#buscador-cursos",null)}),$("#buscador-log, #filtro-usuario-log, #filtro-fecha-inicio, #filtro-fecha-fin").on("keyup change",function(){actualizarFiltrosLog()}),$("#exportar-btn").on("click",function(e){e.preventDefault();var t=$("#export-format-select").val(),o=$("#buscador-matriculados").val(),a=$("#filtro-curso-matriculados").val(),n="<?php echo wp_create_nonce("export_nonce");?>",r=new URL(ajaxurl);"excel"===t?r.searchParams.append("action","exportar_excel"):r.searchParams.append("action","exportar_moodle_csv"),r.searchParams.append("_wpnonce",n),r.searchParams.append("search_term",o),r.searchParams.append("course_filter",a),window.location.href=r.href}),$("#panel-view-cursos").on("click",".ver-matriculados-btn",function(e){e.preventDefault();var t=$(this).data("curso-nombre");$(".panel-view").removeClass("active").hide(),$("#panel-view-lista_matriculados").addClass("active").show(),$("#filtro-curso-matriculados").val(t).change(),$("#buscador-matriculados").val("")}),$("#buscador-pagos").on("keyup",function(){actualizarFiltros("#tabla-pagos","#buscador-pagos",null)});
            $("#tabla-general-estudiantes").on("click", ".ver-perfil-btn", function() {
                var studentId = $(this).data('estudiante-id');
                var profileContainer = $("#sga-student-profile-content");
                profileContainer.html('<div class="sga-profile-loading">Cargando perfil del estudiante...</div>');
                $("#panel-view-estudiantes").removeClass("active").hide();
                $("#panel-view-perfil_estudiante").addClass("active").show();
                $.post(ajaxurl, {
                    action: 'sga_get_student_profile_data',
                    _ajax_nonce: '<?php echo wp_create_nonce("sga_get_profile_nonce"); ?>',
                    student_id: studentId
                }).done(function(response) {
                    if (response.success) {
                        profileContainer.html(response.data.html);
                    } else {
                        profileContainer.html('<div class="sga-profile-error">Error al cargar el perfil: ' + response.data.message + '</div>');
                    }
                }).fail(function() {
                    profileContainer.html('<div class="sga-profile-error">Error de comunicación con el servidor.</div>');
                });
            });
            $("#gestion-academica-app-container").on("click", "#sga-profile-back-btn", function() {
                $("#panel-view-perfil_estudiante").removeClass("active").hide();
                $("#panel-view-estudiantes").addClass("active").show();
            });
            $("#gestion-academica-app-container").on("click", "#sga-profile-save-btn", function() {
                var btn = $(this);
                btn.text('Guardando...').prop('disabled', true);
                var studentId = btn.data('student-id');
                var profileData = {
                    nombre_completo: $('#sga-profile-nombre_completo').val(),
                    cedula: $('#sga-profile-cedula').val(),
                    email: $('#sga-profile-email').val(),
                    telefono: $('#sga-profile-telefono').val(),
                    direccion: $('#sga-profile-direccion').val(),
                    cursos: []
                };
                $('#sga-profile-cursos-tbody tr').each(function() {
                    var row = $(this);
                    profileData.cursos.push({
                        row_index: row.data('row-index'),
                        estado: row.find('.sga-profile-curso-estado').val()
                    });
                });
                $.post(ajaxurl, {
                    action: 'sga_update_student_profile_data',
                    _ajax_nonce: '<?php echo wp_create_nonce("sga_update_profile_nonce"); ?>',
                    student_id: studentId,
                    profile_data: profileData
                }).done(function(response) {
                    if (response.success) {
                        alert('Perfil actualizado correctamente.');
                    } else {
                        alert('Error al guardar: ' + response.data.message);
                    }
                    btn.text('Guardar Cambios').prop('disabled', false);
                }).fail(function() {
                    alert('Error de comunicación al guardar.');
                    btn.text('Guardar Cambios').prop('disabled', false);
                });
            });
            $('#sga-email-recipient-group').on('change', function() {
                var selectedGroup = $(this).val();
                if (selectedGroup === 'por_curso') {
                    $('#sga-curso-selector-group').slideDown();
                    $('#sga-estudiantes-especificos-group').slideUp();
                } else if (selectedGroup === 'especificos') {
                    $('#sga-curso-selector-group').slideUp();
                    $('#sga-estudiantes-especificos-group').slideDown();
                } else {
                    $('#sga-curso-selector-group').slideUp();
                    $('#sga-estudiantes-especificos-group').slideUp();
                }
            });
            $('#sga-estudiantes-search').on('keyup', function() {
                var searchTerm = $(this).val().toLowerCase();
                $('#sga-estudiantes-checkbox-list .sga-student-item').each(function() {
                    var itemText = $(this).data('search-term');
                    if (itemText.includes(searchTerm)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
            $('#sga-send-bulk-email-btn').on('click', function() {
                var btn = $(this);
                var statusDiv = $('#sga-email-status');
                var editorContent = typeof tinymce !== 'undefined' && tinymce.get('sga-email-body') ? tinymce.get('sga-email-body').getContent() : $('#sga-email-body').val();

                if (!$('#sga-email-subject').val() || !editorContent) {
                    alert('Por favor, completa el asunto y el mensaje.');
                    return;
                }

                btn.prop('disabled', true).siblings('.spinner').addClass('is-active');
                statusDiv.hide().removeClass('success error');
                
                var recipientGroup = $('#sga-email-recipient-group').val();
                var postData = {
                    action: 'sga_send_bulk_email',
                    _ajax_nonce: '<?php echo wp_create_nonce("sga_send_bulk_email_nonce"); ?>',
                    recipient_group: recipientGroup,
                    curso: $('#sga-email-curso-select').val(),
                    subject: $('#sga-email-subject').val(),
                    body: editorContent,
                };

                if (recipientGroup === 'especificos') {
                    var selectedStudents = [];
                    $('.sga-specific-student-checkbox:checked').each(function() {
                        selectedStudents.push($(this).val());
                    });

                    if (selectedStudents.length === 0) {
                        alert('Por favor, selecciona al menos un estudiante.');
                        btn.prop('disabled', false).siblings('.spinner').removeClass('is-active');
                        return;
                    }
                    postData.student_ids = JSON.stringify(selectedStudents);
                }

                $.post(ajaxurl, postData).done(function(response) {
                    if (response.success) {
                        statusDiv.addClass('success').html(response.data.message).slideDown();
                        $('#sga-email-subject').val('');
                        if (typeof tinymce !== 'undefined' && tinymce.get('sga-email-body')) {
                            tinymce.get('sga-email-body').setContent('');
                        } else {
                            $('#sga-email-body').val('');
                        }
                    } else {
                        statusDiv.addClass('error').html('Error: ' + response.data.message).slideDown();
                    }
                }).fail(function() {
                    statusDiv.addClass('error').html('Error de comunicación con el servidor.').slideDown();
                }).always(function() {
                    btn.prop('disabled', false).siblings('.spinner').removeClass('is-active');
                });
            });
        });
        </script>
        <?php
    }
}

