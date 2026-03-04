// Mejoras de UX - Semana 4
// Funcionalidades de productividad y mejoras de interfaz

// Inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    initializeUXEnhancements();
    initializeKeyboardShortcuts();
    initializeTooltips();
    initializeNotifications();
    initializeConfirmationDialogs();
    initializeFormEnhancements();
    initializeLoadingStates();
});

// Funciones principales de mejoras UX
function initializeUXEnhancements() {
    // Agregar clases de animación a elementos que aparecen
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
            }
        });
    }, observerOptions);

    // Observar elementos que deben animarse
    document.querySelectorAll('.card-enhanced, .btn-primary-enhanced, .status-indicator').forEach(el => {
        observer.observe(el);
    });
}

// Atajos de teclado para productividad
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + K para búsqueda rápida
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            openQuickSearch();
        }
        
        // Ctrl/Cmd + N para nuevo documento
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            createNewDocument();
        }
        
        // Escape para cerrar modales
        if (e.key === 'Escape') {
            closeModals();
        }
        
        // Ctrl/Cmd + S para guardar (prevenir comportamiento por defecto)
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            saveCurrentForm();
        }
    });
}

// Sistema de tooltips mejorado
function initializeTooltips() {
    // Crear tooltips dinámicos para elementos con data-tooltip
    document.querySelectorAll('[data-tooltip]').forEach(element => {
        const tooltipText = element.getAttribute('data-tooltip');
        
        // Crear elemento tooltip
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip-text';
        tooltip.textContent = tooltipText;
        
        element.classList.add('tooltip');
        element.appendChild(tooltip);
    });
}

// Sistema de notificaciones mejorado
function initializeNotifications() {
    const normalizeType = (value) => {
        const candidate = String(value ?? 'info').toLowerCase();
        if (candidate === 'error') {
            return 'danger';
        }

        return ['info', 'success', 'warning', 'danger'].includes(candidate) ? candidate : 'info';
    };

    const ensureToastStack = () => {
        let stack = document.getElementById('am-toast-stack');

        if (!stack) {
            stack = document.createElement('div');
            stack.id = 'am-toast-stack';
            stack.className = 'fixed right-4 top-4 z-50 flex w-full max-w-sm flex-col gap-3 pointer-events-none';
            document.body.appendChild(stack);
        }

        return stack;
    };

    window.showNotification = function(first, second = 'info', third = 5000, fourth = undefined) {
        let type = 'info';
        let title = '';
        let message = '';
        let duration = 5000;

        const isLegacySignature = ['info', 'success', 'warning', 'danger', 'error'].includes(String(first).toLowerCase())
            && typeof second === 'string'
            && typeof third === 'string';

        if (isLegacySignature) {
            type = normalizeType(first);
            title = String(second);
            message = String(third);
            duration = typeof fourth === 'number' ? fourth : 5000;
        } else {
            message = String(first ?? '');
            type = normalizeType(second);
            duration = typeof third === 'number' ? third : 5000;
        }

        const notification = document.createElement('div');
        notification.className = `am-toast am-toast--${type} pointer-events-auto slide-in-right`;
        notification.setAttribute('role', 'status');
        notification.setAttribute('aria-live', 'polite');

        const content = document.createElement('div');
        content.className = 'flex items-start justify-between gap-3';

        const textWrapper = document.createElement('div');
        textWrapper.className = 'min-w-0 flex-1';

        if (title) {
            const titleElement = document.createElement('p');
            titleElement.className = 'text-sm font-semibold tracking-tight';
            titleElement.textContent = title;
            textWrapper.appendChild(titleElement);
        }

        const text = document.createElement('p');
        text.className = title ? 'mt-1 block text-sm leading-5' : 'block text-sm leading-5';
        text.textContent = message;
        textWrapper.appendChild(text);

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-current/70 transition hover:bg-white/50 hover:text-current';
        closeButton.setAttribute('aria-label', 'Cerrar notificación');
        closeButton.innerHTML = '&times;';
        closeButton.addEventListener('click', () => notification.remove());

        content.appendChild(textWrapper);
        content.appendChild(closeButton);
        notification.appendChild(content);

        const stack = ensureToastStack();
        stack.appendChild(notification);
        
        // Auto-remover después del tiempo especificado
        setTimeout(() => {
            if (notification.parentElement) {
                notification.classList.add('fade-out');
                setTimeout(() => notification.remove(), 300);
            }
        }, duration);
    };
}

