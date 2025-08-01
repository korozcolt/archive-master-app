<!-- Quick Search Modal -->
<div id="quick-search-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeQuickSearch()"></div>
        
        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="w-full">
                        <!-- Search Header -->
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                                🔍 Búsqueda Rápida
                            </h3>
                            <button onclick="closeQuickSearch()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Search Input -->
                        <div class="relative mb-4">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <input type="text" 
                                   id="quick-search-input" 
                                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 rounded-lg leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                   placeholder="Buscar documentos, usuarios, categorías..." 
                                   autocomplete="off">
                        </div>
                        
                        <!-- Search Filters -->
                        <div class="flex flex-wrap gap-2 mb-4">
                            <button onclick="setSearchFilter('documents')" 
                                    class="search-filter-btn px-3 py-1 text-sm rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" 
                                    data-filter="documents">
                                📄 Documentos
                            </button>
                            <button onclick="setSearchFilter('users')" 
                                    class="search-filter-btn px-3 py-1 text-sm rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" 
                                    data-filter="users">
                                👤 Usuarios
                            </button>
                            <button onclick="setSearchFilter('categories')" 
                                    class="search-filter-btn px-3 py-1 text-sm rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" 
                                    data-filter="categories">
                                📁 Categorías
                            </button>
                            <button onclick="setSearchFilter('all')" 
                                    class="search-filter-btn active px-3 py-1 text-sm rounded-full border border-blue-500 bg-blue-500 text-white transition-colors" 
                                    data-filter="all">
                                🔍 Todo
                            </button>
                        </div>
                        
                        <!-- Search Results -->
                        <div id="search-results" class="max-h-96 overflow-y-auto">
                            <!-- Loading State -->
                            <div id="search-loading" class="hidden text-center py-8">
                                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                                <p class="mt-2 text-gray-500 dark:text-gray-400">Buscando...</p>
                            </div>
                            
                            <!-- Empty State -->
                            <div id="search-empty" class="text-center py-8 text-gray-500 dark:text-gray-400">
                                <div class="text-4xl mb-2">🔍</div>
                                <p>Escribe para buscar documentos, usuarios o categorías</p>
                                <div class="mt-4 text-sm">
                                    <p class="font-medium mb-2">Consejos de búsqueda:</p>
                                    <ul class="text-left space-y-1">
                                        <li>• Usa palabras clave específicas</li>
                                        <li>• Busca por número de documento</li>
                                        <li>• Filtra por tipo de contenido</li>
                                        <li>• Usa comillas para frases exactas</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- Results Container -->
                            <div id="search-results-container" class="hidden space-y-2">
                                <!-- Results will be populated here -->
                            </div>
                            
                            <!-- No Results -->
                            <div id="search-no-results" class="hidden text-center py-8 text-gray-500 dark:text-gray-400">
                                <div class="text-4xl mb-2">😔</div>
                                <p>No se encontraron resultados</p>
                                <p class="text-sm mt-2">Intenta con otros términos de búsqueda</p>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">⚡ Acciones Rápidas</h4>
                            <div class="grid grid-cols-2 gap-2">
                                <a href="{{ route('filament.admin.resources.documents.create') }}" 
                                   class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                   onclick="closeQuickSearch()">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Nuevo Documento
                                </a>
                                <a href="{{ route('filament.admin.resources.documents.index') }}" 
                                   class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                   onclick="closeQuickSearch()">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                    </svg>
                                    Ver Documentos
                                </a>
                                <button onclick="showAdvancedSearch()" 
                                        class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4"></path>
                                    </svg>
                                    Búsqueda Avanzada
                                </button>
                                <button onclick="showSearchHelp()" 
                                        class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Ayuda
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentSearchFilter = 'all';
let searchTimeout = null;

function openQuickSearch() {
    const modal = document.getElementById('quick-search-modal');
    const input = document.getElementById('quick-search-input');
    
    modal.classList.remove('hidden');
    setTimeout(() => {
        input.focus();
    }, 100);
    
    // Add escape key listener
    document.addEventListener('keydown', handleQuickSearchKeydown);
}

function closeQuickSearch() {
    const modal = document.getElementById('quick-search-modal');
    modal.classList.add('hidden');
    
    // Clear search
    document.getElementById('quick-search-input').value = '';
    showSearchState('empty');
    
    // Remove escape key listener
    document.removeEventListener('keydown', handleQuickSearchKeydown);
}

function handleQuickSearchKeydown(event) {
    if (event.key === 'Escape') {
        closeQuickSearch();
    }
}

function setSearchFilter(filter) {
    currentSearchFilter = filter;
    
    // Update button states
    document.querySelectorAll('.search-filter-btn').forEach(btn => {
        btn.classList.remove('active', 'bg-blue-500', 'text-white', 'border-blue-500');
        btn.classList.add('border-gray-300', 'dark:border-gray-600', 'text-gray-700', 'dark:text-gray-300');
    });
    
    const activeBtn = document.querySelector(`[data-filter="${filter}"]`);
    activeBtn.classList.add('active', 'bg-blue-500', 'text-white', 'border-blue-500');
    activeBtn.classList.remove('border-gray-300', 'dark:border-gray-600', 'text-gray-700', 'dark:text-gray-300');
    
    // Re-run search if there's a query
    const query = document.getElementById('quick-search-input').value;
    if (query.trim()) {
        performSearch(query);
    }
}

