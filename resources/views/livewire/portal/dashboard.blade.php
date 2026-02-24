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
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-600 dark:text-sky-400">Portal Operativo</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Resumen personal de documentos</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Visión rápida de tus documentos, estados y actividad reciente.</p>
            </div>
            <a href="{{ route('documents.create') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-sky-300/30 bg-gradient-to-r from-sky-500 to-indigo-600 px-4 text-sm font-semibold text-white shadow-lg shadow-indigo-900/20 transition hover:from-sky-400 hover:to-indigo-500">
                Nuevo Documento
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-white/70 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Total</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $summary['total'] }}</p>
        </div>
        <div class="rounded-2xl border border-white/70 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Enviados</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $summary['sent'] }}</p>
        </div>
        <div class="rounded-2xl border border-white/70 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Recibidos</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $summary['received'] }}</p>
        </div>
        <div class="rounded-2xl border border-white/70 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Pendientes</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $summary['pending'] }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-white/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
            <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">Documentos recientes</h2>
            <a href="{{ route('documents.index') }}" class="inline-flex items-center rounded-lg px-3 py-1.5 text-sm font-medium text-sky-700 transition hover:bg-sky-50 dark:text-sky-300 dark:hover:bg-slate-800">Ver todos</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-slate-50 dark:bg-slate-950/60">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Documento</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Estado</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Asignado</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Fecha</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($recentDocuments as $document)
                        @php
                            $latestVersion = $document->versions->first();
                            $documentFileExtension = \App\Support\FileExtensionIcon::extensionFromPath($document->file_path)
                                ?: \App\Support\FileExtensionIcon::extensionFromPath($latestVersion?->file_path)
                                ?: \App\Support\FileExtensionIcon::normalizeExtension($latestVersion?->file_extension);
                        @endphp
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                            <td class="px-5 py-3.5">
                                <div class="flex items-start gap-3">
                                    <x-file-extension-icon
                                        :extension="$documentFileExtension"
                                        class="mt-0.5 h-9 w-9 shrink-0 rounded-xl"
                                        size="h-4 w-4"
                                        :data-file-ext="$documentFileExtension"
                                    />
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ $document->title }}</div>
                                        <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $document->document_number }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-sm text-slate-700 dark:text-slate-300">
                                <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                    {{ $translateName($document->status, 'Sin estado') }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-sm text-slate-700 dark:text-slate-300">{{ $document->assignee?->name ?? 'Sin asignar' }}</td>
                            <td class="px-5 py-3.5 text-sm text-slate-700 dark:text-slate-300">{{ $document->created_at?->format('d/m/Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400" colspan="4">
                                No hay documentos recientes.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
