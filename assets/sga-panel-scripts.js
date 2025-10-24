jQuery(document).ready(function($) {
    const sgaData = typeof sgaPanelData !== 'undefined' ? sgaPanelData : {};
    const ajaxurl = sgaData.ajaxurl || '';
    let approvalData = {};
    let callData = {};
    let viewsToRefresh = {};
    let inscriptionsChart;

    // --- Funciones de Utilidad ---

    function setDynamicDateTime() {
        if (!$("#dynamic-date").length) return;
        const now = new Date();
        const dateFormatter = new Intl.DateTimeFormat('es-ES', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        const timeFormatter = new Intl.DateTimeFormat('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        let formattedDate = dateFormatter.format(now);
        formattedDate = formattedDate.charAt(0).toUpperCase() + formattedDate.slice(1);
        $("#dynamic-date").text(formattedDate);
        $("#dynamic-time").text(timeFormatter.format(now));
    }
    setDynamicDateTime();
    setInterval(setDynamicDateTime, 1000);

    function checkEmptyTable(tableId, colspan, message) {
        if ($(tableId + ' tbody tr:not(.no-results-search)').length === 0 && !$(tableId + ' .no-results').length) {
            $(tableId + ' tbody').append('<tr class="no-results"><td colspan="' + colspan + '">' + message + '</td></tr>');
        }
    }

    function closeModal() {
        $("#ga-modal-confirmacion").fadeOut(200);
        $("#ga-modal-confirmar").text('Confirmar y Enviar').prop('disabled', false);
        $("#ga-modal-cancelar").prop('disabled', false);
        approvalData = {};
    }

    // --- Navegación del Panel ---

    $("#gestion-academica-app-container").on("click", ".panel-nav-link", function(e) {
        e.preventDefault();
        const view = $(this).data("view");
        const activePanel = $(".panel-view.active");
        const targetPanel = $("#panel-view-" + view);
        const loader = $("#sga-panel-loader");

        if (activePanel.is(targetPanel)) return;

        loader.fadeIn(150);

        function switchView() {
            activePanel.removeClass("active").hide();
            targetPanel.addClass("active").show();
            if (view === 'reportes' && (!inscriptionsChart || viewsToRefresh['reportes'])) {
                renderInscriptionsChart();
            }
            loader.fadeOut(150);
        }

        if (viewsToRefresh[view]) {
            $.post(ajaxurl, {
                action: 'sga_get_panel_view_html',
                view: view,
                _ajax_nonce: sgaData.nonceGetView
            }).done(function(response) {
                if (response.success) {
                    targetPanel.html(response.data.html);
                    delete viewsToRefresh[view];
                } else {
                    targetPanel.html('<div class="sga-profile-error">Error al recargar la vista.</div>');
                }
            }).fail(function() {
                targetPanel.html('<div class="sga-profile-error">Error de comunicación al recargar la vista.</div>');
            }).always(function() {
                switchView();
            });
        } else {
            setTimeout(switchView, 200);
        }
    });

    // --- Lógica de Aprobación y Modales ---

    $("#tabla-pendientes").on("click", ".aprobar-btn", function() {
        const btn = $(this);
        approvalData = {
            type: 'single',
            nonce: btn.data('nonce'),
            post_id: btn.data('postid'),
            row_index: btn.data('rowindex'),
            cedula: btn.data('cedula'),
            nombre: btn.data('nombre'),
            element: btn
        };
        $("#ga-modal-confirmacion").fadeIn(200);
    });

    $("#apply-bulk-action").on("click", function() {
        if ($("#bulk-action-select").val() !== 'aprobar') {
            alert('Por favor, selecciona una acción válida.');
            return;
        }
        const seleccionados = [];
        $("#tabla-pendientes .bulk-checkbox:checked").each(function() {
            const checkbox = $(this);
            seleccionados.push({
                post_id: checkbox.data('postid'),
                row_index: checkbox.data('rowindex')
            });
        });
        if (seleccionados.length > 0) {
            approvalData = {
                type: 'bulk',
                nonce: sgaData.nonceApproveBulk,
                seleccionados: seleccionados,
                element: $(this)
            };
            $("#ga-modal-confirmacion").fadeIn(200);
        } else {
            alert('Por favor, selecciona al menos un estudiante.');
        }
    });

    $("#ga-modal-confirmar").on("click", function() {
        const btn = $(this);
        btn.text('Procesando...').prop('disabled', true);
        $("#ga-modal-cancelar").prop('disabled', true);

        if (approvalData.type === 'single') {
            $.post(ajaxurl, {
                action: 'aprobar_para_matriculacion',
                _ajax_nonce: approvalData.nonce,
                post_id: approvalData.post_id,
                row_index: approvalData.row_index,
                cedula: approvalData.cedula,
                nombre: approvalData.nombre
            }).done(function(response) {
                if (response.success) {
                    viewsToRefresh['lista_matriculados'] = true;
                    viewsToRefresh['cursos'] = true;
                    approvalData.element.closest('tr').fadeOut(500, function() {
                        $(this).remove();
                        checkEmptyTable('#tabla-pendientes', 9, 'No hay estudiantes pendientes de aprobación.');
                    });
                } else {
                    alert('Hubo un error: ' + (response.data || 'Error desconocido'));
                }
                closeModal();
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown);
                alert('Hubo un error de comunicación con el servidor.');
                closeModal();
            });
        } else if (approvalData.type === 'bulk') {
            $.post(ajaxurl, {
                action: 'aprobar_seleccionados',
                _ajax_nonce: approvalData.nonce,
                seleccionados: approvalData.seleccionados
            }).done(function(response) {
                if (response.success) {
                    if (response.data.approved && response.data.approved.length > 0) {
                        viewsToRefresh['lista_matriculados'] = true;
                        viewsToRefresh['cursos'] = true;
                        response.data.approved.forEach(function(estudiante) {
                            $('#tabla-pendientes .bulk-checkbox[data-postid="' + estudiante.post_id + '"][data-rowindex="' + estudiante.row_index + '"]').closest('tr').fadeOut(500, function() {
                                $(this).remove();
                                checkEmptyTable('#tabla-pendientes', 9, 'No hay estudiantes pendientes de aprobación.');
                            });
                        });
                    }
                    if (response.data.failed && response.data.failed.length > 0) {
                        const errorMsg = 'No se pudo aprobar a ' + response.data.failed.length + ' estudiante(s). Por favor, revisa la consola para más detalles o inténtalo de nuevo.';
                        alert(errorMsg);
                        console.log("Estudiantes no aprobados:", response.data.failed);
                    }
                } else {
                    alert('Hubo un error al procesar la solicitud: ' + (response.data.message || 'Error desconocido'));
                }
                closeModal();
                $("#select-all-pendientes").prop("checked", false);
                $("#tabla-pendientes .bulk-checkbox").prop("checked", false);
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown);
                alert('Hubo un error de comunicación con el servidor.');
                closeModal();
            });
        }
    });

    $("#ga-modal-cancelar, .ga-modal").on("click", function(e) {
        if (e.target == this || $(this).is("#ga-modal-cancelar")) {
            closeModal();
            $('#ga-modal-repartir-agentes').fadeOut(200);
        }
    });

    $("#select-all-pendientes").on("click", function() {
        $("#tabla-pendientes .bulk-checkbox").prop('checked', this.checked);
    });
    
    // --- Lógica de Llamadas y Seguimiento ---

    $("#gestion-academica-app-container").on('change', '.sga-call-status-select', function(e) {
        const select = $(this);
        const post_id = select.data('postid');
        const row_index = select.data('rowindex');
        const status = select.val();
        const spinner = select.next('.spinner');

        select.prop('disabled', true);
        spinner.addClass('is-active');

        // Generar el nonce dinámico
        const nonce = sgaData.nonceCallStatus + post_id + '_' + row_index;

        $.post(ajaxurl, {
            action: 'sga_update_call_status',
            _ajax_nonce: nonce,
            post_id: post_id,
            row_index: row_index,
            status: status
        }).done(function(response) {
            if (!response.success) {
                alert('Error: ' + (response.data.message || 'Error desconocido'));
            } else {
                select.closest('tr').data('call-status', status);
                viewsToRefresh['registro_llamadas'] = true;
            }
        }).fail(function() {
            alert('Error de conexión.');
        }).always(function() {
            select.prop('disabled', false);
            spinner.removeClass('is-active');
        });
    });

    $("#gestion-academica-app-container").on("click", ".sga-marcar-llamado-btn", function() {
        const btn = $(this);
        callData = {
            post_id: btn.data('postid'),
            row_index: btn.data('rowindex'),
            nonce: btn.data('nonce'),
            element: btn
        };
        $('#sga-comentario-llamada-texto').val('');
        $('#ga-modal-comentario-llamada').fadeIn(200);
    });

    $('#ga-modal-comentario-guardar').on('click', function() {
        const btn = $(this);
        const comment = $('#sga-comentario-llamada-texto').val();
        const cell = callData.element.parent();
        const post_id = callData.post_id;
        const row_index = callData.row_index;
        const nonce = callData.nonce;

        btn.prop('disabled', true).text('Guardando...');
        callData.element.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'sga_marcar_llamado',
            _ajax_nonce: nonce,
            post_id: post_id,
            row_index: row_index,
            comment: comment
        }).done(function(response) {
            if (response.success) {
                cell.html(response.data.html);
                viewsToRefresh['registro_llamadas'] = true;
                $('#ga-modal-comentario-llamada').fadeOut(200);
            } else {
                alert('Error: ' + (response.data.message || 'Error desconocido'));
            }
        }).fail(function() {
            alert('Error de conexión.');
        }).always(function() {
            btn.prop('disabled', false).text('Marcar y Guardar');
        });
    });

    $('#ga-modal-comentario-cancelar').on('click', function() {
        $('#ga-modal-comentario-llamada').fadeOut(200);
    });
    
    // --- Lógica de Filtros (General) ---

    function filterTable(tableSelector, searchInputSelector, courseFilterSelector) {
        const searchTerm = $(searchInputSelector).val().toLowerCase();
        const courseFilter = courseFilterSelector ? $(courseFilterSelector).val() : '';
        let rowsFound = 0;
        const colspan = $(tableSelector + ' thead th').length;

        $(tableSelector + ' tbody tr').each(function() {
            const row = $(this);
            if (row.hasClass('no-results') || row.hasClass('no-results-search')) return;

            const rowText = row.text().toLowerCase();
            const rowCourse = row.data('curso');

            const matchesSearch = (searchTerm === '' || rowText.includes(searchTerm));
            const matchesCourse = (!courseFilterSelector || courseFilter === '' || rowCourse === courseFilter);

            if (matchesSearch && matchesCourse) {
                row.show();
                rowsFound++;
            } else {
                row.hide();
            }
        });

        $(tableSelector + ' .no-results-search').remove();
        if (rowsFound === 0 && !$(tableSelector + ' .no-results').is(':visible')) {
            $(tableSelector + ' tbody').append('<tr class="no-results-search"><td colspan="' + colspan + '">No se encontraron resultados para los filtros aplicados.</td></tr>');
        }
    }
    
    function filterPendientesTable() {
        const searchTerm = $('#buscador-estudiantes-pendientes').val().toLowerCase();
        const courseFilter = $('#filtro-curso-pendientes').val();
        const callStatusFilter = $('#filtro-estado-llamada').val() || ''; 
        const agentFilter = $('#filtro-agente-asignado').val() || '';
        let rowsFound = 0;
        const colspan = $('#tabla-pendientes thead th').length;

        $('#tabla-pendientes tbody tr').each(function() {
            const row = $(this);
            if (row.hasClass('no-results') || row.hasClass('no-results-search')) return;

            const rowText = row.text().toLowerCase();
            const rowCourse = row.data('curso');
            const rowCallStatus = row.data('call-status');
            const rowAgentId = String(row.data('agent-id'));

            const matchesSearch = (searchTerm === '' || rowText.includes(searchTerm));
            const matchesCourse = (courseFilter === '' || rowCourse === courseFilter);
            const matchesCallStatus = (callStatusFilter === '' || rowCallStatus === callStatusFilter);
            const matchesAgent = (agentFilter === '' || rowAgentId === agentFilter);

            if (matchesSearch && matchesCourse && matchesCallStatus && matchesAgent) {
                row.show();
                rowsFound++;
            } else {
                row.hide();
            }
        });

        $('#tabla-pendientes .no-results-search').remove();
        if (rowsFound === 0 && !$('#tabla-pendientes .no-results').is(':visible')) {
            $('#tabla-pendientes tbody').append('<tr class="no-results-search"><td colspan="' + colspan + '">No se encontraron resultados para los filtros aplicados.</td></tr>');
        }
    }
    
    function filterLogTable() {
        const searchTerm = $('#buscador-log').val().toLowerCase();
        const userFilter = $('#filtro-usuario-log').val();
        const dateFrom = $('#filtro-fecha-inicio').val();
        const dateTo = $('#filtro-fecha-fin').val();
        let rowsFound = 0;
        const colspan = $('#tabla-log thead th').length;

        $('#tabla-log tbody tr').each(function() {
            const row = $(this);
            if (row.hasClass('no-results')) return;
            
            const rowText = row.text().toLowerCase();
            const rowUser = row.data('usuario');
            const rowDate = row.data('fecha');

            const matchesSearch = (searchTerm === '' || rowText.includes(searchTerm));
            const matchesUser = (userFilter === '' || rowUser === userFilter);
            let matchesDate = true;
            
            if(dateFrom && dateTo) {
                matchesDate = rowDate >= dateFrom && rowDate <= dateTo;
            } else if (dateFrom) {
                matchesDate = rowDate >= dateFrom;
            } else if (dateTo) {
                matchesDate = rowDate <= dateTo;
            }

            if (matchesSearch && matchesUser && matchesDate) {
                row.show();
                rowsFound++;
            } else {
                row.hide();
            }
        });
        
        $('#tabla-log .no-results-search').remove();
        if (rowsFound === 0 && !$('#tabla-log .no-results').is(':visible')) {
             $('#tabla-log tbody').append('<tr class="no-results-search"><td colspan="' + colspan + '">No se encontraron resultados para los filtros aplicados.</td></tr>');
        }
    }

    function filterCourses() {
        const searchTerm = $('#buscador-cursos').val().toLowerCase();
        const escuelaFilter = $('#filtro-escuela-cursos').val();
        const visibilidadFilter = $('#filtro-visibilidad-cursos').val();
        let coursesFound = 0;

        $('.curso-card, #tabla-cursos-lista tbody tr').each(function() {
            const element = $(this);
            if (element.hasClass('no-results')) return;

            const elementText = element.data('search-term');
            const elementEscuelas = element.data('escuela').split(' ');
            const elementVisibilidad = element.data('visibilidad');

            const matchesSearch = (searchTerm === '' || elementText.includes(searchTerm));
            const matchesEscuela = (escuelaFilter === '' || elementEscuelas.includes(escuelaFilter));
            const matchesVisibilidad = (visibilidadFilter === '' || elementVisibilidad === visibilidadFilter);

            if (matchesSearch && matchesEscuela && matchesVisibilidad) {
                element.show();
                coursesFound++;
            } else {
                element.hide();
            }
        });
        
        // Manejar "No results" si se aplica en el futuro.
    }
    
    // Asignación de filtros
    $("#buscador-cursos, #filtro-escuela-cursos, #filtro-visibilidad-cursos").on("keyup change", filterCourses);
    $("#buscador-estudiantes-pendientes, #filtro-curso-pendientes, #filtro-estado-llamada, #filtro-agente-asignado").on("keyup change", filterPendientesTable);
    $("#buscador-matriculados, #filtro-curso-matriculados").on("keyup change", function() { filterTable('#tabla-matriculados', '#buscador-matriculados', '#filtro-curso-matriculados'); });
    $("#buscador-general-estudiantes").on("keyup", function() { filterTable('#tabla-general-estudiantes', '#buscador-general-estudiantes', null); });
    $("#buscador-log, #filtro-usuario-log, #filtro-fecha-inicio, #filtro-fecha-fin").on("keyup change", filterLogTable);
    $("#buscador-pagos").on("keyup", function() { filterTable('#tabla-pagos', '#buscador-pagos', null); });

    // --- Lógica de Exportación ---

    $("#exportar-btn").on("click", function(e) {
        e.preventDefault();
        const format = $('#export-format-select').val();
        const searchTerm = $('#buscador-matriculados').val();
        const courseFilter = $('#filtro-curso-matriculados').val();
        const url = new URL(ajaxurl);
        url.searchParams.append('action', format === 'excel' ? 'exportar_excel' : 'exportar_moodle_csv');
        url.searchParams.append('_wpnonce', sgaData.nonceExport);
        url.searchParams.append('search_term', searchTerm);
        url.searchParams.append('course_filter', courseFilter);
        window.location.href = url.href;
    });

    $("#gestion-academica-app-container").on("click", "#exportar-llamadas-btn", function(e) {
        e.preventDefault();
        const searchTerm = $('#buscador-registro-llamadas').val();
        const agentFilter = $('#filtro-agente-llamadas').val();
        const statusFilter = $('#filtro-estado-llamadas-registro').val();

        const url = new URL(ajaxurl);
        url.searchParams.append('action', 'sga_exportar_registro_llamadas');
        url.searchParams.append('_wpnonce', sgaData.nonceExportCalls);
        url.searchParams.append('search_term', searchTerm);
        url.searchParams.append('agent_filter', agentFilter);
        url.searchParams.append('status_filter', statusFilter);
        
        window.location.href = url.href;
    });

    function filterCallLog() {
        const searchTerm = $('#buscador-registro-llamadas').val().toLowerCase();
        const agentFilter = $('#filtro-agente-llamadas').val();
        const statusFilter = $('#filtro-estado-llamadas-registro').val();
        
        $('#sga-call-log-accordion .user-log-section').each(function() {
            const userSection = $(this);
            const agentName = userSection.data('agent');
            const matchesAgent = (agentFilter === '' || agentName === agentFilter);
            
            if (!matchesAgent) {
                userSection.hide();
                return; 
            }

            const tableRows = userSection.find('.user-log-content tbody tr');
            let matchingRowsInSection = 0;

            tableRows.each(function() {
                const row = $(this);
                const rowText = row.text().toLowerCase();
                const rowStatus = row.data('status');

                const matchesSearch = (searchTerm === '' || rowText.includes(searchTerm));
                const matchesStatus = (statusFilter === '' || rowStatus === statusFilter);

                if (matchesSearch && matchesStatus) {
                    row.show();
                    matchingRowsInSection++;
                } else {
                    row.hide();
                }
            });

            if (matchingRowsInSection > 0) {
                userSection.show();
            } else {
                userSection.hide();
            }
        });
    }
    $('#buscador-registro-llamadas, #filtro-agente-llamadas, #filtro-estado-llamadas-registro').on('keyup change', filterCallLog);
    
    // --- Vistas de Cursos ---
    
    $("#panel-view-cursos").on("click", ".ver-matriculados-btn", function(e) {
        e.preventDefault();
        const courseName = $(this).data('curso-nombre');
        $('.panel-view').removeClass('active').hide();
        $('#panel-view-lista_matriculados').addClass('active').show();
        $('#filtro-curso-matriculados').val(courseName).trigger('change');
        $('#buscador-matriculados').val('');
    });
    
    $('.view-switcher').on('click', '.view-btn', function(e) {
        e.preventDefault();
        const $this = $(this);
        if ($this.hasClass('active')) return;
        const targetView = $this.data('view');
        $('.view-btn').removeClass('active');
        $this.addClass('active');
        if (targetView === 'grid') {
            $('.cursos-list-view').fadeOut(200, function() { $('.cursos-grid').fadeIn(200); });
        } else {
            $('.cursos-grid').fadeOut(200, function() { $('.cursos-list-view').fadeIn(200); });
        }
    });

    // --- Perfil de Estudiante ---

    $("#tabla-general-estudiantes").on("click", ".ver-perfil-btn", function() {
        const studentId = $(this).data('estudiante-id');
        const profileContainer = $("#sga-student-profile-content");
        
        profileContainer.html('<div class="sga-profile-loading"><div class="spinner is-active" style="float:none; width:auto; height:auto; margin: 20px auto;"></div>Cargando perfil del estudiante...</div>');
        
        $(".panel-view.active").fadeOut(200, function() {
            $(this).removeClass("active");
            $("#panel-view-perfil_estudiante").fadeIn(200).addClass("active");
        });

        $.post(ajaxurl, {
            action: 'sga_get_student_profile_data',
            _ajax_nonce: sgaData.nonceGetProfile,
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
        $(".panel-view.active").fadeOut(200, function() {
            $(this).removeClass("active");
            $("#panel-view-estudiantes").fadeIn(200).addClass("active");
        });
    });

    $("#gestion-academica-app-container").on("click", "#sga-profile-save-btn", function() {
        const btn = $(this);
        btn.text('Guardando...').prop('disabled', true);
        const studentId = btn.data('student-id');
        const profileData = {
            nombre_completo: $('#sga-profile-nombre_completo').val(),
            cedula: $('#sga-profile-cedula').val(),
            email: $('#sga-profile-email').val(),
            telefono: $('#sga-profile-telefono').val(),
            direccion: $('#sga-profile-direccion').val(),
            cursos: []
        };
        $('#sga-profile-cursos-tbody tr').each(function() {
            const row = $(this);
            profileData.cursos.push({
                row_index: row.data('row-index'),
                estado: row.find('.sga-profile-curso-estado').val()
            });
        });

        $.post(ajaxurl, {
            action: 'sga_update_student_profile_data',
            _ajax_nonce: sgaData.nonceUpdateProfile,
            student_id: studentId,
            profile_data: profileData
        }).done(function(response) {
            if (response.success) {
                alert('Perfil actualizado correctamente.');
                viewsToRefresh['estudiantes'] = true;
                viewsToRefresh['lista_matriculados'] = true;
                viewsToRefresh['enviar_a_matriculacion'] = true;
            } else {
                alert('Error al guardar: ' + response.data.message);
            }
            btn.text('Guardar Cambios').prop('disabled', false);
        }).fail(function() {
            alert('Error de comunicación al guardar.');
            btn.text('Guardar Cambios').prop('disabled', false);
        });
    });

    // --- Comunicación y Correo Masivo ---
    
    $('#sga-email-recipient-group').on('change', function() {
        const selectedGroup = $(this).val();
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
        const searchTerm = $(this).val().toLowerCase();
        $('#sga-estudiantes-checkbox-list .sga-student-item').each(function() {
            const itemText = $(this).data('search-term');
            if (itemText.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    $('#sga-send-bulk-email-btn').on('click', function() {
        const btn = $(this);
        const statusDiv = $('#sga-email-status');
        const editorContent = typeof tinymce !== 'undefined' && tinymce.get('sga-email-body') ? tinymce.get('sga-email-body').getContent() : $('#sga-email-body').val();

        if (!$('#sga-email-subject').val() || !editorContent) {
            alert('Por favor, completa el asunto y el mensaje.');
            return;
        }

        btn.prop('disabled', true).siblings('.spinner').addClass('is-active');
        statusDiv.hide().removeClass('success error');
        
        const recipientGroup = $('#sga-email-recipient-group').val();
        let postData = {
            action: 'sga_send_bulk_email',
            _ajax_nonce: sgaData.nonceBulkEmail,
            recipient_group: recipientGroup,
            curso: $('#sga-email-curso-select').val(),
            subject: $('#sga-email-subject').val(),
            body: editorContent,
        };

        if (recipientGroup === 'especificos') {
            const selectedStudents = [];
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

    // --- Reportes y Gráficos ---

    $('#report_type').on('change', function() {
        const reportType = $(this).val();
        const cursoFilter = $('#report-curso-filter-container');
        const agenteFilter = $('#report-agente-filter-container');
        cursoFilter.hide();
        agenteFilter.hide();
        if (reportType === 'matriculados' || reportType === 'pendientes' || reportType === 'historial_llamadas') {
            cursoFilter.slideDown();
        }
        if (reportType === 'historial_llamadas') {
            agenteFilter.slideDown();
        }
    }).trigger('change');

    function renderInscriptionsChart() {
        if (inscriptionsChart) { inscriptionsChart.destroy(); }
        let ctx = document.getElementById('inscriptionsChart') ? document.getElementById('inscriptionsChart').getContext('2d') : null;
        if (!ctx) return;
        
        const chartContainer = $('.chart-container');
        chartContainer.html('<canvas id="inscriptionsChart"></canvas><div class="chart-loading" style="position:absolute;top:0;left:0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.7);"><span class="spinner is-active"></span></div>');
        ctx = document.getElementById('inscriptionsChart').getContext('2d');

        $.post(ajaxurl, {
            action: 'sga_get_report_chart_data',
            _ajax_nonce: sgaData.nonceChart
        }).done(function(response) {
            chartContainer.find('.chart-loading').remove();
            if(response.success) {
                inscriptionsChart = new Chart(ctx, {
                    type: 'line',
                    data: { labels: response.data.labels, datasets: [{ label: 'Inscripciones por Mes', data: response.data.data, backgroundColor: 'rgba(79, 70, 229, 0.2)', borderColor: 'rgba(79, 70, 229, 1)', borderWidth: 2, tension: 0.4, fill: true }] },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
                });
            } else {
                chartContainer.html('<p>No se pudieron cargar los datos.</p>');
            }
        }).fail(function() {
            chartContainer.find('.chart-loading').remove();
            chartContainer.html('<p>Error al contactar al servidor.</p>');
        });
    }

    // --- Reparto de Agentes ---
    $('#panel-view-enviar_a_matriculacion').on('click', '#sga-distribute-btn', function() {
        let agentListHtml = '';
        if (sgaData.sgaAgents && sgaData.sgaAgents.length > 0) {
            sgaData.sgaAgents.forEach(function(agent) {
                agentListHtml += '<label><input type="checkbox" class="sga-distribute-agent" value="' + agent.id + '"> ' + agent.name + '</label><br>';
            });
        } else {
            agentListHtml = '<p>No hay agentes disponibles para asignar.</p>';
        }
        $('#sga-distribute-agent-list').html(agentListHtml);
        $('#ga-modal-repartir-agentes').fadeIn(200);
    });

    $('#ga-modal-repartir-confirmar').on('click', function() {
        const btn = $(this);
        const selectedAgents = [];
        $('.sga-distribute-agent:checked').each(function() {
            selectedAgents.push($(this).val());
        });

        if (selectedAgents.length === 0) {
            alert('Por favor, selecciona al menos un agente.');
            return;
        }

        btn.prop('disabled', true).text('Repartiendo...');
        $('#sga-panel-loader').fadeIn(150);

        $.post(ajaxurl, {
            action: 'sga_distribute_inscriptions',
            security: sgaData.nonceDistribute,
            agent_ids: selectedAgents
        }).done(function(response) {
            if (response.success) {
                alert(response.data.message);
                viewsToRefresh['enviar_a_matriculacion'] = true;
                // Forzar recarga de la vista actual
                $(".panel-nav-link[data-view='enviar_a_matriculacion']").first().trigger('click');
            } else {
                alert('Error: ' + response.data.message);
            }
        }).fail(function() {
            alert('Error de comunicación con el servidor.');
        }).always(function() {
            btn.prop('disabled', false).text('Confirmar Reparto');
            $('#ga-modal-repartir-agentes').fadeOut(200);
            $('#sga-panel-loader').fadeOut(150);
        });
    });

    $('#ga-modal-repartir-cancelar').on('click', function() {
        $('#ga-modal-repartir-agentes').fadeOut(200);
    });
    
    // --- Lógica del Acordeón de Llamadas ---
    $('#gestion-academica-app-container').on('click', '#sga-call-log-accordion .user-log-title button', function() {
        const button = $(this);
        const content = button.closest('.user-log-section').find('.user-log-content');
        const isExpanded = button.attr('aria-expanded') === 'true';
        button.attr('aria-expanded', !isExpanded);
        content.slideToggle(200);
    });

});
