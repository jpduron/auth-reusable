/**
 * =========================================
 * APP.JS - PUNTO DE ENTRADA
 * =========================================
 * Inicializa la aplicación y maneja el formulario de login.
 */

import { login, checkSession, getCurrentUser } from './modules/auth.js';

// =========================
// ELEMENTOS DEL DOM
// =========================
const loginForm = document.getElementById('loginForm');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const btnLogin = document.getElementById('btnLogin');
const btnText = document.getElementById('btnText');
const btnSpinner = document.getElementById('btnSpinner');
const togglePassword = document.getElementById('togglePassword');
const alertContainer = document.getElementById('alertContainer');
const alertMessage = document.getElementById('alertMessage');
const emailError = document.getElementById('emailError');
const passwordError = document.getElementById('passwordError');

// =========================
// INICIALIZACIÓN
// =========================
document.addEventListener('DOMContentLoaded', async () => {
    // 1. Verificar si hay datos locales primero (evita la petición 401 innecesaria)
    const usuarioLocal = getCurrentUser();
    
    if (usuarioLocal) {
        // Si hay datos locales, verificamos con el backend que la sesión siga válida
        const usuarioValido = await checkSession();
        if (usuarioValido) {
            window.location.href = '/segr/frontend/dashboard.html';
            return;
        }
    }
    
    // Si no hay sesión, configuramos el formulario de login normalmente
    setupEventListeners();
});

// =========================
// EVENT LISTENERS
// =========================
function setupEventListeners() {
    // Envío del formulario
    loginForm.addEventListener('submit', handleLogin);
    
    // Mostrar/ocultar contraseña
    togglePassword.addEventListener('click', togglePasswordVisibility);
    
    // Limpiar errores al escribir
    emailInput.addEventListener('input', () => clearFieldError(emailInput, emailError));
    passwordInput.addEventListener('input', () => clearFieldError(passwordInput, passwordError));
}

// =========================
// MANEJO DEL LOGIN
// =========================
async function handleLogin(event) {
    event.preventDefault();
    
    // 1. Limpiar errores previos
    hideAlert();
    clearFieldError(emailInput, emailError);
    clearFieldError(passwordInput, passwordError);
    
    // 2. Validación del lado del cliente
    const email = emailInput.value.trim();
    const password = passwordInput.value;
    
    if (!validateForm(email, password)) {
        return;
    }
    
    // 3. Estado de carga
    setLoadingState(true);
    
    try {
        // 4. Intentar login
        const response = await login(email, password);
        
        // 5. Éxito: redirigir al dashboard
        showAlert('success', 'Sesión iniciada correctamente. Redirigiendo...');
        
        setTimeout(() => {
            window.location.href = '/segr/frontend/dashboard.html';
        }, 1000);
        
    } catch (error) {
        // 6. Error: mostrar mensaje
        console.error('Error de login:', error);
        
        let mensaje = error.message || 'Error al iniciar sesión';
        
        // Mensajes específicos según el código HTTP
        if (error.status === 401) {
            mensaje = 'Credenciales inválidas. Verifique su correo y contraseña.';
        } else if (error.status === 403) {
            mensaje = 'Su cuenta está inactiva. Contacte al administrador.';
        } else if (error.status === 500) {
            mensaje = 'Error interno del servidor. Intente más tarde.';
        }
        
        showAlert('danger', mensaje);
    } finally {
        // 7. Quitar estado de carga
        setLoadingState(false);
    }
}

// =========================
// VALIDACIONES
// =========================
function validateForm(email, password) {
    let isValid = true;
    
    // Validar email
    if (!email) {
        showFieldError(emailInput, emailError, 'El correo electrónico es obligatorio.');
        isValid = false;
    } else if (!isValidEmail(email)) {
        showFieldError(emailInput, emailError, 'Ingrese un correo electrónico válido.');
        isValid = false;
    }
    
    // Validar contraseña
    if (!password) {
        showFieldError(passwordInput, passwordError, 'La contraseña es obligatoria.');
        isValid = false;
    } else if (password.length < 6) {
        showFieldError(passwordInput, passwordError, 'La contraseña debe tener al menos 6 caracteres.');
        isValid = false;
    }
    
    return isValid;
}

function isValidEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

// =========================
// UTILIDADES DE UI
// =========================
function showFieldError(input, errorElement, message) {
    input.classList.add('is-invalid');
    errorElement.textContent = message;
    errorElement.style.display = 'block';
}

function clearFieldError(input, errorElement) {
    input.classList.remove('is-invalid');
    errorElement.textContent = '';
    errorElement.style.display = 'none';
}

function showAlert(type, message) {
    alertContainer.querySelector('.alert').className = `alert alert-${type} d-flex align-items-center py-2 mb-0`;
    alertMessage.textContent = message;
    alertContainer.style.display = 'block';
}

function hideAlert() {
    alertContainer.style.display = 'none';
}

function setLoadingState(loading) {
    btnLogin.disabled = loading;
    emailInput.disabled = loading;
    passwordInput.disabled = loading;
    
    if (loading) {
        btnText.classList.add('d-none');
        btnSpinner.classList.remove('d-none');
    } else {
        btnText.classList.remove('d-none');
        btnSpinner.classList.add('d-none');
    }
}

function togglePasswordVisibility() {
    const type = passwordInput.type === 'password' ? 'text' : 'password';
    passwordInput.type = type;
    
    const icon = togglePassword.querySelector('i');
    icon.classList.toggle('bi-eye-fill');
    icon.classList.toggle('bi-eye-slash-fill');
    
    togglePassword.setAttribute(
        'aria-label', 
        type === 'password' ? 'Mostrar contraseña' : 'Ocultar contraseña'
    );
}