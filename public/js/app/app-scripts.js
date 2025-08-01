// Mejoras de UX - Semana 4
// Funcionalidades de productividad y mejoras de interfaz

// Inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    initializeUXEnhancements();
    initializeKeyboardShortcuts();
    initializeTooltips();
    initializeNotifications();
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
function showNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `notification-${type} fixed top-4 right-4 z-50 max-w-sm slide-in-right`;
    notification.innerHTML = `
        <div class="flex items-center justify-between">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg font-bold">&times;</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remover después del tiempo especificado
    setTimeout(() => {
        if (notification.parentElement) {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        }
    }, duration);
}

function initializeNotifications() {
    // Make showNotification available globally
    window.showNotification = showNotification;
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
        showNotification('Función de búsqueda rápida activada (Ctrl+K)', 'info');
    }
}

function createNewDocument() {
    // Buscar botón de crear nuevo documento
    const newDocBtn = document.querySelector('a[href*="create"], button[data-action="create"]');
    if (newDocBtn) {
        newDocBtn.click();
    } else {
        showNotification('Atajo para nuevo documento activado (Ctrl+N)', 'info');
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
            showNotification('Formulario guardado automáticamente', 'success');
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
    showNotification('Borrador guardado automáticamente', 'info', 2000);
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
    showNotification,
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
    .slide-in-right {
        animation: slideInRight 0.3s ease-out;
    }
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100%);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    .notification-info {
        background-color: #3b82f6;
        color: white;
        padding: 1rem;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    .notification-success {
        background-color: #10b981;
        color: white;
        padding: 1rem;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    .notification-warning {
        background-color: #f59e0b;
        color: white;
        padding: 1rem;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    .notification-error {
        background-color: #ef4444;
        color: white;
        padding: 1rem;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
`;

const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);