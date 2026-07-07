<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Calorie Tracker')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full text-slate-800 antialiased">
    <div class="flex min-h-full flex-col pb-24 sm:pb-0">
        <header class="sticky top-0 z-30 border-b border-slate-200/60 bg-white/70 backdrop-blur-xl">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3 sm:px-6">
                <a href="{{ route('dashboard') }}" class="group flex items-center gap-2.5">
                    <span class="grid size-9 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-teal-400 text-white shadow-sm shadow-brand-500/30 transition group-hover:scale-105">
                        <x-icon name="leaf" class="size-5" />
                    </span>
                    <span class="text-[15px] font-bold tracking-tight text-slate-900">Calorie<span class="text-brand-600">Tracker</span></span>
                </a>

                {{-- Desktop nav --}}
                <nav class="hidden items-center gap-1 rounded-full bg-slate-100/80 p-1 text-sm font-medium sm:flex">
                    <a href="{{ route('dashboard') }}"
                       class="rounded-full px-4 py-1.5 transition {{ request()->routeIs('dashboard') ? 'bg-white text-brand-600 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">
                        Today
                    </a>
                    <a href="{{ route('history') }}"
                       class="rounded-full px-4 py-1.5 transition {{ request()->routeIs('history') ? 'bg-white text-brand-600 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">
                        History
                    </a>
                </nav>
            </div>
        </header>

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

        <footer class="mx-auto w-full max-w-3xl px-4 py-8 text-center text-xs text-slate-400 sm:px-6">
            Calorie estimates are AI-verified approximations — a helpful guide, not medical advice.
        </footer>
    </div>

    {{-- Mobile bottom tab bar --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/70 bg-white/85 backdrop-blur-xl sm:hidden">
        <div class="pb-safe mx-auto grid max-w-md grid-cols-3 items-end px-3 pt-2">
            <a href="{{ route('dashboard') }}"
               class="flex flex-col items-center gap-1 py-1.5 text-[11px] font-medium transition {{ request()->routeIs('dashboard') ? 'text-brand-600' : 'text-slate-400' }}">
                <x-icon name="home" class="size-[22px]" />
                Today
            </a>

            <div class="flex justify-center">
                <a href="{{ route('dashboard') }}#add-food"
                   class="-mt-6 grid size-14 place-items-center rounded-2xl bg-gradient-to-br from-brand-500 to-teal-500 text-white shadow-lg shadow-brand-500/40 transition active:scale-95">
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
