@extends('layouts.app')

@section('title', 'Solicitudes de acceso')

@section('content')
<div class="space-y-6">
    <section class="rounded-2xl border border-white/70 bg-white p-5 shadow-sm motion-safe:animate-fade-in-up motion-safe:animate-duration-300 dark:border-slate-800 dark:bg-slate-900 am-motion-safe">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Solicitudes de acceso</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
            @if($accessRequests->total() > 0)
                Tienes <span class="font-semibold text-sky-600 dark:text-sky-400">{{ $accessRequests->total() }}</span> solicitud(es) pendiente(s) de revisión.
            @else
                No hay solicitudes de acceso pendientes.
            @endif
        </p>
    </section>

    <section class="rounded-2xl border border-white/70 bg-white shadow-sm motion-safe:animate-fade-in-up motion-safe:animate-delay-100 dark:border-slate-800 dark:bg-slate-900 am-motion-safe">
        @if($accessRequests->isEmpty())
            <div class="px-6 py-14 text-center">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Todo al día</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">No tienes solicitudes de acceso pendientes por revisar.</p>
            </div>
        @else
            <div class="divide-y divide-slate-200 dark:divide-slate-800">
                @foreach($accessRequests as $accessRequest)
                    <div class="p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $accessRequest->document->title }}</h3>
                                    <span class="text-sm text-slate-500 dark:text-slate-400">#{{ $accessRequest->document->document_number }}</span>
                                </div>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                    Solicitado por <span class="font-medium text-slate-800 dark:text-slate-200">{{ $accessRequest->requester->name }}</span>
                                    el {{ $accessRequest->requested_at?->format('d/m/Y H:i') }}
                                </p>
                                @if($accessRequest->reason)
                                    <p class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                                        {{ $accessRequest->reason }}
                                    </p>
                                @endif
                            </div>

                            <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                                <a href="{{ route('documents.show', $accessRequest->document) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                                    Ver documento
                                </a>

                                <form method="POST" action="{{ route('access-requests.approve', $accessRequest) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:from-emerald-400 hover:to-emerald-500">
                                        Aprobar
                                    </button>
                                </form>

                                <details class="relative">
                                    <summary class="inline-flex h-10 w-full cursor-pointer list-none items-center justify-center rounded-xl border border-rose-300 bg-rose-50 px-4 text-sm font-semibold text-rose-700 shadow-sm transition hover:bg-rose-100 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                                        Rechazar
                                    </summary>
                                    <form method="POST" action="{{ route('access-requests.reject', $accessRequest) }}" class="absolute right-0 z-10 mt-2 w-72 rounded-xl border border-slate-200 bg-white p-3 shadow-xl dark:border-slate-700 dark:bg-slate-800">
                                        @csrf
                                        <label class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-200">Motivo del rechazo</label>
                                        <textarea name="resolution_note" rows="2" required class="block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
                                        <button type="submit" class="mt-2 inline-flex h-9 w-full items-center justify-center rounded-lg bg-rose-600 px-3 text-xs font-semibold text-white shadow-sm transition hover:bg-rose-700">
                                            Confirmar rechazo
                                        </button>
                                    </form>
                                </details>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                {{ $accessRequests->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