function performSearch(query) {
    if (!query.trim()) {
        showSearchState('empty');
        return;
    }
    
    showSearchState('loading');
    
    // Clear previous timeout
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    
    // Debounce search
    searchTimeout = setTimeout(() => {
        // Simulate API call
        setTimeout(() => {
            const results = generateMockResults(query, currentSearchFilter);
            displaySearchResults(results);
        }, 500);
    }, 300);
}

function generateMockResults(query, filter) {
    const mockDocuments = [
        { type: 'document', id: 1, title: 'Contrato de Servicios 2024', number: 'DOC-001', category: 'Contratos', status: 'Pendiente' },
        { type: 'document', id: 2, title: 'Factura Proveedor ABC', number: 'DOC-002', category: 'Facturas', status: 'Aprobado' },
        { type: 'document', id: 3, title: 'Reporte Mensual Enero', number: 'DOC-003', category: 'Reportes', status: 'En Revisión' },
    ];
    
    const mockUsers = [
        { type: 'user', id: 1, name: 'Juan Pérez', email: 'juan@empresa.com', role: 'Administrador' },
        { type: 'user', id: 2, name: 'María García', email: 'maria@empresa.com', role: 'Usuario' },
    ];
    
    const mockCategories = [
        { type: 'category', id: 1, name: 'Contratos', description: 'Documentos contractuales', count: 15 },
        { type: 'category', id: 2, name: 'Facturas', description: 'Facturas y comprobantes', count: 32 },
    ];
    
    let results = [];
    
    if (filter === 'all' || filter === 'documents') {
        results = results.concat(mockDocuments.filter(item => 
            item.title.toLowerCase().includes(query.toLowerCase()) ||
            item.number.toLowerCase().includes(query.toLowerCase())
        ));
    }
    
    if (filter === 'all' || filter === 'users') {
        results = results.concat(mockUsers.filter(item => 
            item.name.toLowerCase().includes(query.toLowerCase()) ||
            item.email.toLowerCase().includes(query.toLowerCase())
        ));
    }
    
    if (filter === 'all' || filter === 'categories') {
        results = results.concat(mockCategories.filter(item => 
            item.name.toLowerCase().includes(query.toLowerCase())
        ));
    }
    
    return results;
}

function displaySearchResults(results) {
    const container = document.getElementById('search-results-container');
    
    if (results.length === 0) {
        showSearchState('no-results');
        return;
    }
    
    container.innerHTML = results.map(result => {
        switch (result.type) {
            case 'document':
                return `
                    <div class="p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg cursor-pointer transition-colors" onclick="selectResult('document', ${result.id})">
                        <div class="flex items-center">
                            <div class="text-2xl mr-3">📄</div>
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900 dark:text-white">${result.title}</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">${result.number} • ${result.category} • ${result.status}</p>
                            </div>
                        </div>
                    </div>
                `;
            case 'user':
                return `
                    <div class="p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg cursor-pointer transition-colors" onclick="selectResult('user', ${result.id})">
                        <div class="flex items-center">
                            <div class="text-2xl mr-3">👤</div>
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900 dark:text-white">${result.name}</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">${result.email} • ${result.role}</p>
                            </div>
                        </div>
                    </div>
                `;
            case 'category':
                return `
                    <div class="p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg cursor-pointer transition-colors" onclick="selectResult('category', ${result.id})">
                        <div class="flex items-center">
                            <div class="text-2xl mr-3">📁</div>
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900 dark:text-white">${result.name}</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">${result.description} • ${result.count} documentos</p>
                            </div>
                        </div>
                    </div>
                `;
            default:
                return '';
        }
    }).join('');
    
    showSearchState('results');
}

function showSearchState(state) {
    const states = ['empty', 'loading', 'results', 'no-results'];
    
    states.forEach(s => {
        const element = document.getElementById(`search-${s}`);
        if (element) {
            if (s === state) {
                element.classList.remove('hidden');
            } else {
                element.classList.add('hidden');
            }
        }
    });
    
    const container = document.getElementById('search-results-container');
    if (state === 'results') {
        container.classList.remove('hidden');
    } else {
        container.classList.add('hidden');
    }
}

function selectResult(type, id) {
    // Handle result selection
    switch (type) {
        case 'document':
            window.location.href = `/admin/documents/${id}/edit`;
            break;
        case 'user':
            showNotification('info', 'Usuario seleccionado', `Navegando al perfil del usuario ID: ${id}`);
            break;
        case 'category':
            window.location.href = `/admin/documents?tableFilters[category_id][value]=${id}`;
            break;
    }
    closeQuickSearch();
}

function showAdvancedSearch() {
    closeQuickSearch();
    showNotification('info', 'Búsqueda Avanzada', 'Funcionalidad en desarrollo. Próximamente disponible.');
}

function showSearchHelp() {
    showNotification('info', 'Ayuda de Búsqueda', 'Usa palabras clave, números de documento o filtra por tipo de contenido para mejores resultados.');
}

// Initialize search input listener
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('quick-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            performSearch(e.target.value);
        });
    }
});
</script>