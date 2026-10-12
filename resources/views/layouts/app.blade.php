<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Calorie Tracker')</title>

    {{-- Site icons (source: public/images/icon.png) --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="icon" type="image/png" href="{{ asset('images/icon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icon.png') }}">
    <meta name="theme-color" content="#1f8a52">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full text-slate-800 antialiased">
    <div class="flex min-h-full flex-col pb-24 sm:pb-0">
        <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-6 sm:px-6 sm:py-8">
            @if (session('success'))
                <div class="animate-rise mb-6 flex items-center gap-3 rounded-2xl border border-brand-200 bg-brand-50/80 px-4 py-3 text-sm font-medium text-brand-800 shadow-sm">
                    <span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-500 text-white">
                        <x-icon name="check" class="size-3.5" stroke-width="3" />
                    </span>
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="animate-rise mb-6 flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50/80 px-4 py-3 text-sm font-medium text-rose-800 shadow-sm">
                    <span class="grid size-6 shrink-0 place-items-center rounded-full bg-rose-500 text-white">
                        <x-icon name="alert" class="size-3.5" />
                    </span>
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="animate-rise mb-6 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50/80 px-4 py-3 text-sm text-rose-800 shadow-sm">
                    <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-rose-500 text-white">
                        <x-icon name="alert" class="size-3.5" />
                    </span>
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="mx-auto w-full max-w-3xl px-4 py-10 text-center sm:px-6">
            @auth
                @inject('dailyTip', 'App\Services\DailyTip')
                <p class="text-xs font-semibold tracking-wide text-brand-600 uppercase">Lorie's tip of the day</p>
                <p class="mx-auto mt-1.5 mb-6 max-w-md text-sm leading-relaxed text-slate-500">{{ $dailyTip->today() }}</p>
            @endauth
            <p class="text-[11px] tracking-wide text-slate-300">Lorie · made by Edrian</p>
        </footer>
    </div>

    {{-- Mobile bottom tab bar (signed-in users only) --}}
    @auth
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white sm:hidden">
        <div class="pb-safe mx-auto grid max-w-md grid-cols-3 items-end px-3 pt-2">
            <a href="{{ route('dashboard') }}" data-home
               class="flex flex-col items-center gap-1 py-1.5 text-[11px] font-medium transition {{ request()->routeIs('dashboard') ? 'text-brand-600' : 'text-slate-400' }}">
                <x-icon name="home" class="size-[22px]" />
                Today
            </a>

            <div class="flex justify-center">
                <a href="{{ route('dashboard') }}" data-add-food
                   class="-mt-6 grid size-14 place-items-center rounded-2xl bg-brand-600 text-white shadow-md transition hover:bg-brand-700 active:scale-95">
                    <x-icon name="plus" class="size-7" />
                </a>
            </div>

            <a href="{{ route('history') }}"
               class="flex flex-col items-center gap-1 py-1.5 text-[11px] font-medium transition {{ request()->routeIs('history') ? 'text-brand-600' : 'text-slate-400' }}">
                <x-icon name="chart" class="size-[22px]" />
                History
            </a>
        </div>
    </nav>
    @endauth

    {{-- Loading overlay (shown during AI analysis) --}}
    <div id="loading-overlay" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-6 backdrop-blur-sm">
        <div class="flex flex-col items-center gap-4 rounded-3xl bg-white px-10 py-8 text-center shadow-2xl">
            <span class="size-10 animate-spin rounded-full border-4 border-slate-200 border-t-brand-500"></span>
            <p id="loading-text" class="text-sm font-semibold text-slate-700">Analyzing…</p>
            <p class="text-xs text-slate-400">This can take a few seconds.</p>
        </div>
    </div>
</body>
</html>
