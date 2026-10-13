@props(['day'])

@php
    use App\Support\WaterCalculator;
    use App\Support\WaterSchedule;

    $schedule = $day->schedule;
    $status = $schedule?->status();
    $next = $schedule?->nextDrinkAt();

    $tone = match ($status) {
        WaterSchedule::BEHIND => 'bg-amber-50 text-amber-800',
        WaterSchedule::DONE => 'bg-brand-100 text-brand-800',
        default => 'bg-sky-50 text-sky-800',
    };
@endphp

{{--
    One line saying when to drink next, worked out from today's pace. Only
    today has a schedule; on other days this renders an empty placeholder so
    app.js still has something to swap (data-water-part) after a drink.
--}}
@if ($schedule === null)
    <div data-water-part="schedule" hidden></div>
@else
    <button type="button" data-water-part="schedule" data-add-food-open="water"
            class="mt-5 flex w-full cursor-pointer items-center gap-3 rounded-xl px-3.5 py-2.5 text-left text-xs leading-snug transition active:scale-[0.98] {{ $tone }}">
        <x-icon :name="$status === WaterSchedule::DONE ? 'check' : 'clock'" class="size-4 shrink-0" />

        <span class="min-w-0 flex-1">
            @switch($status)
                @case(WaterSchedule::BEFORE)
                    Your water day starts at <strong class="font-bold">{{ $schedule->start->format('g:i A') }}</strong>.
                    @break
                @case(WaterSchedule::ON_TRACK)
                    You're on pace. Next drink by <strong class="font-bold">{{ $next->format('g:i A') }}</strong>
                    <span class="opacity-70">(in {{ WaterSchedule::duration((int) ceil($schedule->now->diffInMinutes($next))) }})</span>.
                    @break
                @case(WaterSchedule::BEHIND)
                    <strong class="font-bold">Time to drink.</strong>
                    @if ($schedule->behindMl() > 0)
                        You're {{ WaterCalculator::format($schedule->behindMl()) }} behind today's pace.
                    @else
                        Your next drink is due.
                    @endif
                    @break
                @case(WaterSchedule::DONE)
                    <strong class="font-bold">Water goal reached.</strong> Nothing more scheduled today.
                    @break
                @case(WaterSchedule::ENDED)
                    Today's water hours ended at {{ $schedule->end->format('g:i A') }},
                    {{ WaterCalculator::format($schedule->remainingMl()) }} short of the goal.
                    @break
            @endswitch
        </span>
    </button>
@endif
