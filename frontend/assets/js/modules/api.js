/**
 * API Helper - Módulo genérico para hacer peticiones HTTP al backend
 * Maneja automáticamente los tokens Bearer y los errores de autenticación
 */
const API = (() => {
    // URL base de tu backend (ajusta según tu configuración)
    const BASE_URL = 'http://localhost/proyecto/backend/public';

    /**
     * Obtiene el token almacenado en localStorage
     */
    const getToken = () => localStorage.getItem('api_token');

    /**
     * Guarda el token en localStorage
     */
    const setToken = (token) => localStorage.setItem('api_token', token);

    /**
     * Elimina el token de localStorage
     */
    const clearToken = () => localStorage.removeItem('api_token');

    /**
     * Construye los headers comunes para cada petición
     */
    const buildHeaders = (includeAuth = false) => {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };

        if (includeAuth) {
            const token = getToken();
            if (token) {
                headers['Authorization'] = `Bearer ${token}`;
            }
        }

        return headers;
    };

    /**
     * Maneja la respuesta HTTP de forma centralizada
     */
    const handleResponse = async (response) => {
        const data = await response.json();

        // Si el token expiró o es inválido (401), redirigir al login
        if (response.status === 401) {
            clearToken();
            // Solo redirigir si no estamos ya en login
            if (!window.location.pathname.includes('login.html')) {
                window.location.href = '/proyecto/frontend/pages/login.html';
            }
            throw new Error(data.mensaje || 'Sesión expirada. Por favor, inicia sesión nuevamente.');
        }

        // Si es un error de permisos (403)
        if (response.status === 403) {
            throw new Error(data.mensaje || 'No tienes permisos para realizar esta acción.');
        }

        // Si es un error de validación o negocio (400)
        if (!response.ok) {
            throw new Error(data.mensaje || 'Ocurrió un error en el servidor.');
        }

        return data;
    };

    /**
     * Método genérico para hacer peticiones
     */
    const request = async (endpoint, method = 'GET', body = null, includeAuth = true) => {
        const url = `${BASE_URL}/${endpoint}`;
        const options = {
            method,
            headers: buildHeaders(includeAuth)
        };

        if (body && (method === 'POST' || method === 'PUT')) {
            options.body = JSON.stringify(body);
        }

        try {
            const response = await fetch(url, options);
            return await handleResponse(response);
        } catch (error) {
            // Errores de red o servidor caído
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                throw new Error('No se pudo conectar con el servidor. Verifica tu conexión.');
            }
            throw error;
        }
    };

    // API pública del módulo
    return {
        get: (endpoint) => request(endpoint, 'GET'),
        post: (endpoint, body) => request(endpoint, 'POST', body),
        put: (endpoint, body) => request(endpoint, 'PUT', body),
        delete: (endpoint) => request(endpoint, 'DELETE'),
        
        // Métodos de gestión de tokens
        getToken,
        setToken,
        clearToken,
        
        // Utilidad
        getBaseUrl: () => BASE_URL
    };
})();