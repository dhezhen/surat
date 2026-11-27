<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' — ' : '' }}{{ \App\Support\Identitas::ambil('nama_aplikasi') }}</title>

        <link rel="icon" type="image/png" href="{{ \App\Services\LogoService::url() }}">
        @include('layouts._tema')
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|source-serif-4:600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-800">
        <div x-data="{ sidebar: false }" x-effect="document.body.classList.toggle('overflow-hidden', sidebar)"
             @keydown.escape.window="sidebar = false" class="min-h-screen">
            @include('layouts.navigation')

            <div class="lg:pl-72 print:pl-0">
                @isset($header)
                    <header class="border-b border-slate-300/70">
                        <div class="{{ isset($lebar) ? trim((string) $lebar) : 'max-w-7xl' }} mx-auto py-5 px-4 sm:px-6 lg:px-8 flex flex-wrap items-center justify-between gap-3 [&_h1]:font-serif [&_h1]:text-2xl">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="{{ isset($lebar) ? trim((string) $lebar) : 'max-w-7xl' }} mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    @if (session('status') && session('status') !== 'password-updated')
                        <div class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any() && ! ($hideErrors ?? false))
                        <div class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                            <p class="font-semibold">Periksa kembali isian berikut:</p>
                            <ul class="mt-1 list-disc ps-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
        @stack('scripts')
    </body>
</html>
