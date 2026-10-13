@props(['entry'])

@php
    [$sourceLabel, $sourceClass] = match ($entry->source) {
        'usda' => ['USDA data', 'bg-brand-100 text-brand-700'],
        'hybrid' => ['USDA + AI', 'bg-sky-100 text-sky-700'],
        default => ['AI estimate', 'bg-amber-100 text-amber-700'],
    };

    [$confidenceLabel, $confidenceDot] = match ($entry->confidence) {
        'high' => ['High confidence', 'bg-brand-600'],
        'low' => ['Low confidence', 'bg-rose-500'],
        default => ['Medium confidence', 'bg-amber-500'],
    };

    // The same four slots, in the same order, on every card.
    $stats = [
        ['kcal', number_format($entry->calories), 'bg-brand-50', 'bg-brand-600'],
        ['Protein', $entry->protein_g.'g', 'bg-sky-50', 'bg-sky-400'],
        ['Carbs', $entry->carbs_g.'g', 'bg-amber-50', 'bg-amber-400'],
        ['Fat', $entry->fat_g.'g', 'bg-rose-50', 'bg-rose-400'],
    ];

    $pill = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-[11px] leading-none font-semibold';

    // A confident check is routine and can wait in the details; a doubtful one is shown up front.
    $noteUpFront = $entry->verification_note && $entry->confidence !== 'high';
    $hasDetails = ! empty($entry->breakdown) || ($entry->verification_note && ! $noteUpFront);
@endphp

<article class="animate-rise rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    {{-- Name + remove --}}
    <div class="flex items-start justify-between gap-3">
        <h3 class="min-w-0 pt-0.5 leading-snug font-semibold text-slate-900">{{ $entry->name }}</h3>

        <form method="POST" action="{{ route('entries.destroy', $entry) }}" class="shrink-0"
              data-confirm data-confirm-title="Remove this entry?" data-confirm-action="Remove"
              data-confirm-text="{{ $entry->name }} ({{ number_format($entry->calories) }} kcal) will be taken out of your log.">
            @csrf
            @method('DELETE')
            <button type="submit" class="-mt-1 -mr-1 grid size-9 cursor-pointer place-items-center rounded-xl bg-slate-100 text-slate-500 transition hover:bg-rose-100 hover:text-rose-600 active:scale-90" title="Remove" aria-label="Remove entry">
                <x-icon name="trash" variant="duotone" class="size-5" />
            </button>
        </form>
    </div>

    {{-- Tags --}}
    <div class="mt-3.5 flex flex-wrap gap-1.5">
        <span class="{{ $pill }} {{ $sourceClass }}">{{ $sourceLabel }}</span>

        @if ($entry->quantity)
            <span class="{{ $pill }} bg-slate-100 text-slate-600">{{ Str::replaceLast(' total', '', $entry->quantity) }}</span>
        @endif

        @if ($entry->confidence)
            <span class="{{ $pill }} bg-slate-100 text-slate-600">
                <span class="size-1.5 rounded-full {{ $confidenceDot }}"></span>
                {{ $confidenceLabel }}
            </span>
        @endif
    </div>

    {{-- Nutrition --}}
    <div class="mt-5 grid grid-cols-4 gap-2.5 text-center">
        @foreach ($stats as [$label, $value, $tileClass, $dotClass])
            <div class="rounded-xl px-1 py-3.5 {{ $tileClass }}">
                <p class="text-sm font-bold text-slate-900 tabular-nums">{{ $value }}</p>
                <p class="mt-1.5 flex items-center justify-center gap-1 text-[11px] font-medium text-slate-500">
                    <span class="size-1.5 rounded-full {{ $dotClass }}"></span>{{ $label }}
                </p>
            </div>
        @endforeach
    </div>

    @if ($noteUpFront)
        <p class="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600">{{ $entry->verification_note }}</p>
    @endif

    @if ($hasDetails)
        <details class="group mt-5 border-t border-slate-100 pt-4">
            <summary class="flex cursor-pointer list-none items-center justify-between text-xs font-semibold text-slate-500 transition hover:text-brand-600 [&::-webkit-details-marker]:hidden">
                Details
                <x-icon name="chevron-right" class="size-3.5 transition-transform group-open:rotate-90" />
            </summary>

            @if ($entry->verification_note && ! $noteUpFront)
                <p class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600">{{ $entry->verification_note }}</p>
            @endif

            @if (! empty($entry->breakdown))
                <ul class="mt-4 flex flex-col gap-2.5 text-xs">
                    @foreach ($entry->breakdown as $item)
                        <li class="grid grid-cols-[minmax(0,1fr)_3rem_4rem] items-center gap-2">
                            <span class="flex min-w-0 items-center gap-1.5 text-slate-600">
                                <span class="size-1.5 shrink-0 rounded-full {{ ($item['source'] ?? '') === 'usda' ? 'bg-brand-600' : 'bg-amber-500' }}" title="{{ ($item['source'] ?? '') === 'usda' ? 'USDA data' : 'AI estimate' }}"></span>
                                <span class="truncate">{{ $item['name'] }}</span>
                            </span>
                            <span class="text-right text-slate-400 tabular-nums">{{ $item['grams'] }}g</span>
                            <span class="text-right font-semibold text-slate-700 tabular-nums">{{ number_format($item['calories']) }} kcal</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </details>
    @endif
</article>
