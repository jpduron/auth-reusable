/**
 * =========================================
 * MÓDULO UI
 * =========================================
 * Utilidades reutilizables para la interfaz de usuario.
 */

/**
 * Muestra una notificación tipo "toast" en la esquina superior derecha.
 */
export function showToast(message, type = 'info', duration = 4000) {
    // Crear contenedor si no existe
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '1080';
        document.body.appendChild(container);
    }

    // Mapeo de íconos por tipo
    const iconMap = {
        success: 'bi-check-circle-fill',
        danger: 'bi-exclamation-triangle-fill',
        warning: 'bi-exclamation-circle-fill',
        info: 'bi-info-circle-fill'
    };

    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-bg-${type} border-0`;
    toastEl.setAttribute('role', 'alert');
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi ${iconMap[type] || iconMap.info} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;

    container.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl, { delay: duration });
    toast.show();

    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

/**
 * Muestra un diálogo de confirmación personalizado.
 * @returns {Promise<boolean>}
 */
export function confirmDialog(message, title = 'Confirmar') {
    return new Promise((resolve) => {
        const modalHtml = `
            <div class="modal fade" id="confirmModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${title}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">${message}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-danger" id="btnConfirmar">Confirmar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const wrapper = document.createElement('div');
        wrapper.innerHTML = modalHtml;
        document.body.appendChild(wrapper);

        const modalEl = wrapper.querySelector('#confirmModal');
        const modal = new bootstrap.Modal(modalEl);

        modalEl.querySelector('#btnConfirmar').addEventListener('click', () => {
            resolve(true);
            modal.hide();
        });

        modalEl.addEventListener('hidden.bs.toast', () => wrapper.remove());
        modalEl.addEventListener('hidden.bs.modal', () => {
            resolve(false);
            setTimeout(() => wrapper.remove(), 300);
        });

        modal.show();
    });
}

/**
 * Formatea una fecha ISO a formato legible.
 */
export function formatDate(isoString) {
    if (!isoString) return '—';
    const date = new Date(isoString);
    return date.toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
}

/**
 * Formatea fecha y hora.
 */
export function formatDateTime(isoString) {
    if (!isoString) return '—';
    const date = new Date(isoString);
    return date.toLocaleString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}