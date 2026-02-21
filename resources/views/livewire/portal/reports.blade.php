<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Reportes personales</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400">Documentos enviados y recibidos por tu usuario</p>
    </div>

    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="date_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Desde</label>
                <input
                    type="date"
                    id="date_from"
                    name="date_from"
                    wire:model.live="dateFrom"
                    class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                >
            </div>
            <div>
                <label for="date_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Hasta</label>
                <input
                    type="date"
                    id="date_to"
                    name="date_to"
                    wire:model.live="dateTo"
                    class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                >
            </div>
            <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900/50">
                <p class="text-sm text-gray-500 dark:text-gray-400">Enviados</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $summary['sent'] }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900/50">
                <p class="text-sm text-gray-500 dark:text-gray-400">Recibidos</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $summary['received'] }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-lg bg-white shadow dark:bg-gray-800">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Enviados</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Documento</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Estado</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($sentDocuments as $document)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $document->title }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $document->document_number }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $document->status?->name ?? 'Sin estado' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $document->created_at?->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400" colspan="3">
                                    No hay documentos enviados en este rango.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-lg bg-white shadow dark:bg-gray-800">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Recibidos</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Documento</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Estado</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($receivedDocuments as $document)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $document->title }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $document->document_number }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $document->status?->name ?? 'Sin estado' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $document->created_at?->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400" colspan="3">
                                    No hay documentos recibidos en este rango.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
