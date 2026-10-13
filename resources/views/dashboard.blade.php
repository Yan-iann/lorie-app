@extends('layouts.app')

@section('title', 'Today · Calorie Tracker')

{{-- Entries added from the + popup go to the day being viewed --}}
@section('add-food-date', $date)

@php
    use Illuminate\Support\Carbon;

    $carbonDate = Carbon::parse($date);
    $isToday = $carbonDate->isToday();
    $prev = $carbonDate->copy()->subDay()->toDateString();
    $next = $carbonDate->copy()->addDay()->toDateString();
    $over = $remaining < 0;

    $ring = $over ? 'text-rose-500' : ($percent >= 80 ? 'text-amber-500' : 'text-brand-600');

    // Circular progress ring geometry
    $radius = 64;
    $circ = 2 * M_PI * $radius;
    $offset = $circ * (1 - min($percent, 100) / 100);

    // Macro calorie split for the distribution bar
    $pCal = $totals['protein_g'] * 4;
    $cCal = $totals['carbs_g'] * 4;
    $fCal = $totals['fat_g'] * 9;
    $mTotal = max(1, $pCal + $cCal + $fCal);

    $macros = [
        ['Protein', $totals['protein_g'], 'bg-sky-50', 'bg-sky-400'],
        ['Carbs', $totals['carbs_g'], 'bg-amber-50', 'bg-amber-400'],
        ['Fat', $totals['fat_g'], 'bg-rose-50', 'bg-rose-400'],
    ];

    // Mascot artwork (drop the file at public/images/mascot.png)
    $hasMascot = file_exists(public_path('images/mascot.png'));
    $mascotSrc = asset('images/mascot.png');
@endphp

