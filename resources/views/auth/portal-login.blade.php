<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} - Acceso al Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-900">
    <div class="mx-auto flex min-h-screen max-w-md items-center px-4">
        <div class="w-full rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Acceso al Portal</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Ingresa tu número de recibido y correo para solicitar OTP.
            </p>

            <form method="POST" action="{{ route('portal.auth.request-otp') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="receipt_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Número de recibido</label>
                    <input id="receipt_number" name="receipt_number" type="text" value="{{ old('receipt_number') }}" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    @error('receipt_number')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Correo</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="w-full rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Solicitar OTP</button>
            </form>
        </div>
    </div>
</body>
</html>

