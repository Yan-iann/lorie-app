@props(['day'])

@php
    use App\Support\WaterCalculator;

    // Once the water is past the middle, the icon sits on blue instead of white.
    $submerged = $day->level() >= 0.5;
@endphp

{{--
    The water half of the day's summary, laid out to mirror the calorie half
    beside it: a circle of the same size with an icon and percentage inside,
    then the total, the goal and a pill. The whole thing is a button that opens
    the + popup on its water view (data-add-food-open, see app.js). Redrawn by
    the server after each drink and swapped in by app.js, which finds it by
    data-water-part and reads the old level from data-level.
--}}
<button type="button" data-water-part="gauge" data-level="{{ $day->level() }}" data-add-food-open="water"
        aria-label="Log water. {{ WaterCalculator::litres($day->totalMl()) }} of {{ WaterCalculator::litres($day->goalMl) }} so far."
        class="group flex w-full cursor-pointer flex-col items-center rounded-2xl text-center outline-sky-500 transition focus-visible:outline-2 focus-visible:outline-offset-4 active:scale-[0.97]">
    <span class="text-[11px] font-semibold tracking-wide text-slate-400 uppercase">Water</span>

    <span class="relative mt-3 block size-28">
        <x-water-glass :level="$day->level()" class="size-full" />

        <span class="absolute inset-0 flex flex-col items-center justify-center">
            <x-icon name="drop" variant="fill" class="size-4 {{ $submerged ? 'text-sky-800' : 'text-sky-500' }}" />
            <span class="text-lg leading-tight font-extrabold text-slate-900 tabular-nums">{{ $day->percent() }}%</span>
        </span>

        {{-- Shows the gauge can be tapped --}}
        <span class="absolute -right-0.5 -bottom-0.5 grid size-6 place-items-center rounded-full bg-sky-500 text-white ring-2 ring-white transition group-hover:bg-sky-600">
            <x-icon name="plus" class="size-3" />
        </span>
    </span>

    <span class="mt-3 block text-xl font-extrabold tracking-tight text-slate-900 tabular-nums">{{ WaterCalculator::litres($day->totalMl()) }}</span>
    <span class="block text-xs font-medium text-slate-400">of {{ WaterCalculator::litres($day->goalMl) }}</span>

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
