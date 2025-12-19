{{-- resources/views/layouts/guest.blade.php --}}
@php
    $locale = app()->getLocale();
    $dir = $locale === 'ar' ? 'rtl' : 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}" class="h-full antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', config('app.name', 'Ghanem ERP'))</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        * { font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif !important; }
        body {
            min-height: 100vh;
            background-color: #f8fafc;
            padding: 1.5rem 1rem;
            overflow-x: hidden;
        }
        img, svg { max-width: 100%; height: auto; object-fit: contain; }
        .erp-card { width: 100%; }
    </style>

    @livewireStyles
</head>
<body class="h-full flex items-center justify-center">

<div class="w-full max-w-lg px-4">
    {{-- Language Switcher --}}
    <div class="flex justify-center gap-2 mb-4 flex-wrap">
        <a href="?lang=ar" class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $locale === 'ar' ? 'bg-emerald-500 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }} transition-all">
            العربية
        </a>
        <a href="?lang=en" class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $locale === 'en' ? 'bg-emerald-500 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }} transition-all">
            English
        </a>
    </div>

    @php
        $appName = config('app.name', 'Ghanem ERP');
    @endphp
    <div class="mb-6 flex justify-center">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-3">
            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 text-white font-bold text-2xl shadow-lg shadow-emerald-500/40">
                {{ strtoupper(mb_substr($appName, 0, 1)) }}
            </span>
            <span class="text-lg font-semibold text-slate-800">
                {{ $appName }}
            </span>
        </a>
    </div>

    <div class="erp-card p-6">
        {{ $slot ?? '' }}
        @yield('content')
    </div>

    <p class="mt-4 text-center text-xs text-slate-500">
        &copy; {{ date('Y') }} {{ $appName }}
    </p>
</div>

@livewireScripts
@stack('scripts')
</body>
</html>
