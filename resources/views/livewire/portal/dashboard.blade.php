<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Portal</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">Resumen personal de documentos</p>
        </div>
        <a href="{{ route('documents.create') }}" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            Nuevo Documento
        </a>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total</p>
            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $summary['total'] }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">Enviados</p>
            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $summary['sent'] }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">Recibidos</p>
            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $summary['received'] }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">Pendientes</p>
            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $summary['pending'] }}</p>
        </div>
    </div>

    <div class="rounded-lg bg-white shadow dark:bg-gray-800">
        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Documentos recientes</h2>
            <a href="{{ route('documents.index') }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">Ver todos</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Documento</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Estado</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Asignado</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Fecha</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($recentDocuments as $document)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $document->title }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $document->document_number }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $document->status?->name ?? 'Sin estado' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $document->assignee?->name ?? 'Sin asignar' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $document->created_at?->format('d/m/Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400" colspan="4">
                                No hay documentos recientes.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
