@props(['week'])

@php
    use App\Support\WeekSummary;

    $logged = $week->loggedDays();
@endphp

{{--
    The last seven days at a glance, in the same white card as the other
    sections. Each day has two bars — calories (green) and water (blue) — each
    drawn as a share of its own goal, so one green goal line serves both. A
    dot marks each day food was logged. Each day links to itself.
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

    {{-- Legend --}}
    <div class="mt-2 flex items-center gap-4 text-[11px] font-medium text-slate-500">
        <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-brand-600"></span>Calories</span>
        <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-sky-400"></span>Water</span>
    </div>

    <div class="relative mt-5 h-24">
        {{-- Goal line --}}
        <div class="pointer-events-none absolute inset-x-0 border-t border-dashed border-brand-600" style="bottom: {{ $week->goalHeight() }}%">
            <span class="absolute -top-2 right-0 bg-white pl-1.5 text-[10px] leading-none font-semibold text-brand-600">goal</span>
        </div>

        <div class="relative grid h-full grid-cols-7 items-end gap-2 pr-8">
            @foreach ($week->days as $day)
                <a href="{{ route('dashboard', ['date' => $day['date']]) }}"
                   title="{{ WeekSummary::describe($day) }}" aria-label="{{ WeekSummary::describe($day) }}"
                   class="group flex h-full items-end justify-center gap-0.5 transition hover:opacity-80">
                    {{-- Calories: green on or under goal, red over, a faint line when nothing was logged --}}
                    <span class="w-full max-w-3 rounded-t {{ ! $day['logged'] ? 'bg-slate-200' : ($day['over'] ? 'bg-rose-500' : 'bg-brand-600') }}"
                          style="height: {{ $day['logged'] ? max(4, $week->calorieHeight($day['calories'])) : 3 }}%"></span>
                    {{-- Water: blue, or a faint line when none was logged --}}
                    <span class="w-full max-w-3 rounded-t {{ $day['waterMl'] > 0 ? 'bg-sky-400' : 'bg-slate-200' }}"
                          style="height: {{ $day['waterMl'] > 0 ? max(4, $week->waterHeight($day['waterMl'])) : 3 }}%"></span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Day names, with a dot under each day that has food logged --}}
    <div class="mt-2 grid grid-cols-7 gap-2 pr-8 text-center">
        @foreach ($week->days as $day)
            <div class="flex flex-col items-center gap-1.5">
                <span class="rounded-full px-1.5 py-0.5 text-[10px] leading-none font-semibold {{ $day['isViewed'] ? 'bg-brand-600 text-white' : ($day['isToday'] ? 'text-brand-600' : 'text-slate-400') }}">{{ $day['label'] }}</span>
                <span class="size-1.5 rounded-full {{ $day['logged'] ? 'bg-brand-600' : 'bg-slate-200' }}"></span>
            </div>
        @endforeach
    </div>
</section>
