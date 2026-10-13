@props(['type' => 'success'])

@php
    $isError = $type === 'error';
@endphp

{{-- Floating "glass" notification. app.js dismisses it after data-timeout ms, or at once on tap. --}}
<div role="{{ $isError ? 'alert' : 'status' }}" data-toast data-timeout="{{ $isError ? 6000 : 3500 }}"
     {{ $attributes->merge(['class' => 'toast pointer-events-auto flex w-full max-w-sm cursor-pointer items-start gap-3 rounded-2xl border border-white/70 bg-white/75 px-4 py-3 text-sm font-medium text-slate-800 shadow-xl shadow-slate-900/10 backdrop-blur-xl']) }}>
    <span class="grid size-6 shrink-0 place-items-center rounded-full text-white {{ $isError ? 'bg-rose-500' : 'bg-brand-600' }}">
        <x-icon :name="$isError ? 'alert' : 'check'" class="size-3.5" />
    </span>
    <div class="min-w-0 flex-1 pt-0.5 leading-snug">{{ $slot }}</div>
</div>
