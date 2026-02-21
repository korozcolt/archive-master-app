@extends('layouts.app')

@section('title', 'Recibido '.$receipt->receipt_number)

@section('content')
<div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                Recibido {{ $receipt->receipt_number }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Emitido el {{ $receipt->issued_at->format('d/m/Y H:i') }}
            </p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Documento</p>
                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $receipt->document->title }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Número documento</p>
                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $receipt->document->document_number }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Receptor</p>
                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $receipt->recipient_name }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Correo receptor</p>
                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $receipt->recipient_email }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Teléfono receptor</p>
                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $receipt->recipient_phone ?: 'N/A' }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Emitido por</p>
                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $receipt->issuer->name }}</p>
            </div>
        </div>
        <div class="px-6 pb-6">
            <a href="{{ route('receipts.download', $receipt) }}" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                Descargar PDF
            </a>
        </div>
    </div>
</div>
@endsection

