/**
 * Auth Module - Maneja toda la lógica de autenticación
 * Usa el helper API para comunicarse con el backend
 */
const Auth = (() => {
    
    /**
     * Guarda los datos del usuario en localStorage (sin información sensible)
     */
    const saveUserData = (user) => {
        localStorage.setItem('user_data', JSON.stringify(user));
    };

    /**
     * Obtiene los datos del usuario desde localStorage
     */
    const getUserData = () => {
        const data = localStorage.getItem('user_data');
        return data ? JSON.parse(data) : null;
    };

    /**
     * Limpia todos los datos de sesión
     */
    const clearSession = () => {
        API.clearToken();
        localStorage.removeItem('user_data');
    };

    /**
     * LOGIN - Autentica al usuario y guarda el token
     */
    const login = async (correo, password) => {
        try {
            const response = await API.post('login', { correo, password });
            
            if (response.data && response.data.token) {
                API.setToken(response.data.token);
                saveUserData(response.data.user);
                return { success: true, user: response.data.user };
            }
            
            throw new Error('Respuesta inválida del servidor');
        } catch (error) {
            return { success: false, message: error.message };
        }
    };

    /**
     * LOGOUT - Cierra la sesión y redirige al login
     */
    const logout = async () => {
        try {
            await API.post('logout');
        } catch (error) {
            // Ignoramos errores de logout, limpiamos sesión de todos modos
            console.warn('Error al cerrar sesión:', error.message);
        } finally {
            clearSession();
            window.location.href = '/proyecto/frontend/pages/login.html';
        }
    };

    /**
     * REGISTER - Registra un nuevo usuario
     */
    const register = async (nombreCompleto, correo, password) => {
        try {
            const response = await API.post('register', {
                nombre_completo: nombreCompleto,
                correo,
                password
            });
            return { success: true, message: response.mensaje };
        } catch (error) {
            return { success: false, message: error.message };
        }
    };

    /**
     * VERIFY EMAIL - Verifica el correo con el token del enlace
     */
    const verifyEmail = async (token) => {
        try {
            // Este endpoint usa GET con query parameter, necesitamos un helper especial
            const url = `${API.getBaseUrl()}/verify-email?token=${encodeURIComponent(token)}`;
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.mensaje || 'Error al verificar el correo');
            }
            
            return { success: true, message: data.mensaje };
        } catch (error) {
            return { success: false, message: error.message };
        }
    };

    /**
     * FORGOT PASSWORD - Solicita el enlace de recuperación
     */
    const forgotPassword = async (correo) => {
        try {
            const response = await API.post('forgot-password', { correo });
            return { success: true, message: response.mensaje };
        } catch (error) {
            return { success: false, message: error.message };
        }
    };

    /**
     * RESET PASSWORD - Restablece la contraseña con el token
     */
    const resetPassword = async (token, newPassword) => {
        try {
            const response = await API.post('reset-password', {
                token,
                password: newPassword
            });
            return { success: true, message: response.mensaje };
        } catch (error) {
            return { success: false, message: error.message };
        }
    };

    /**
     * ME - Obtiene los datos del usuario autenticado
     */
    const getMe = async () => {
        try {
            const response = await API.get('me');
            if (response.data) {
                saveUserData(response.data);
                return { success: true, user: response.data };
            }
            throw new Error('No se pudieron obtener los datos del usuario');
        } catch (error) {
            return { success: false, message: error.message };
        }
    };

    /**
     * Verifica si el usuario está autenticado (tiene token válido)
     */
    const isAuthenticated = () => {
        return !!API.getToken();
    };

    /**
     * Protege una página: si no hay sesión, redirige al login
     */
    const requireAuth = async () => {
        if (!isAuthenticated()) {
            window.location.href = '/proyecto/frontend/pages/login.html';
            return false;
        }

        // Verificar que el token siga siendo válido
        const result = await getMe();
        if (!result.success) {
            clearSession();
            window.location.href = '/proyecto/frontend/pages/login.html';
            return false;
        }

        return true;
    };

    // API pública del módulo
    return {
        login,
        logout,
        register,
        verifyEmail,
        forgotPassword,
        resetPassword,
        getMe,
        isAuthenticated,
        requireAuth,
        getUserData,
        clearSession
    };
})();