function initializeConfirmationDialogs() {
    const forms = document.querySelectorAll('form[data-confirm-message]');

    forms.forEach((form) => {
        if (form.dataset.confirmBound === '1') {
            return;
        }

        form.dataset.confirmBound = '1';

        form.addEventListener('submit', async (event) => {
            if (form.dataset.confirmBypassed === '1') {
                return;
            }

            event.preventDefault();

            const confirmed = await openConfirmModal({
                title: form.dataset.confirmTitle || 'Confirmar acción',
                message: form.dataset.confirmMessage || '¿Estás seguro de continuar?',
                confirmText: form.dataset.confirmButton || 'Confirmar',
                cancelText: form.dataset.cancelButton || 'Cancelar',
                tone: form.dataset.confirmTone || 'danger',
            });

            if (!confirmed) {
                return;
            }

            form.dataset.confirmBypassed = '1';
            form.requestSubmit();
            delete form.dataset.confirmBypassed;
        });
    });
}

function openConfirmModal({
    title = 'Confirmar acción',
    message = '¿Estás seguro de continuar?',
    confirmText = 'Confirmar',
    cancelText = 'Cancelar',
    tone = 'danger',
} = {}) {
    return new Promise((resolve) => {
        const toneMap = {
            danger: {
                confirmButton: 'bg-rose-600 text-white hover:bg-rose-700 focus:ring-rose-300/60',
                badge: 'bg-rose-100 text-rose-700 ring-1 ring-rose-200',
            },
            warning: {
                confirmButton: 'bg-amber-500 text-white hover:bg-amber-600 focus:ring-amber-300/60',
                badge: 'bg-amber-100 text-amber-700 ring-1 ring-amber-200',
            },
            info: {
                confirmButton: 'bg-sky-600 text-white hover:bg-sky-700 focus:ring-sky-300/60',
                badge: 'bg-sky-100 text-sky-700 ring-1 ring-sky-200',
            },
        };

        const selectedTone = toneMap[tone] ? tone : 'danger';
        const palette = toneMap[selectedTone];

        const overlay = document.createElement('div');
        overlay.className = 'fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm';

        const dialog = document.createElement('div');
        dialog.className = 'w-full max-w-md rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900';

        dialog.innerHTML = `
            <div class="flex items-start gap-3">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl ${palette.badge}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12V16.5Zm9-4.5a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">${title}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">${message}</p>
                </div>
            </div>
            <div class="mt-5 flex items-center justify-end gap-2">
                <button type="button" data-cancel class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-300/50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                    ${cancelText}
                </button>
                <button type="button" data-confirm class="inline-flex h-10 items-center justify-center rounded-xl px-4 text-sm font-semibold transition focus:outline-none focus:ring-2 ${palette.confirmButton}">
                    ${confirmText}
                </button>
            </div>
        `;

        const finish = (result) => {
            document.removeEventListener('keydown', handleEsc);
            overlay.remove();
            resolve(result);
        };

        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) {
                finish(false);
            }
        });

        dialog.querySelector('[data-cancel]')?.addEventListener('click', () => finish(false));
        dialog.querySelector('[data-confirm]')?.addEventListener('click', () => finish(true));

        const handleEsc = (event) => {
            if (event.key === 'Escape') {
                finish(false);
            }
        };

        document.addEventListener('keydown', handleEsc);
        overlay.appendChild(dialog);
        document.body.appendChild(overlay);
        dialog.querySelector('[data-confirm]')?.focus();
    });
}

// Mejoras para formularios
function initializeFormEnhancements() {
    // Auto-guardar formularios
    const forms = document.querySelectorAll('form[data-autosave]');
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('input', debounce(() => {
                autoSaveForm(form);
            }, 2000));
        });
    });
    
    // Validación en tiempo real
    document.querySelectorAll('input[required], textarea[required]').forEach(input => {
        input.addEventListener('blur', validateField);
        input.addEventListener('input', clearFieldError);
    });
    
    // Contador de caracteres para textareas
    document.querySelectorAll('textarea[maxlength]').forEach(textarea => {
        addCharacterCounter(textarea);
    });
}

// Estados de carga mejorados
function initializeLoadingStates() {
    // Interceptar formularios para mostrar loading
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                showButtonLoading(submitBtn);
            }
        });
    });
    
    // Interceptar enlaces que requieren loading
    document.querySelectorAll('a[data-loading]').forEach(link => {
        link.addEventListener('click', function(e) {
            showPageLoading();
        });
    });
}

// Funciones auxiliares
function openQuickSearch() {
    // Buscar el campo de búsqueda existente o crear uno modal
    const searchInput = document.querySelector('input[type="search"], input[placeholder*="buscar"], input[placeholder*="search"]');
    if (searchInput) {
        searchInput.focus();
        searchInput.select();
    } else {
        window.showNotification?.('Función de búsqueda rápida activada (Ctrl+K)', 'info');
    }
}

