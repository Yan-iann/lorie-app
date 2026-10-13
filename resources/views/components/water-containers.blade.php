@props(['date' => null])

@php
    use App\Support\WaterCalculator;
@endphp

{{-- One tap logs a drink. Sent in the background when JavaScript is running (data-async, see app.js). --}}
<form method="POST" action="{{ route('water.store') }}" data-async
      {{ $attributes->merge(['class' => 'grid grid-cols-5 gap-2']) }}>
    @csrf
    <input type="hidden" name="consumed_on" value="{{ $date }}">

    @foreach (WaterCalculator::CONTAINERS as $key => $container)
        <button type="submit" name="container" value="{{ $key }}" title="{{ $container['label'] }}"
                aria-label="Add a {{ Str::lower($container['label']) }} of water, {{ WaterCalculator::format($container['ml']) }}"
                class="flex cursor-pointer flex-col items-center gap-1 rounded-xl border border-slate-200 bg-white px-1 py-2.5 text-sky-600 transition hover:border-sky-300 hover:bg-sky-50 active:scale-90 active:bg-sky-100">
            <x-icon :name="'water-'.$key" class="size-8" />
            <span class="text-[11px] leading-none font-semibold text-slate-600 tabular-nums">{{ WaterCalculator::format($container['ml']) }}</span>
        </button>
    @endforeach
</form>
