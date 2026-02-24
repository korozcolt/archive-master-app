@php
    $translateName = function ($model, string $fallback = 'Sin estado') {
        if ($model && method_exists($model, 'getTranslation')) {
            $value = $model->getTranslation('name', app()->getLocale(), false);

            if (is_string($value) && str_starts_with($value, '{')) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    return (string) ($decoded[app()->getLocale()] ?? $decoded['es'] ?? $decoded['en'] ?? reset($decoded) ?? $fallback);
                }
            }

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return (string) (data_get($model, 'name') ?: $fallback);
    };
@endphp

<div class="space-y-6">
    <div class="rounded-2xl border border-white/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-indigo-600 dark:text-indigo-400">Reportes</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Reportes personales</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Documentos enviados y recibidos por tu usuario</p>
    </div>

    <div class="rounded-2xl border border-white/70 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                <label for="date_from" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Desde</label>
                <input
                    type="date"
                    id="date_from"
                    name="date_from"
                    wire:model.live="dateFrom"
                    class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                >
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                <label for="date_to" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Hasta</label>
                <input
                    type="date"
                    id="date_to"
                    name="date_to"
                    wire:model.live="dateTo"
                    class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                >
            </div>
            <div class="rounded-xl border border-slate-200 bg-gradient-to-br from-white to-sky-50 p-4 dark:border-slate-700 dark:from-slate-900 dark:to-slate-800">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Enviados</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $summary['sent'] }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-gradient-to-br from-white to-indigo-50 p-4 dark:border-slate-700 dark:from-slate-900 dark:to-slate-800">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Recibidos</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $summary['received'] }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="overflow-hidden rounded-2xl border border-white/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">Enviados</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950/60">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Documento</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Estado</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($sentDocuments as $document)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                <td class="px-5 py-3.5">
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $document->title }}</div>
                                    <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $document->document_number }}</div>
                                </td>
                                <td class="px-5 py-3.5 text-sm text-slate-700 dark:text-slate-300">
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                        {{ $translateName($document->status, 'Sin estado') }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-sm text-slate-700 dark:text-slate-300">{{ $document->created_at?->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400" colspan="3">
                                    No hay documentos enviados en este rango.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-white/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">Recibidos</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950/60">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Documento</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Estado</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($receivedDocuments as $document)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                <td class="px-5 py-3.5">
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $document->title }}</div>
                                    <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $document->document_number }}</div>
                                </td>
                                <td class="px-5 py-3.5 text-sm text-slate-700 dark:text-slate-300">
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                        {{ $translateName($document->status, 'Sin estado') }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-sm text-slate-700 dark:text-slate-300">{{ $document->created_at?->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400" colspan="3">
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
