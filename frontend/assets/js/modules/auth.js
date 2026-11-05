/**
 * =========================================
 * MÓDULO AUTH
 * =========================================
 * Maneja la lógica de autenticación: login, logout, verificación de sesión.
 */

import { apiRequest } from './api.js';

/**
 * Inicia sesión con email y contraseña.
 * 
 * @param {string} correo - Correo electrónico del usuario
 * @param {string} password - Contraseña en texto plano
 * @returns {Promise<object>} Datos del usuario autenticado
 */
export async function login(correo, password) {
    const response = await apiRequest('/login', {
        method: 'POST',
        body: { correo, password }
    });
    
    // Guardamos los datos del usuario en sessionStorage (no localStorage por seguridad)
    if (response.data) {
        sessionStorage.setItem('usuario', JSON.stringify(response.data));
        console.log('✅ Usuario guardado en sessionStorage:', response.data);
    }
    
    return response;
}

/**
 * Cierra la sesión del usuario actual.
 */
export async function logout() {
    try {
        await apiRequest('/logout', {
            method: 'POST',
            requireAuth: true
        });
    } catch (error) {
        // Ignoramos errores, limpiamos la sesión local de todos modos
        console.warn('Error al cerrar sesión en el servidor:', error);
    } finally {
        sessionStorage.removeItem('usuario');
        //20260806 se actualiza el link al index
        window.location.href = '/sitir/frontend/index.html';
    }
}

/**
 * Verifica si hay una sesión activa consultando al backend.
 * 
 * @returns {Promise<object|null>} Datos del usuario o null si no hay sesión
 */
export async function checkSession() {
    // 1. Primero intentar obtener del sessionStorage
    const usuarioLocal = getCurrentUser();
    if (usuarioLocal) {
        console.log('✅ Usuario encontrado en sessionStorage:', usuarioLocal);
        
        // 2. Verificar con el backend que la sesión sigue válida
        try {
            const response = await apiRequest('/session', {
                method: 'GET',
                requireAuth: true
            });
            
            if (response.data) {
                sessionStorage.setItem('usuario', JSON.stringify(response.data));
                return response.data;
            }
        } catch (error) {
            console.warn('Sesión inválida en backend, limpiando sessionStorage');
            sessionStorage.removeItem('usuario');
            return null;
        }
        
        return usuarioLocal;
    }
    
    // 3. Si no hay en sessionStorage, intentar con el backend
    try {
        const response = await apiRequest('/session', {
            method: 'GET',
            requireAuth: true
        });
        
        if (response.data) {
            sessionStorage.setItem('usuario', JSON.stringify(response.data));
            return response.data;
        }
        return null;
    } catch (error) {
        sessionStorage.removeItem('usuario');
        return null;
    }
}

/**
 * Obtiene el usuario actual desde sessionStorage.
 * 
 * @returns {object|null} Datos del usuario o null
 */
export function getCurrentUser() {
    const userJson = sessionStorage.getItem('usuario');
    if (!userJson) return null;
    
    try {
        return JSON.parse(userJson);
    } catch (error) {
        sessionStorage.removeItem('usuario');
        return null;
    }
}

/**
 * Verifica si el usuario tiene un rol específico.
 * 
 * @param {string|string[]} roles - Rol o array de roles permitidos
 * @returns {boolean}
 */
export function hasRole(roles) {
    const user = getCurrentUser();
    if (!user) {
        console.warn("⚠️ [hasRole] No hay usuario en sesión.");
        return false;
    }
    
    const rolesArray = Array.isArray(roles) ? roles : [roles];
    console.log(`🔍 [hasRole] Verificando si "${user.rol}" está en`, rolesArray);
    
    return rolesArray.includes(user.rol);
}