@section('content')
    {{-- Greeting header --}}
    <header class="animate-rise mb-5">
        <div class="flex items-center justify-between gap-2">
            @if ($streak > 0)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-orange-100 px-3 py-1.5 text-sm font-semibold text-orange-700">
                    <x-icon name="flame" variant="fill" class="size-4" /> x{{ $streak }} day streak!
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-500">
                    <x-icon name="flame" class="size-4" /> Start your streak
                </span>
            @endif

            <div class="flex items-center gap-2">
                <a href="{{ route('history') }}" class="grid size-11 place-items-center rounded-full text-brand-600 transition hover:bg-slate-200/70 hover:text-brand-700 active:scale-90" aria-label="History">
                    <x-icon name="clock" class="size-7" />
                </a>
                <a href="{{ route('profile') }}" class="grid size-11 place-items-center rounded-full text-brand-600 transition hover:bg-slate-200/70 hover:text-brand-700 active:scale-90" aria-label="Profile">
                    <x-icon name="user" class="size-7" />
                </a>
            </div>
        </div>

        <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-900">{{ $greeting }}, <span class="text-brand-600">{{ $name }}</span>!</h1>
    </header>

    {{-- Mascot coach banner --}}
    <div class="animate-rise mb-6">
        <div class="relative flex items-end gap-2 rounded-2xl bg-brand-600 px-3 pt-3 pb-3">
            @if ($hasMascot)
                <img src="{{ $mascotSrc }}" alt="{{ $mascotName }} the mascot" class="mascot {{ session('success') ? 'mascot-cheer' : '' }} -mt-9 -mb-3 h-36 w-auto shrink-0 self-end drop-shadow-lg sm:h-40">
            @else
                <span class="grid size-20 shrink-0 place-items-center self-center rounded-2xl bg-white/20 text-white">
                    <x-icon name="leaf" class="size-9" />
                </span>
            @endif
            <div class="flex-1 self-center rounded-2xl rounded-bl-md bg-white px-3.5 py-2.5 shadow-sm">
                <p class="text-xs font-bold text-brand-600">{{ $mascotName }}</p>
                <p class="mt-0.5 text-[12px] leading-snug text-slate-600" data-coach-message>{{ $coachMessage }}</p>
            </div>
        </div>
    </div>

    {{-- Date navigation (the one place the date is shown) --}}
    <div class="animate-rise mb-6 flex items-center justify-between gap-2">
        <a href="{{ route('dashboard', ['date' => $prev]) }}"
           class="grid size-11 place-items-center rounded-2xl bg-white text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:text-brand-600 hover:ring-brand-600 active:scale-90" title="Previous day" aria-label="Previous day">
            <x-icon name="chevron-left" class="size-5" />
        </a>
        <div class="text-center">
            <p class="font-semibold text-slate-900">{{ $carbonDate->format('l, M j') }}</p>
            @unless ($isToday)
                <a href="{{ route('dashboard') }}" class="mt-0.5 inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:underline">
                    <x-icon name="rotate" class="size-3.5" /> Jump to today
                </a>
            @else
                <p class="text-xs font-medium text-slate-400">Today</p>
            @endunless
        </div>
        <a href="{{ route('dashboard', ['date' => $next]) }}"
           class="grid size-11 place-items-center rounded-2xl bg-white text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:text-brand-600 hover:ring-brand-600 active:scale-90" title="Next day" aria-label="Next day">
            <x-icon name="chevron-right" class="size-5" />
        </a>
    </div>

    {{-- The day at a glance: calories and water side by side, then macros. Tapping the glass logs water. --}}
    <section class="animate-rise mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="grid grid-cols-2 divide-x divide-slate-100">
            {{-- Calories --}}
            <div class="flex flex-col items-center pr-3 text-center">
                <p class="text-[11px] font-semibold tracking-wide text-slate-400 uppercase">Calories</p>

                <div class="mt-3 grid h-28 place-items-center">
                <div class="relative size-28">
                    <svg class="size-full -rotate-90" viewBox="0 0 160 160" role="img" aria-label="{{ $percent }}% of the calorie goal">
                        <circle cx="80" cy="80" r="{{ $radius }}" fill="none" stroke="currentColor" stroke-width="13" class="text-slate-100" />
                        <circle cx="80" cy="80" r="{{ $radius }}" fill="none" stroke="currentColor" stroke-width="13" stroke-linecap="round"
                                class="{{ $ring }} transition-all duration-700"
                                stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $offset }}" />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <x-icon name="flame" variant="fill" class="size-4 {{ $ring }}" />
                        <span class="text-lg leading-tight font-extrabold text-slate-900 tabular-nums">{{ $percent }}%</span>
                    </div>
                </div>
                </div>

                <p class="mt-3 text-xl font-extrabold tracking-tight text-slate-900 tabular-nums">{{ number_format($totals['calories']) }}</p>
                <p class="text-xs font-medium text-slate-400">of {{ number_format($goal) }} kcal</p>

                <p class="mt-2 inline-flex rounded-full px-2.5 py-1 text-[11px] leading-none font-semibold {{ $over ? 'bg-rose-100 text-rose-600' : 'bg-brand-100 text-brand-700' }}">
                    {{ number_format(abs($remaining)) }} kcal {{ $over ? 'over' : 'left' }}
                </p>
            </div>

            {{-- Water --}}
            <div class="pl-3">
                <x-water-gauge :day="$water" />
            </div>
        </div>

        {{-- Macros --}}
        <div class="mt-5 border-t border-slate-100 pt-5">
            <div class="mb-2.5 flex h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="bg-sky-400" style="width: {{ $pCal / $mTotal * 100 }}%"></div>
                <div class="bg-amber-400" style="width: {{ $cCal / $mTotal * 100 }}%"></div>
                <div class="bg-rose-400" style="width: {{ $fCal / $mTotal * 100 }}%"></div>
            </div>
            <div class="grid grid-cols-3 gap-2 text-center">
                @foreach ($macros as [$label, $grams, $tileClass, $dotClass])
                    <div class="rounded-xl py-2 {{ $tileClass }}">
                        <p class="flex items-center justify-center gap-1 text-sm font-bold text-slate-900 tabular-nums"><span class="size-2 rounded-full {{ $dotClass }}"></span>{{ $grams }}g</p>
                        <p class="text-[11px] font-medium text-slate-500">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- The last seven days --}}
    <x-week-strip :week="$week" />

    {{-- Entries list --}}
    <section class="mb-6">
        <h2 class="mb-3 flex items-center justify-between px-1 text-sm font-bold tracking-wide text-slate-500 uppercase">
            <span>{{ $isToday ? "Today's entries" : 'Entries' }}</span>
            <span class="grid min-w-7 place-items-center rounded-full px-2 py-1 text-xs leading-none font-bold tabular-nums {{ $entries->isEmpty() ? 'bg-slate-200/70 text-slate-500' : 'bg-brand-600 text-white' }}">{{ $entries->count() }}</span>
        </h2>
        <div class="flex flex-col gap-4">
        @forelse ($entries as $entry)
            <x-entry-card :entry="$entry" />
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white/60 px-4 py-12 text-center">
                <span class="mx-auto grid size-16 place-items-center rounded-3xl bg-brand-600 text-white">
                    <x-icon name="bowl" class="size-9" />
                </span>
                <p class="mt-3 text-sm font-medium text-slate-500">Nothing logged {{ $isToday ? 'yet today' : 'for this day' }}.</p>
                <p class="text-xs text-slate-400">Tap the + button to add your first food.</p>
            </div>
        @endforelse
        </div>
    </section>

    <x-food-fact :fact="$foodFact" />
@endsection
