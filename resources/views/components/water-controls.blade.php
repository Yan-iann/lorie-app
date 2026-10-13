@props(['day'])

@php
    use App\Support\WaterCalculator;

    $drinks = $day->logs->count();
@endphp

{{--
    Logging water for a day: the container buttons, then what has been drunk so
    far with undo. Shown in the + popup's water view. Like the gauge, it is
    redrawn by the server after each change and swapped in by app.js
    (data-water-part).
--}}
<div data-water-part="controls">
    <x-water-containers :date="$day->date" />

    @if ($drinks > 0)
        <div class="mt-4 border-t border-slate-100 pt-3">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs font-semibold text-slate-500">
                    {{ $drinks }} {{ Str::plural('drink', $drinks) }} · {{ WaterCalculator::litres($day->totalMl()) }}
                </p>

                {{-- Undo: removes the most recent drink --}}
                <form method="POST" action="{{ route('water.destroy', $day->logs->first()) }}" data-async
                      data-confirm data-confirm-icon="undo" data-confirm-title="Undo your last drink?" data-confirm-action="Undo"
                      data-confirm-text="{{ WaterCalculator::format($day->logs->first()->amount_ml) }} ({{ Str::lower($day->logs->first()->containerLabel()) }}) will be taken off this day's water.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex cursor-pointer items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-200 active:scale-95">
                        <x-icon name="rotate" class="size-3.5" /> Undo
                    </button>
                </form>
            </div>

            {{-- Newest first. Tap one to remove it. --}}
            <ul class="mt-2.5 flex flex-wrap gap-1.5">
                @foreach ($day->logs as $log)
                    <li>
                        <form method="POST" action="{{ route('water.destroy', $log) }}" data-async
                              data-confirm data-confirm-title="Remove this drink?" data-confirm-action="Remove"
                              data-confirm-text="{{ WaterCalculator::format($log->amount_ml) }} ({{ Str::lower($log->containerLabel()) }}) will be taken off this day's water.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" title="{{ $log->containerLabel() }}"
                                    aria-label="Remove {{ WaterCalculator::format($log->amount_ml) }} ({{ Str::lower($log->containerLabel()) }})"
                                    class="inline-flex cursor-pointer items-center gap-1 rounded-full bg-sky-50 py-1 pr-1.5 pl-2.5 text-[11px] leading-none font-semibold text-sky-700 transition hover:bg-rose-50 hover:text-rose-600 active:scale-95">
                                {{ WaterCalculator::format($log->amount_ml) }}
                                <x-icon name="x" class="size-3" />
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
