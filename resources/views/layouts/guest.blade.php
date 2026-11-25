<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php $identitas = \App\Support\Identitas::semua(); @endphp
        <title>{{ isset($title) ? $title.' — ' : '' }}{{ $identitas['nama_aplikasi'] }}</title>

        <link rel="icon" type="image/png" href="{{ \App\Services\LogoService::url() }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|source-serif-4:600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center py-6 px-4">
            <div class="text-center">
                <img src="{{ \App\Services\LogoService::url() }}" alt="Logo {{ $identitas['nama_unit'] }}" class="mx-auto mb-3 h-20 w-20 rounded-2xl bg-white object-contain p-2">
                <div class="break-words font-serif text-4xl font-bold text-indigo-900">{{ $identitas['nama_aplikasi'] }}</div>
                <div class="mt-1 text-sm text-slate-600">Sistem Informasi Persuratan &amp; Arsip<br>{{ $identitas['nama_unit'] }}</div>
            </div>

            <div class="w-full {{ $lebar ?? 'sm:max-w-md' }} mt-6 px-6 py-5 nm-raised overflow-hidden rounded-2xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
