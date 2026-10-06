<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'ITAM') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center bg-slate-100 px-4 py-10">
            <div class="w-full sm:max-w-md">
                {{-- Brand --}}
                <div class="mb-8 flex flex-col items-center gap-3 text-center">
                    <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}"
                         class="h-14 w-14 rounded-xl object-contain"
                         width="56" height="56">
                    <div>
                        <h1 class="text-xl font-semibold text-slate-900">IT Asset Management</h1>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Sistem Inventaris Aset IT Internal') }}</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white px-6 py-8 shadow-sm">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center text-xs text-slate-400">
                    {{ __('Registrasi mandiri dinonaktifkan. Hubungi Admin IT untuk pembuatan akun.') }}
                </p>
            </div>
        </div>
    </body>
</html>
