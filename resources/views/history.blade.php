@extends('layouts.app')

@section('title', 'History · Calorie Tracker')

@section('content')
    <div class="animate-rise mb-6 flex items-center gap-2.5">
        <span class="grid size-9 place-items-center rounded-xl bg-slate-100 text-slate-500">
            <x-icon name="calendar" class="size-5" />
        </span>
        <h1 class="text-xl font-bold tracking-tight text-slate-900">History</h1>
    </div>

    @forelse ($days as $day)
        @php
            $date = \Illuminate\Support\Carbon::parse($day->consumed_on);
            $total = (int) $day->total_calories;
            $over = $total > $goal;
            $percent = $goal > 0 ? min(100, (int) round($total / $goal * 100)) : 0;
            $barColor = $over ? 'bg-rose-500' : ($percent >= 80 ? 'bg-amber-500' : 'bg-brand-500');
        @endphp
        <a href="{{ route('dashboard', ['date' => $date->toDateString()]) }}"
           class="animate-rise group mb-2.5 block rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm transition hover:border-brand-300 hover:shadow-md active:scale-[0.99]">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-semibold text-slate-900">
                        {{ $date->isToday() ? 'Today' : ($date->isYesterday() ? 'Yesterday' : $date->format('l, M j')) }}
                        <span class="ml-1 text-xs font-normal text-slate-400">{{ $date->format('Y') }}</span>
                    </p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $day->entries_count }} {{ \Illuminate\Support\Str::plural('item', $day->entries_count) }} ·
                        P {{ round($day->total_protein, 1) }}g · C {{ round($day->total_carbs, 1) }}g · F {{ round($day->total_fat, 1) }}g
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <div class="text-right">
                        <p class="font-bold {{ $over ? 'text-rose-600' : 'text-slate-900' }}">{{ number_format($total) }}</p>
                        <p class="text-[10px] font-medium text-slate-400">/ {{ number_format($goal) }} kcal</p>
                    </div>
                    <x-icon name="chevron-right" class="size-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand-500" />
                </div>
            </div>
            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full {{ $barColor }} transition-all" style="width: {{ $percent }}%"></div>
            </div>
        </a>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white/60 px-4 py-14 text-center">
            <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-slate-100 text-slate-400">
                <x-icon name="inbox" class="size-7" />
            </span>
            <p class="mt-3 text-sm font-medium text-slate-500">No history yet.</p>
            <p class="text-xs text-slate-400">Start logging on the <a href="{{ route('dashboard') }}" class="font-medium text-brand-600 hover:underline">Today</a> page.</p>
        </div>
    @endforelse

    @if ($days->hasPages())
        <div class="mt-6">
            {{ $days->links() }}
        </div>
    @endif
@endsection
