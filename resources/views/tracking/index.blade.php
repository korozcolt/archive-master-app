<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking de Documentos - ArchiveMaster</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <div class="container mx-auto px-4 py-12">
        <!-- Header -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-indigo-600 rounded-full mb-4">
                <i class="fas fa-search-location text-3xl text-white"></i>
            </div>
            <h1 class="text-4xl font-bold text-gray-800 mb-2">Tracking de Documentos</h1>
            <p class="text-gray-600 text-lg">Consulte el estado de su documento en tiempo real</p>
        </div>

        <!-- Main Card -->
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                <!-- Card Header -->
                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-8 py-6">
                    <h2 class="text-2xl font-semibold text-white flex items-center">
                        <i class="fas fa-barcode mr-3"></i>
                        Ingrese su Código de Tracking
                    </h2>
                </div>

                <!-- Card Body -->
                <div class="p-8">
                    @if(session('error'))
                        <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                                <p class="text-red-700 font-medium">{{ session('error') }}</p>
                            </div>
                        </div>
                    @endif

                    <form action="{{ route('tracking.track') }}" method="POST" class="space-y-6">
                        @csrf

                        <!-- Tracking Code Input -->
                        <div>
                            <label for="tracking_code" class="block text-sm font-medium text-gray-700 mb-2">
                                Código de Tracking
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-hashtag text-gray-400"></i>
                                </div>
                                <input
                                    type="text"
                                    name="tracking_code"
                                    id="tracking_code"
                                    value="{{ old('tracking_code') }}"
                                    class="block w-full pl-10 pr-3 py-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-lg font-mono uppercase tracking-wider transition duration-150 ease-in-out @error('tracking_code') border-red-500 @enderror"
                                    placeholder="Ej: A1B2C3D4E5F6G7H8I9J0K1L2M3N4O5P6"
                                    maxlength="32"
                                    required
                                    autofocus
                                >
                            </div>
                            @error('tracking_code')
                                <p class="mt-2 text-sm text-red-600 flex items-center">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    {{ $message }}
                                </p>
                            @enderror
                            <p class="mt-2 text-sm text-gray-500">
                                <i class="fas fa-info-circle mr-1"></i>
                                El código debe tener exactamente 32 caracteres
                            </p>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold py-4 px-6 rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition duration-200 ease-in-out flex items-center justify-center text-lg"
                        >
                            <i class="fas fa-search mr-2"></i>
                            Rastrear Documento
                        </button>
                    </form>
                </div>

                <!-- Card Footer -->
                <div class="bg-gray-50 px-8 py-6 border-t border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                        <div class="flex flex-col items-center">
                            <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mb-2">
                                <i class="fas fa-shield-alt text-indigo-600"></i>
                            </div>
                            <h3 class="font-semibold text-gray-700 text-sm">Seguro</h3>
                            <p class="text-xs text-gray-500">Información protegida</p>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mb-2">
                                <i class="fas fa-clock text-green-600"></i>
                            </div>
                            <h3 class="font-semibold text-gray-700 text-sm">Tiempo Real</h3>
                            <p class="text-xs text-gray-500">Estado actualizado</p>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mb-2">
                                <i class="fas fa-history text-purple-600"></i>
                            </div>
                            <h3 class="font-semibold text-gray-700 text-sm">Historial</h3>
                            <p class="text-xs text-gray-500">Línea de tiempo completa</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Help Section -->
            <div class="mt-8 text-center">
                <p class="text-gray-600">
                    <i class="fas fa-question-circle mr-1"></i>
                    ¿No tiene un código de tracking? Contacte con la empresa que gestionó su documento.
                </p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-12 pb-8 text-center text-gray-500 text-sm">
        <p>&copy; {{ date('Y') }} ArchiveMaster. Todos los derechos reservados.</p>
    </footer>

    <script>
        // Auto-formatear el código a mayúsculas mientras se escribe
        document.getElementById('tracking_code').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
    </script>
</body>
</html>
