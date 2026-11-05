/**
 * =========================================
 * MÓDULO API
 * =========================================
 * Centraliza la configuración de fetch para las peticiones al backend.
 * Maneja: URL base, headers, credenciales y respuestas estandarizadas.
 */

// URL base del backend (RECORDAR AJUSTAR según entorno)
const API_BASE_URL = '/segr/backend/public';

/**
 * Realiza una petición HTTP al backend.
 * 
 * @param {string} endpoint - Ruta del endpoint (ej: '/login')
 * @param {object} options - Opciones de la petición
 * @param {string} options.method - Método HTTP (GET, POST, PUT, DELETE)
 * @param {object} options.body - Cuerpo de la petición (se convierte a JSON)
 * @param {boolean} options.requireAuth - Si requiere autenticación (envía cookies)
 * @returns {Promise<object>} Respuesta del backend
 */
export async function apiRequest(endpoint, options = {}) {
    const {
        method = 'GET',
        body = null,
        requireAuth = false
    } = options;

    const config = {
        method,
        headers: {
            'Accept': 'application/json',
        },
        credentials: 'include'
    };

    if (body && (method === 'POST' || method === 'PUT')) {
        config.headers['Content-Type'] = 'application/json';
        config.body = JSON.stringify(body);
    }

    try {
        const response = await fetch(`${API_BASE_URL}${endpoint}`, config);
        
        // 🚨 INTERCEPTOR DE MANTENIMIENTO 🚨
        // Si el backend devuelve 503, redirigimos inmediatamente al usuario
        if (response.status === 503) {
            window.location.href = '/segr/frontend/maintenance.html';
            throw new Error('Sistema en mantenimiento');
        }

        // Intentar parsear la respuesta como JSON
        let data;
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            data = await response.json();
        } else {
            // Si no es JSON y no es 503, es un error inesperado del servidor
            throw new Error('Respuesta no JSON del servidor');
        }

        if (!response.ok) {
            const error = new Error(data.mensaje || 'Error en la petición');
            error.status = response.status;
            error.data = data;
            throw error;
        }

        return data;

    } catch (error) {
        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            throw new Error('No se pudo conectar con el servidor. Verifique su conexión.');
        }
        throw error;
    }
}