function createNewDocument() {
    // Buscar botón de crear nuevo documento
    const newDocBtn = document.querySelector('a[href*="create"], button[data-action="create"]');
    if (newDocBtn) {
        newDocBtn.click();
    } else {
        window.showNotification?.('Atajo para nuevo documento activado (Ctrl+N)', 'info');
    }
}

function closeModals() {
    // Cerrar modales abiertos
    const modals = document.querySelectorAll('.modal, [data-modal], .fi-modal');
    modals.forEach(modal => {
        if (modal.style.display !== 'none') {
            const closeBtn = modal.querySelector('.close, [data-close], .fi-modal-close-btn');
            if (closeBtn) {
                closeBtn.click();
            }
        }
    });
}

function saveCurrentForm() {
    const activeForm = document.querySelector('form:focus-within');
    if (activeForm) {
        const saveBtn = activeForm.querySelector('button[type="submit"], button[data-action="save"]');
        if (saveBtn) {
            saveBtn.click();
        } else {
            window.showNotification?.('Formulario guardado automáticamente', 'success');
        }
    }
}

function autoSaveForm(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Guardar en localStorage como respaldo
    const formId = form.id || 'autosave-form';
    localStorage.setItem(`autosave-${formId}`, JSON.stringify(data));
    
    // Mostrar indicador de guardado
    window.showNotification?.('Borrador guardado automáticamente', 'info', 2000);
}

function validateField(e) {
    const field = e.target;
    const value = field.value.trim();
    
    // Limpiar errores previos
    clearFieldError(e);
    
    // Validaciones básicas
    if (field.hasAttribute('required') && !value) {
        showFieldError(field, 'Este campo es obligatorio');
        return false;
    }
    
    if (field.type === 'email' && value && !isValidEmail(value)) {
        showFieldError(field, 'Ingrese un email válido');
        return false;
    }
    
    // Marcar como válido
    field.classList.add('form-input-success');
    return true;
}

function clearFieldError(e) {
    const field = e.target;
    field.classList.remove('form-input-error', 'shake-error');
    
    // Remover mensaje de error
    const errorMsg = field.parentElement.querySelector('.field-error');
    if (errorMsg) {
        errorMsg.remove();
    }
}

function showFieldError(field, message) {
    field.classList.add('form-input-error', 'shake-error');
    
    // Crear mensaje de error
    const errorMsg = document.createElement('div');
    errorMsg.className = 'field-error text-red-600 text-sm mt-1';
    errorMsg.textContent = message;
    
    field.parentElement.appendChild(errorMsg);
}

function addCharacterCounter(textarea) {
    const maxLength = parseInt(textarea.getAttribute('maxlength'));
    const counter = document.createElement('div');
    counter.className = 'character-counter text-sm text-gray-500 mt-1 text-right';
    
    const updateCounter = () => {
        const remaining = maxLength - textarea.value.length;
        counter.textContent = `${remaining} caracteres restantes`;
        
        if (remaining < 20) {
            counter.classList.add('text-red-500');
        } else {
            counter.classList.remove('text-red-500');
        }
    };
    
    textarea.addEventListener('input', updateCounter);
    textarea.parentElement.appendChild(counter);
    updateCounter();
}

function showButtonLoading(button) {
    const originalText = button.textContent;
    button.disabled = true;
    button.innerHTML = `
        <span class="loading-spinner mr-2"></span>
        Procesando...
    `;
    
    // Restaurar después de 10 segundos como fallback
    setTimeout(() => {
        button.disabled = false;
        button.textContent = originalText;
    }, 10000);
}

function showPageLoading() {
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.innerHTML = `
        <div class="text-center text-white">
            <div class="loading-spinner w-8 h-8 mx-auto mb-4"></div>
            <p>Cargando...</p>
        </div>
    `;
    
    document.body.appendChild(overlay);
    
    // Remover después de 5 segundos como fallback
    setTimeout(() => {
        if (overlay.parentElement) {
            overlay.remove();
        }
    }, 5000);
}

// Utilidades
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Exportar funciones para uso global
window.UXEnhancements = {
    showNotification: window.showNotification,
    showButtonLoading,
    showPageLoading,
    validateField,
    autoSaveForm
};

// Agregar estilos CSS adicionales dinámicamente
const additionalStyles = `
    .fade-out {
        opacity: 0;
        transform: translateX(20px);
        transition: all 0.3s ease-out;
    }
    
    .field-error {
        animation: slideInRight 0.3s ease-out;
    }
    
    .character-counter {
        transition: color 0.2s ease;
    }
`;

const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);
