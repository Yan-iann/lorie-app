@props(['fact'])

{{-- Lorie's food fact of the day --}}
<aside {{ $attributes->merge(['class' => 'animate-rise flex items-start gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm']) }}>
    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-600">
        <x-icon name="bulb" class="size-5" />
    </span>
    <div>
        <p class="text-xs font-semibold tracking-wide text-brand-600 uppercase">Did you know?</p>
        <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $fact }}</p>
        <p class="mt-1.5 text-[11px] text-slate-400">Lorie's food fact of the day</p>
    </div>
</aside>
