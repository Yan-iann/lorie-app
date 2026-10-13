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

    {{-- Self-hosted web font (preloads + @font-face), then the app's CSS and JS --}}
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full text-slate-800 antialiased">
    {{-- Progress bar shown while navigating to another page --}}
    <div id="nav-progress" class="nav-progress" aria-hidden="true"></div>

    {{-- Notifications: float over the page, then dismiss themselves (see app.js) --}}
    <div class="pointer-events-none fixed inset-x-0 top-0 z-50 flex flex-col items-center gap-2 px-4 pt-[max(env(safe-area-inset-top),0.75rem)]">
        @if (session('success'))
            <x-toast>{{ session('success') }}</x-toast>
        @endif

        @if (session('error'))
            <x-toast type="error">{{ session('error') }}</x-toast>
        @endif

        @if ($errors->any())
            <x-toast type="error">
                @if ($errors->count() === 1)
                    {{ $errors->first() }}
                @else
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
            </x-toast>
        @endif
    </div>

    <div class="flex min-h-full flex-col pb-24 sm:pb-0">
        <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-6 sm:px-6 sm:py-8">
            {{-- Everything in here is swapped for the skeleton while the next page loads --}}
            <div id="page-content">
                @yield('content')
            </div>

            <x-page-skeleton />
        </main>

        <footer class="mx-auto w-full max-w-3xl px-4 py-10 text-center sm:px-6">
            <p class="text-[11px] tracking-wide text-slate-300">Lorie · made by Edrian</p>
        </footer>
    </div>

    {{-- Mobile bottom tab bar and the + button (signed-in users who have finished onboarding) --}}
    @if (auth()->user()?->hasCompletedProfile())
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white sm:hidden">
        <div class="pb-safe mx-auto grid max-w-md grid-cols-3 items-center px-6 pt-2">
            @php
                $tabClass = 'flex min-h-12 flex-col items-center justify-center gap-1 rounded-xl text-[11px] font-semibold transition active:scale-90';
                $onToday = request()->routeIs('dashboard');
                $onHistory = request()->routeIs('history');
            @endphp

            <a href="{{ route('dashboard') }}" data-home @if ($onToday) aria-current="page" @endif
               class="{{ $tabClass }} {{ $onToday ? 'text-brand-600' : 'text-slate-500 hover:text-slate-700' }}">
                <x-icon name="home" :variant="$onToday ? 'fill' : null" class="size-6" />
                Today
            </a>

            <div class="flex justify-center">
                <button type="button" data-add-food-toggle aria-label="Add food" aria-expanded="false" aria-controls="add-food-panel"
                        class="group -mt-6 grid size-14 cursor-pointer place-items-center rounded-2xl bg-brand-600 text-white shadow-lg shadow-brand-600/30 ring-4 ring-white transition hover:bg-brand-700 active:scale-95 aria-expanded:bg-slate-500 aria-expanded:shadow-slate-500/30 aria-expanded:hover:bg-slate-600">
                    <x-icon name="plus" class="size-6 transition-transform duration-500 ease-spring group-aria-expanded:rotate-[135deg]" />
                </button>
            </div>

            <a href="{{ route('history') }}" @if ($onHistory) aria-current="page" @endif
               class="{{ $tabClass }} {{ $onHistory ? 'text-brand-600' : 'text-slate-500 hover:text-slate-700' }}">
                <x-icon name="chart" :variant="$onHistory ? 'fill' : null" class="size-6" />
                History
            </a>
        </div>
    </nav>

    {{-- Desktop has no tab bar, so the + lives in the corner there --}}
    <button type="button" data-add-food-toggle aria-label="Add food" aria-expanded="false" aria-controls="add-food-panel"
            class="group fixed right-6 bottom-6 z-30 hidden size-14 cursor-pointer place-items-center rounded-2xl bg-brand-600 text-white shadow-lg shadow-brand-600/30 transition hover:bg-brand-700 active:scale-95 aria-expanded:bg-slate-500 aria-expanded:shadow-slate-500/30 aria-expanded:hover:bg-slate-600 sm:grid">
        <x-icon name="plus" class="size-6 transition-transform duration-500 ease-spring group-aria-expanded:rotate-[135deg]" />
    </button>

    <x-add-food />
    @endif

    {{-- Loading overlay (shown during AI analysis) --}}
    <div id="loading-overlay" class="animate-fade-in fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-6 backdrop-blur-sm">
        <div class="animate-pop-in flex flex-col items-center gap-4 rounded-3xl bg-white px-10 py-8 text-center shadow-2xl">
            <span class="size-10 animate-spin rounded-full border-4 border-slate-200 border-t-brand-500"></span>
            <p id="loading-text" class="text-sm font-semibold text-slate-700">Analyzing…</p>
            <p class="text-xs text-slate-400">This can take a few seconds.</p>
        </div>
    </div>
</body>
</html>
