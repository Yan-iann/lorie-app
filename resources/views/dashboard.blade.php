@extends('layouts.app')

@section('title', 'Today — Calorie Tracker')

@php
    use Illuminate\Support\Carbon;

    $carbonDate = Carbon::parse($date);
    $isToday = $carbonDate->isToday();
    $prev = $carbonDate->copy()->subDay()->toDateString();
    $next = $carbonDate->copy()->addDay()->toDateString();
    $over = $remaining < 0;

    $ring = $percent >= 100 ? 'text-rose-500' : ($percent >= 80 ? 'text-amber-500' : 'text-brand-500');

    // Circular progress ring geometry
    $radius = 64;
    $circ = 2 * M_PI * $radius;
    $offset = $circ * (1 - min($percent, 100) / 100);

    // Macro calorie split for the distribution bar
    $pCal = $totals['protein_g'] * 4;
    $cCal = $totals['carbs_g'] * 4;
    $fCal = $totals['fat_g'] * 9;
    $mTotal = max(1, $pCal + $cCal + $fCal);
@endphp

@section('content')
    {{-- Date navigation --}}
    <div class="animate-rise mb-6 flex items-center justify-between gap-2">
        <a href="{{ route('dashboard', ['date' => $prev]) }}"
           class="grid size-10 place-items-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-brand-300 hover:text-brand-600" title="Previous day">&larr;</a>
        <div class="text-center">
            <p class="font-semibold text-slate-900">{{ $carbonDate->format('l, M j') }}</p>
            @unless ($isToday)
                <a href="{{ route('dashboard') }}" class="text-xs font-medium text-brand-600 hover:underline">↩ Jump to today</a>
            @else
                <p class="text-xs font-medium text-slate-400">Today</p>
            @endunless
        </div>
        <a href="{{ route('dashboard', ['date' => $next]) }}"
           class="grid size-10 place-items-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-brand-300 hover:text-brand-600" title="Next day">&rarr;</a>
    </div>

    {{-- Hero progress card --}}
    <section class="animate-rise mb-6 overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-lg shadow-slate-200/50">
        <div class="grid gap-6 p-6 sm:grid-cols-[auto_1fr] sm:items-center sm:gap-8 sm:p-8">
            {{-- Ring --}}
            <div class="relative mx-auto size-44">
                <svg class="size-full -rotate-90" viewBox="0 0 160 160">
                    <circle cx="80" cy="80" r="{{ $radius }}" fill="none" stroke="currentColor" stroke-width="14" class="text-slate-100" />
                    <circle cx="80" cy="80" r="{{ $radius }}" fill="none" stroke="currentColor" stroke-width="14" stroke-linecap="round"
                            class="{{ $ring }} transition-all duration-700"
                            stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $offset }}" />
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-4xl font-extrabold tracking-tight text-slate-900">{{ number_format($totals['calories']) }}</span>
                    <span class="text-xs font-medium text-slate-400">of {{ number_format($goal) }} kcal</span>
                    <span class="mt-1 rounded-full px-2 py-0.5 text-[11px] font-bold {{ $over ? 'bg-rose-100 text-rose-600' : 'bg-brand-100 text-brand-700' }}">{{ $percent }}%</span>
                </div>
            </div>

            {{-- Stats --}}
            <div class="flex flex-col gap-5">
                <div class="flex items-baseline justify-between rounded-2xl bg-slate-50 px-5 py-4">
                    <span class="text-sm font-medium text-slate-500">{{ $over ? 'Over budget by' : 'Still remaining' }}</span>
                    <span class="text-2xl font-bold {{ $over ? 'text-rose-600' : 'text-brand-600' }}">
                        {{ number_format(abs($remaining)) }}<span class="ml-1 text-sm font-medium text-slate-400">kcal</span>
                    </span>
                </div>

                {{-- Macro distribution bar --}}
                <div>
                    <div class="mb-2 flex h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                        <div class="bg-sky-400" style="width: {{ $pCal / $mTotal * 100 }}%"></div>
                        <div class="bg-amber-400" style="width: {{ $cCal / $mTotal * 100 }}%"></div>
                        <div class="bg-rose-400" style="width: {{ $fCal / $mTotal * 100 }}%"></div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-xl bg-sky-50 py-2">
                            <p class="flex items-center justify-center gap-1 text-sm font-bold text-slate-900"><span class="size-2 rounded-full bg-sky-400"></span>{{ $totals['protein_g'] }}g</p>
                            <p class="text-[11px] font-medium text-slate-500">Protein</p>
                        </div>
                        <div class="rounded-xl bg-amber-50 py-2">
                            <p class="flex items-center justify-center gap-1 text-sm font-bold text-slate-900"><span class="size-2 rounded-full bg-amber-400"></span>{{ $totals['carbs_g'] }}g</p>
                            <p class="text-[11px] font-medium text-slate-500">Carbs</p>
                        </div>
                        <div class="rounded-xl bg-rose-50 py-2">
                            <p class="flex items-center justify-center gap-1 text-sm font-bold text-slate-900"><span class="size-2 rounded-full bg-rose-400"></span>{{ $totals['fat_g'] }}g</p>
                            <p class="text-[11px] font-medium text-slate-500">Fat</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Add food form --}}
    <section class="animate-rise mb-6 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm">
        <h2 class="flex items-center gap-2 text-base font-bold text-slate-900">
            <span class="grid size-7 place-items-center rounded-lg bg-brand-100 text-brand-600">+</span>
            Add food
        </h2>
        <p class="mb-4 mt-1 text-sm text-slate-500">Describe it in plain language — AI breaks it down and looks up real nutrition data.</p>
        <form method="POST" action="{{ route('entries.store') }}" class="flex flex-col gap-3 sm:flex-row" data-loading="Analyzing your food…">
            @csrf
            <input type="hidden" name="consumed_on" value="{{ $date }}">
            <input type="text" name="description" value="{{ old('description') }}" required autofocus
                   placeholder="e.g. chicken adobo with rice"
                   class="flex-1 rounded-xl border border-slate-300 bg-slate-50/50 px-4 py-3 text-sm shadow-sm outline-none transition focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-500/15">
            <button type="submit"
                    class="rounded-xl bg-gradient-to-br from-brand-500 to-teal-500 px-6 py-3 text-sm font-semibold text-white shadow-md shadow-brand-500/25 transition hover:shadow-lg hover:shadow-brand-500/30 active:scale-95 focus:ring-4 focus:ring-brand-500/30">
                Estimate &amp; add
            </button>
        </form>

        {{-- Divider --}}
        <div class="my-4 flex items-center gap-3 text-xs font-medium text-slate-400">
            <span class="h-px flex-1 bg-slate-200"></span>
            OR
            <span class="h-px flex-1 bg-slate-200"></span>
        </div>

        {{-- Photo capture --}}
        <form method="POST" action="{{ route('entries.photo') }}" enctype="multipart/form-data" data-loading="Looking at your photo…">
            @csrf
            <input type="hidden" name="consumed_on" value="{{ $date }}">
            <input type="file" name="photo" id="photo-input" accept="image/*" capture="environment" class="sr-only" data-autosubmit>
            <label for="photo-input"
                   class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50/50 px-4 py-4 text-sm font-semibold text-slate-600 transition hover:border-brand-400 hover:bg-brand-50/40 hover:text-brand-700">
                <span class="text-lg">📷</span>
                Take or upload a food photo
            </label>
        </form>
        <p class="mt-2 text-center text-xs text-slate-400">AI identifies the food, then looks up real nutrition data.</p>
    </section>

    {{-- Entries list --}}
    <section class="mb-6">
        <h2 class="mb-3 flex items-center justify-between px-1 text-sm font-bold tracking-wide text-slate-500 uppercase">
            <span>Today's entries</span>
            <span class="rounded-full bg-slate-200/70 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $entries->count() }}</span>
        </h2>
        @forelse ($entries as $entry)
            @php
                $sourceBadge = match ($entry->source) {
                    'usda' => ['USDA data', 'bg-brand-100 text-brand-700'],
                    'hybrid' => ['USDA + AI', 'bg-sky-100 text-sky-700'],
                    default => ['AI estimate', 'bg-amber-100 text-amber-700'],
                };
                $confColor = match ($entry->confidence) {
                    'high' => 'text-brand-600',
                    'low' => 'text-rose-600',
                    default => 'text-amber-600',
                };
            @endphp
            <div class="animate-rise group mb-2.5 rounded-2xl border border-slate-200/80 bg-white px-4 py-3.5 shadow-sm transition hover:border-slate-300 hover:shadow-md">
                <div class="flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-semibold text-slate-900">{{ $entry->name }}</p>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $sourceBadge[1] }}">{{ $sourceBadge[0] }}</span>
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500">
                            @if ($entry->quantity)<span class="text-slate-400">{{ $entry->quantity }}</span> · @endif
                            P {{ $entry->protein_g }}g · C {{ $entry->carbs_g }}g · F {{ $entry->fat_g }}g
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="font-bold text-slate-900">{{ number_format($entry->calories) }}</p>
                        <p class="text-[10px] font-medium text-slate-400">kcal</p>
                    </div>
                    <form method="POST" action="{{ route('entries.destroy', $entry) }}"
                          onsubmit="return confirm('Remove this entry?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="grid size-8 place-items-center rounded-lg text-slate-300 transition hover:bg-rose-50 hover:text-rose-600" title="Remove">&times;</button>
                    </form>
                </div>

                @if ($entry->verification_note)
                    <p class="mt-2 flex items-start gap-1.5 rounded-lg bg-slate-50 px-2.5 py-1.5 text-xs {{ $confColor }}">
                        <span class="font-semibold whitespace-nowrap">AI check · {{ $entry->confidence }}:</span>
                        <span class="text-slate-600">{{ $entry->verification_note }}</span>
                    </p>
                @endif

                @if (! empty($entry->breakdown))
                    <details class="mt-2">
                        <summary class="cursor-pointer text-xs font-medium text-slate-400 transition hover:text-brand-600">Ingredient breakdown</summary>
                        <ul class="mt-2 space-y-1 border-t border-slate-100 pt-2 text-xs text-slate-500">
                            @foreach ($entry->breakdown as $item)
                                <li class="flex justify-between gap-2">
                                    <span class="truncate">
                                        {{ $item['name'] }} <span class="text-slate-400">({{ $item['grams'] }}g)</span>
                                        <span class="text-[10px] font-medium {{ ($item['source'] ?? '') === 'usda' ? 'text-brand-500' : 'text-amber-500' }}">[{{ $item['source'] ?? 'ai' }}]</span>
                                    </span>
                                    <span class="shrink-0 font-semibold text-slate-600">{{ number_format($item['calories']) }} kcal</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white/60 px-4 py-12 text-center">
                <p class="text-3xl">🍽️</p>
                <p class="mt-2 text-sm font-medium text-slate-500">Nothing logged {{ $isToday ? 'yet today' : 'for this day' }}.</p>
                <p class="text-xs text-slate-400">Add your first food above to get started.</p>
            </div>
        @endforelse
    </section>

    {{-- Goal setting --}}
    <section class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm">
        <h2 class="flex items-center gap-2 text-base font-bold text-slate-900">
            <span class="grid size-7 place-items-center rounded-lg bg-slate-100 text-slate-500">🎯</span>
            Daily calorie goal
        </h2>
        <form method="POST" action="{{ route('goal.update') }}" class="mt-4 flex items-center gap-3">
            @csrf
            @method('PUT')
            <div class="relative">
                <input type="number" name="daily_goal" value="{{ old('daily_goal', $goal) }}" min="500" max="10000" step="50" required
                       class="w-36 rounded-xl border border-slate-300 bg-slate-50/50 py-2.5 pl-4 pr-14 text-sm font-semibold shadow-sm outline-none transition focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-500/15">
                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">kcal</span>
            </div>
            <button type="submit"
                    class="ml-auto rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-brand-300 hover:text-brand-600 active:scale-95">
                Save
            </button>
        </form>
    </section>
@endsection
