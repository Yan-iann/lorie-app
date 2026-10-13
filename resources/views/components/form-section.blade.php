@props(['title', 'icon', 'description' => null, 'variant' => null])

{{-- One titled group of fields on a settings page, in its own card. --}}
<section {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white p-6 shadow-sm']) }}>
    <div class="flex items-center gap-3">
        <x-icon :name="$icon" :variant="$variant" class="size-6 shrink-0 text-brand-600" />
        <div class="min-w-0">
            <h2 class="text-base leading-tight font-bold text-slate-900">{{ $title }}</h2>
            @if ($description)
                <p class="mt-0.5 text-xs text-slate-400">{{ $description }}</p>
            @endif
        </div>
    </div>

    <div class="mt-5 space-y-5">
        {{ $slot }}
    </div>
</section>
