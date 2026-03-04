@props([
    'type' => 'info',
    'title' => null,
    'dismissible' => true,
])

@php
    $resolvedType = match ($type) {
        'error' => 'danger',
        default => $type,
    };

    $iconPaths = [
        'success' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'danger' => 'M12 9v3.75m0 3.75h.008v.008H12V16.5Zm9-4.5a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'warning' => 'm11.25 9.75.042-.02a.75.75 0 0 1 1.04.852l-.708 2.836a.75.75 0 0 0 .854.928l.09-.017M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'info' => 'm11.25 11.25.041-.02a.75.75 0 0 1 1.02.866l-.708 2.836a.75.75 0 0 0 .853.928l.088-.017M12 8.25h.008v.008H12V8.25Zm9 3.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    ];

    $styleMap = [
        'success' => 'am-alert--success border-emerald-200/90 bg-emerald-50/90 text-emerald-900 dark:border-emerald-600/30 dark:bg-emerald-500/10 dark:text-emerald-100',
        'danger' => 'am-alert--danger border-rose-200/90 bg-rose-50/90 text-rose-900 dark:border-rose-600/30 dark:bg-rose-500/10 dark:text-rose-100',
        'warning' => 'am-alert--warning border-amber-200/90 bg-amber-50/90 text-amber-900 dark:border-amber-600/30 dark:bg-amber-500/10 dark:text-amber-100',
        'info' => 'am-alert--info border-sky-200/90 bg-sky-50/90 text-sky-900 dark:border-sky-600/30 dark:bg-sky-500/10 dark:text-sky-100',
    ];

    $ringMap = [
        'success' => 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200/80 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30',
        'danger' => 'bg-rose-100 text-rose-700 ring-1 ring-rose-200/80 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30',
        'warning' => 'bg-amber-100 text-amber-700 ring-1 ring-amber-200/80 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
        'info' => 'bg-sky-100 text-sky-700 ring-1 ring-sky-200/80 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-sky-500/30',
    ];

    $safeType = array_key_exists($resolvedType, $styleMap) ? $resolvedType : 'info';
@endphp

<div
    data-alert
    {{ $attributes->class([
        'am-alert relative overflow-hidden rounded-2xl border px-4 py-3 shadow-sm backdrop-blur-sm',
        $styleMap[$safeType],
    ]) }}
    role="alert"
>
    <div class="flex items-start gap-3">
        <div class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl {{ $ringMap[$safeType] }}">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPaths[$safeType] }}" />
            </svg>
        </div>

        <div class="min-w-0 flex-1">
            @if (filled($title))
                <p class="text-sm font-semibold tracking-tight">{{ $title }}</p>
            @endif

            <div @class(['text-sm leading-5', 'mt-1' => filled($title)])>
                {{ $slot }}
            </div>
        </div>

        @if ($dismissible)
            <button
                type="button"
                class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-current/60 transition hover:bg-white/50 hover:text-current focus:outline-none focus:ring-2 focus:ring-current/30 dark:hover:bg-white/10"
                onclick="this.closest('[data-alert]')?.remove()"
                aria-label="Cerrar alerta"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        @endif
    </div>
</div>
