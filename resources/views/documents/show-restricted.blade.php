@extends('layouts.app')

@section('title', $document->title)

@section('content')
@php
    $translateName = function ($model, string $fallback = 'Sin dato') {
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

        $raw = data_get($model, 'name');

        if (is_string($raw) && str_starts_with($raw, '{')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return (string) ($decoded[app()->getLocale()] ?? $decoded['es'] ?? $decoded['en'] ?? reset($decoded) ?? $fallback);
            }
        }

        return (string) ($raw ?: $fallback);
    };

    $statusLabel = $translateName($document->status, 'Sin estado');
    $categoryLabel = $translateName($document->category, 'Sin categoría');
    $departmentLabel = $translateName($document->department, 'Sin dependencia');
    $statusDotClass = str_contains(mb_strtolower($statusLabel), 'aprob') ? 'bg-emerald-500' : (str_contains(mb_strtolower($statusLabel), 'proceso') ? 'bg-amber-500' : 'bg-slate-400');
@endphp

<div class="space-y-6">
    <section class="rounded-2xl border border-white/70 bg-white p-5 shadow-sm motion-safe:animate-fade-in-up motion-safe:animate-duration-300 dark:border-slate-800 dark:bg-slate-900 am-motion-safe">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0">
                <div class="mb-2 flex flex-wrap items-center gap-2 text-sm">
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <span class="h-1.5 w-1.5 rounded-full {{ $statusDotClass }}"></span>
                        {{ $statusLabel }}
                    </span>
                    <span class="text-slate-500 dark:text-slate-400">ID: #{{ $document->id }}</span>
                    <span class="text-slate-300 dark:text-slate-600">•</span>
                    <span class="text-slate-500 dark:text-slate-400">{{ $document->document_number ?: 'Sin número de documento' }}</span>
                </div>
                <h1 class="truncate text-2xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-3xl">{{ $document->title }}</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600 dark:text-slate-400">
                    Este documento está bajo custodia de Archivo. Puedes ver sus datos básicos, pero necesitas solicitar acceso para consultar el detalle completo o descargarlo.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('documents.index') }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                    Volver
                </a>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-white/70 bg-white p-5 shadow-sm motion-safe:animate-fade-in-up motion-safe:animate-delay-75 dark:border-slate-800 dark:bg-slate-900 am-motion-safe">
        <h2 class="mb-4 text-lg font-semibold tracking-tight text-slate-900 dark:text-white">Información básica</h2>
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Categoría</dt>
                <dd class="mt-1 text-sm text-slate-800 dark:text-slate-200">{{ $categoryLabel }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Dependencia</dt>
                <dd class="mt-1 text-sm text-slate-800 dark:text-slate-200">{{ $departmentLabel }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Fecha</dt>
                <dd class="mt-1 text-sm text-slate-800 dark:text-slate-200">{{ $document->created_at?->format('d/m/Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Estado</dt>
                <dd class="mt-1 text-sm text-slate-800 dark:text-slate-200">{{ $statusLabel }}</dd>
            </div>
        </dl>
    </section>

    <section class="rounded-2xl border border-white/70 bg-white p-5 shadow-sm motion-safe:animate-fade-in-up motion-safe:animate-delay-150 dark:border-slate-800 dark:bg-slate-900 am-motion-safe">
        <h2 class="mb-4 text-lg font-semibold tracking-tight text-slate-900 dark:text-white">Acceso al documento</h2>

        @if($lastAccessRequest?->isPending())
            <x-ui.alert type="info" title="Solicitud enviada">
                Enviaste tu solicitud el {{ $lastAccessRequest->requested_at?->format('d/m/Y H:i') }}. Está pendiente de aprobación.
            </x-ui.alert>
        @elseif($lastAccessRequest?->status === 'rejected')
            <x-ui.alert type="danger" title="Solicitud rechazada">
                Tu solicitud fue rechazada{{ $lastAccessRequest->resolution_note ? ": {$lastAccessRequest->resolution_note}" : '.' }}
            </x-ui.alert>

            <form method="POST" action="{{ route('documents.access-requests.store', $document) }}" class="mt-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-800/40">
                @csrf
                <label for="reason" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Motivo (opcional)</label>
                <textarea name="reason" id="reason" rows="3" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"></textarea>
                <button type="submit" class="mt-3 inline-flex h-10 items-center justify-center rounded-xl bg-gradient-to-r from-sky-500 to-indigo-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:from-sky-400 hover:to-indigo-500">
                    Volver a solicitar acceso
                </button>
            </form>
        @elseif($lastAccessRequest?->status === 'expired')
            <x-ui.alert type="warning" title="Acceso expirado">
                Tu acceso expiró el {{ $lastAccessRequest->expires_at?->format('d/m/Y H:i') }}.
            </x-ui.alert>

            <form method="POST" action="{{ route('documents.access-requests.store', $document) }}" class="mt-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-800/40">
                @csrf
                <label for="reason" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Motivo (opcional)</label>
                <textarea name="reason" id="reason" rows="3" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"></textarea>
                <button type="submit" class="mt-3 inline-flex h-10 items-center justify-center rounded-xl bg-gradient-to-r from-sky-500 to-indigo-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:from-sky-400 hover:to-indigo-500">
                    Volver a solicitar acceso
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('documents.access-requests.store', $document) }}" class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-800/40">
                @csrf
                <label for="reason" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Motivo (opcional)</label>
                <textarea name="reason" id="reason" rows="3" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"></textarea>
                <button type="submit" class="mt-3 inline-flex h-10 items-center justify-center rounded-xl bg-gradient-to-r from-sky-500 to-indigo-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:from-sky-400 hover:to-indigo-500">
                    Solicitar acceso
                </button>
            </form>
        @endif
    </section>
</div>
@endsection
