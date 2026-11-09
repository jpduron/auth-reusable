/**
 * Módulo de Gestión de Usuarios (CRUD) con DataTables
 */
const Usuarios = (() => {
    
    let modalInstance = null;
    let dataTable = null; // Instancia de DataTables

    // Inicialización
    const init = async () => {
        const isAuth = await Auth.requireAuth();
        if (!isAuth) return;

        const user = Auth.getUserData();
        document.getElementById('userName').textContent = user.nombre_completo;
        
        modalInstance = new bootstrap.Modal(document.getElementById('modalUsuario'));

        // Inicializar la tabla (vacía al principio)
        inicializarDataTable();
        
        // Cargar datos
        cargarUsuarios();

        // Listeners
        document.getElementById('btnLogout').addEventListener('click', () => Auth.logout());
        document.getElementById('formUsuario').addEventListener('submit', guardarUsuario);
    };

    // Configurar DataTables
    const inicializarDataTable = () => {
        dataTable = new DataTable('#tablaUsuarios', {
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' // Traducción al español
            },
            pageLength: 10, // Registros por página por defecto
            order: [[0, 'asc']], // Ordenar por la primera columna (Nombre) ascendente
            processing: true, // Muestra un indicador de "Procesando..." automáticamente
            
            // 👇 AQUÍ ESTÁ LA CLAVE: Mapear las propiedades del objeto a las columnas 👇
            columns: [
                { data: 'nombre_completo' }, // Columna 0: Nombre
                { data: 'correo' },          // Columna 1: Correo
                { data: 'rol_id' },          // Columna 2: Rol
                { data: 'estado_id' },       // Columna 3: Estado
                { data: 'es_su', orderable: false } // Columna 4: Acciones (no ordenable)
            ],

            columnDefs: [
                { 
                    targets: 0, // Columna Nombre
                    render: function(data, type, row) {
                        const suBadge = row.es_su ? '<span class="badge bg-warning text-dark ms-1">SU</span>' : '';
                        return `<strong>${row.nombre_completo}</strong>${suBadge}`;
                    }
                },
                { 
                    targets: 2, // Columna Rol
                    render: function(data, type, row) {
                        return row.rol_id === 1 ? 'Administrador' : 'Usuario';
                    }
                },
                { 
                    targets: 3, // Columna Estado
                    render: function(data, type, row) {
                        let badgeClass = 'bg-secondary';
                        let texto = 'Inactivo';
                        if (row.estado_id === 1) { badgeClass = 'bg-success'; texto = 'Activo'; }
                        else if (row.estado_id === 3) { badgeClass = 'bg-danger'; texto = 'Bloqueado'; }
                        return `<span class="badge ${badgeClass}">${texto}</span>`;
                    }
                },
                { 
                    targets: 4, // Columna Acciones
                    render: function(data, type, row) {
                        const currentUser = Auth.getUserData();
                        const puedeEditar = currentUser.es_su || (currentUser.id !== row.id && !row.es_su);
                        const puedeEliminar = currentUser.es_su && !row.es_su;

                        let acciones = '';
                        if (puedeEditar) {
                            acciones += `<button class="btn btn-sm btn-outline-primary me-1" onclick="Usuarios.prepararModalEditar(${row.id})" title="Editar"><i class="bi bi-pencil"></i></button>`;
                        }
                        if (puedeEliminar) {
                            // Escapamos las comillas simples en el nombre para evitar errores en el onclick
                            const nombreEscapado = row.nombre_completo.replace(/'/g, "\\'");
                            acciones += `<button class="btn btn-sm btn-outline-danger" onclick="Usuarios.eliminar(${row.id}, '${nombreEscapado}')" title="Eliminar"><i class="bi bi-trash"></i></button>`;
                        }
                        if (!puedeEditar && !puedeEliminar) {
                            acciones = '<span class="text-muted small">Sin permisos</span>';
                        }
                        return `<div class="text-center">${acciones}</div>`;
                    }
                }
            ]
        });
    };

    // Cargar usuarios desde la API
    const cargarUsuarios = async () => {
        // Limpiar tabla y mostrar indicador de carga nativo
        dataTable.clear().draw();

        try {
            const buscar = document.getElementById('filtroBuscar').value.trim();
            const estado = document.getElementById('filtroEstado').value;
            
            const params = new URLSearchParams();
            if (buscar) params.append('buscar', buscar);
            if (estado) params.append('estado', estado);
            params.append('por_pagina', 500); // Traemos los registros para paginación local

            const response = await API.get(`usuarios?${params.toString()}`);
            
            // Agregar los datos reales a DataTables
            if (response.data && response.data.usuarios) {
                dataTable.rows.add(response.data.usuarios).draw();
            } else {
                // Si no hay datos, DataTables mostrará su mensaje nativo "No hay datos disponibles"
                dataTable.draw();
            }
        } catch (error) {
            mostrarAlerta(error.message, 'danger');
            dataTable.draw(); // Limpia el estado de carga
        }
    };

    // Preparar modal para CREAR
    window.prepararModalCrear = () => {
        document.getElementById('modalTitle').textContent = 'Nuevo Usuario';
        document.getElementById('formUsuario').reset();
        document.getElementById('usuarioId').value = '';
        document.getElementById('divPassword').style.display = 'block';
        document.getElementById('password').required = true;
        
        const currentUser = Auth.getUserData();
        document.getElementById('es_su').parentElement.style.display = currentUser.es_su ? 'block' : 'none';
        
        modalInstance.show();
    };

    // Preparar modal para EDITAR
    window.prepararModalEditar = async (id) => {
        try {
            const response = await API.get(`usuarios/${id}`);
            const u = response.data;

            document.getElementById('modalTitle').textContent = 'Editar Usuario';
            document.getElementById('usuarioId').value = u.id;
            document.getElementById('nombre_completo').value = u.nombre_completo;
            document.getElementById('correo').value = u.correo;
            document.getElementById('rol_id').value = u.rol_id;
            document.getElementById('estado_id').value = u.estado_id;
            document.getElementById('es_su').checked = u.es_su;

            document.getElementById('divPassword').style.display = 'none';
            document.getElementById('password').required = false;

            const currentUser = Auth.getUserData();
            if (currentUser.id === u.id) {
                document.getElementById('rol_id').disabled = true;
                document.getElementById('es_su').disabled = true;
            } else {
                document.getElementById('rol_id').disabled = !currentUser.es_su;
                document.getElementById('es_su').disabled = !currentUser.es_su;
            }

            modalInstance.show();
        } catch (error) {
            mostrarAlerta(error.message, 'danger');
        }
    };

    // Guardar (Crear o Actualizar)
    const guardarUsuario = async (e) => {
        e.preventDefault();
        
        const btnGuardar = document.getElementById('btnGuardar');
        const spinner = document.getElementById('spinner');
        btnGuardar.disabled = true;
        spinner.classList.remove('d-none');

        const id = document.getElementById('usuarioId').value;
        const currentUser = Auth.getUserData();

        // 1. Campos base que TODOS los admins pueden modificar
        const data = {
            nombre_completo: document.getElementById('nombre_completo').value.trim(),
            correo: document.getElementById('correo').value.trim().toLowerCase(),
            estado_id: parseInt(document.getElementById('estado_id').value)
        };

        // 2. Campos restringidos: SOLO se envían si el usuario actual es Super Usuario
        if (currentUser.es_su) {
            data.rol_id = parseInt(document.getElementById('rol_id').value);
            data.es_su = document.getElementById('es_su').checked ? 1 : 0;
        }

        // 3. Contraseña (solo si se escribió algo, útil al crear o cambiar contraseña)
        const password = document.getElementById('password').value;
        if (password) {
            data.password = password;
        }

        try {
            if (id) {
                // Actualizar (PUT)
                await API.put(`usuarios/${id}`, data);
                mostrarAlerta('Usuario actualizado exitosamente', 'success');
            } else {
                // Crear (POST)
                await API.post('usuarios', data);
                mostrarAlerta('Usuario creado exitosamente', 'success');
            }
            
            modalInstance.hide();
            cargarUsuarios(); // Recargar la tabla
        } catch (error) {
            mostrarAlerta(error.message, 'danger');
        } finally {
            btnGuardar.disabled = false;
            spinner.classList.add('d-none');
        }
    };

    // Eliminar usuario
    window.eliminar = async (id, nombre) => {
        if (!confirm(`¿Estás seguro de que deseas desactivar al usuario "${nombre}"?\n\nEsta acción es irreversible desde el panel.`)) {
            return;
        }

        try {
            await API.delete(`usuarios/${id}`);
            mostrarAlerta('Usuario desactivado exitosamente', 'success');
            cargarUsuarios();
        } catch (error) {
            mostrarAlerta(error.message, 'danger');
        }
    };

    // Utilidad para mostrar alertas
    const mostrarAlerta = (mensaje, tipo) => {
        const alert = document.getElementById('alert');
        alert.className = `alert alert-${tipo}`;
        alert.textContent = mensaje;
        alert.classList.remove('d-none');
        setTimeout(() => alert.classList.add('d-none'), 5000);
    };

    // API Pública
    return {
        init,
        cargarUsuarios,
        prepararModalEditar,
        eliminar
    };
})();

// Iniciar el módulo cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', Usuarios.init);