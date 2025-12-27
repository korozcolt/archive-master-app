<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking: {{ $document['title'] }} - ArchiveMaster</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <a href="{{ route('tracking.index') }}" class="inline-flex items-center text-indigo-600 hover:text-indigo-800 font-medium mb-4">
                <i class="fas fa-arrow-left mr-2"></i>
                Realizar otra consulta
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Estado del Documento</h1>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Document Info Card -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Main Info -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-file-alt mr-2"></i>
                            Información del Documento
                        </h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800 mb-2">{{ $document['title'] }}</h3>
                            @if($document['description'])
                                <p class="text-gray-600">{{ $document['description'] }}</p>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-gray-200">
                            @if($document['category'])
                            <div class="flex items-start">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                    <i class="fas fa-folder text-blue-600"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 uppercase font-medium">Categoría</p>
                                    <p class="text-gray-800 font-semibold">{{ $document['category'] }}</p>
                                </div>
                            </div>
                            @endif

                            @if($document['company'])
                            <div class="flex items-start">
                                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                    <i class="fas fa-building text-purple-600"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 uppercase font-medium">Empresa</p>
                                    <p class="text-gray-800 font-semibold">{{ $document['company'] }}</p>
                                </div>
                            </div>
                            @endif

                            <div class="flex items-start">
                                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                    <i class="fas fa-calendar-plus text-green-600"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 uppercase font-medium">Creado</p>
                                    <p class="text-gray-800 font-semibold">{{ \Carbon\Carbon::parse($document['created_at'])->format('d/m/Y H:i') }}</p>
                                </div>
                            </div>

                            <div class="flex items-start">
                                <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                    <i class="fas fa-sync-alt text-orange-600"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 uppercase font-medium">Última actualización</p>
                                    <p class="text-gray-800 font-semibold">{{ \Carbon\Carbon::parse($document['updated_at'])->diffForHumans() }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-history mr-2"></i>
                            Línea de Tiempo
                        </h2>
                    </div>
                    <div class="p-6">
                        @if($document['workflow_history'] && count($document['workflow_history']) > 0)
                            <div class="space-y-6">
                                @foreach($document['workflow_history'] as $index => $history)
                                    <div class="relative pl-8 pb-6 {{ $loop->last ? '' : 'border-l-2 border-gray-200' }}">
                                        <!-- Timeline dot -->
                                        <div class="absolute left-0 top-0 -translate-x-1/2 w-4 h-4 rounded-full {{ $loop->first ? 'bg-green-500' : 'bg-indigo-500' }} border-4 border-white shadow"></div>

                                        <div class="bg-gray-50 rounded-lg p-4 hover:shadow-md transition duration-200">
                                            <div class="flex items-start justify-between mb-2">
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                                            {{ $history['from_status'] }}
                                                        </span>
                                                        <i class="fas fa-arrow-right text-gray-400"></i>
                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                                            {{ $history['to_status'] }}
                                                        </span>
                                                    </div>
                                                    @if($history['comment'])
                                                        <p class="text-sm text-gray-600 mt-2">
                                                            <i class="fas fa-comment-alt mr-1"></i>
                                                            {{ $history['comment'] }}
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex items-center justify-between text-xs text-gray-500 mt-3 pt-3 border-t border-gray-200">
                                                <span class="flex items-center">
                                                    <i class="fas fa-user mr-1"></i>
                                                    {{ $history['user_name'] }}
                                                </span>
                                                <span class="flex items-center">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    {{ \Carbon\Carbon::parse($history['date'])->format('d/m/Y H:i') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-12">
                                <i class="fas fa-inbox text-gray-300 text-5xl mb-4"></i>
                                <p class="text-gray-500">No hay historial de cambios disponible</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Current Status -->
                @if($document['status'])
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gray-800 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white flex items-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            Estado Actual
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="inline-flex items-center px-4 py-2 rounded-lg text-lg font-bold" style="background-color: {{ $document['status']['color'] }}20; color: {{ $document['status']['color'] }}">
                            <i class="fas fa-circle mr-2 text-sm"></i>
                            {{ $document['status']['name'] }}
                        </div>
                    </div>
                </div>
                @endif

                <!-- Tracking Info -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gray-800 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white flex items-center">
                            <i class="fas fa-barcode mr-2"></i>
                            Código de Tracking
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="bg-gray-50 rounded-lg p-4 border-2 border-dashed border-gray-300">
                            <p class="text-xs text-gray-500 uppercase font-medium mb-2">Su código</p>
                            <p class="font-mono text-sm font-bold text-gray-800 break-all">{{ $document['tracking_code'] }}</p>
                        </div>

                        @if($document['tracking_expires_at'])
                            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                <p class="text-xs text-yellow-800 flex items-center">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    <span>
                                        <strong>Expira:</strong>
                                        {{ \Carbon\Carbon::parse($document['tracking_expires_at'])->format('d/m/Y H:i') }}
                                    </span>
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Actions -->
                <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <i class="fas fa-share-alt mr-2"></i>
                        Compartir
                    </h3>
                    <p class="text-sm text-indigo-100 mb-4">
                        Comparta este código con otras personas para que puedan consultar el estado
                    </p>
                    <button
                        onclick="copyTrackingCode()"
                        class="w-full bg-white text-indigo-600 font-semibold py-2 px-4 rounded-lg hover:bg-indigo-50 transition duration-200 flex items-center justify-center"
                    >
                        <i class="fas fa-copy mr-2"></i>
                        Copiar Código
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-12 pb-8 text-center text-gray-500 text-sm">
        <p>&copy; {{ date('Y') }} ArchiveMaster. Todos los derechos reservados.</p>
    </footer>

    <script>
        function copyTrackingCode() {
            const code = '{{ $document['tracking_code'] }}';
            navigator.clipboard.writeText(code).then(() => {
                // Cambiar el botón temporalmente para dar feedback
                const btn = event.target.closest('button');
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check mr-2"></i>¡Copiado!';
                btn.classList.add('bg-green-50', 'text-green-600');

                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.classList.remove('bg-green-50', 'text-green-600');
                }, 2000);
            }).catch(err => {
                alert('Error al copiar el código. Por favor cópielo manualmente.');
            });
        }
    </script>
</body>
</html>
