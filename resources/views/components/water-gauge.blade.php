@props(['day'])

@php
    use App\Support\WaterCalculator;
@endphp

{{--
    The water half of the day's summary: the glass and how much has been drunk.
    The whole thing is a button that opens the + popup on its water view
    (data-add-food-open, see app.js). Redrawn by the server after each drink
    and swapped in by app.js, which finds it by data-water-part and reads the
    old level from data-level.
--}}
<button type="button" data-water-part="gauge" data-level="{{ $day->level() }}" data-add-food-open="water"
        aria-label="Log water. {{ WaterCalculator::litres($day->totalMl()) }} of {{ WaterCalculator::litres($day->goalMl) }} so far."
        class="group flex w-full cursor-pointer flex-col items-center rounded-2xl text-center outline-sky-500 transition focus-visible:outline-2 focus-visible:outline-offset-4 active:scale-[0.97]">
    <span class="text-[11px] font-semibold tracking-wide text-slate-400 uppercase">Water</span>

    <span class="relative mt-3 block">
        <x-water-glass :level="$day->level()" class="h-28 w-auto" />

        {{-- Shows the glass can be tapped --}}
        <span class="absolute -right-3 -bottom-1 grid size-7 place-items-center rounded-full bg-sky-500 text-white ring-2 ring-white transition group-hover:bg-sky-600">
            <x-icon name="plus" class="size-3.5" />
        </span>
    </span>

    <span class="mt-3 block text-xl font-extrabold tracking-tight text-slate-900 tabular-nums">{{ WaterCalculator::litres($day->totalMl()) }}</span>
    <span class="block text-xs font-medium text-slate-400">of {{ WaterCalculator::litres($day->goalMl) }} · {{ $day->percent() }}%</span>

    @if ($day->goalReached())
        <span class="mt-2 inline-flex items-center gap-1 rounded-full bg-brand-100 px-2.5 py-1 text-[11px] leading-none font-semibold text-brand-700">
            <x-icon name="check" class="size-3" /> Goal reached
        </span>
    @else
        <span class="mt-2 inline-flex rounded-full bg-sky-50 px-2.5 py-1 text-[11px] leading-none font-semibold text-sky-700">
            {{ WaterCalculator::format($day->remainingMl()) }} to go
        </span>
    @endif
</button>
