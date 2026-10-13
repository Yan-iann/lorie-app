@props(['week'])

@php
    use App\Support\WeekSummary;

    $logged = $week->loggedDays();
@endphp

{{--
    The last seven days at a glance, in the same white card as the other
    sections: a bar of calories per day against a green goal line, and a dot
    for each day something was logged. Each day links to itself.
--}}
<section class="animate-rise mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex items-baseline justify-between gap-3">
        <h2 class="text-base font-bold text-slate-900">This week</h2>
        <p class="text-xs font-medium text-slate-400">
            @if ($logged > 0)
                {{ $logged }} of 7 days · avg {{ number_format($week->averageCalories()) }} kcal
            @else
                Nothing logged yet
            @endif
        </p>
    </div>

    <div class="relative mt-5 h-24">
        {{-- Goal line --}}
        <div class="pointer-events-none absolute inset-x-0 border-t border-dashed border-brand-600" style="bottom: {{ $week->goalHeight() }}%">
            <span class="absolute -top-2 right-0 bg-white pl-1.5 text-[10px] leading-none font-semibold text-brand-600">goal</span>
        </div>

        <div class="relative grid h-full grid-cols-7 items-end gap-2 pr-8">
            @foreach ($week->days as $day)
                <a href="{{ route('dashboard', ['date' => $day['date']]) }}"
                   title="{{ WeekSummary::describe($day['date']) }}: {{ $day['logged'] ? number_format($day['calories']).' kcal' : 'nothing logged' }}"
                   aria-label="{{ WeekSummary::describe($day['date']) }}: {{ $day['logged'] ? number_format($day['calories']).' kcal' : 'nothing logged' }}"
                   class="group flex h-full items-end justify-center">
                    {{-- Brand green when on or under goal, red when over, a faint line when nothing was logged --}}
                    <span class="w-full max-w-7 rounded-t-md transition group-hover:opacity-80 {{ ! $day['logged'] ? 'bg-slate-200' : ($day['over'] ? 'bg-rose-500' : 'bg-brand-600') }}"
                          style="height: {{ $day['logged'] ? max(4, $week->barHeight($day['calories'])) : 3 }}%"></span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Day names, with a dot under each day that has something logged --}}
    <div class="mt-2 grid grid-cols-7 gap-2 pr-8 text-center">
        @foreach ($week->days as $day)
            <div class="flex flex-col items-center gap-1.5">
                <span class="rounded-full px-1.5 py-0.5 text-[10px] leading-none font-semibold {{ $day['isViewed'] ? 'bg-brand-600 text-white' : ($day['isToday'] ? 'text-brand-600' : 'text-slate-400') }}">{{ $day['label'] }}</span>
                <span class="size-1.5 rounded-full {{ $day['logged'] ? 'bg-brand-600' : 'bg-slate-200' }}"></span>
            </div>
        @endforeach
    </div>
</section>
