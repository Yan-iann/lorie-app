@php
    // Mascot artwork (drop the file at public/images/mascot.png)
    $hasMascot = file_exists(public_path('images/mascot.png'));
@endphp

<div {{ $attributes->merge(['class' => 'animate-rise flex items-end gap-2 rounded-2xl bg-brand-600 px-3 py-3']) }}>
    @if ($hasMascot)
        <img src="{{ asset('images/mascot.png') }}" alt="Lorie the mascot" class="-mt-9 -mb-3 h-36 w-auto shrink-0 self-end drop-shadow-lg sm:h-40">
    @else
        <span class="grid size-20 shrink-0 place-items-center self-center rounded-2xl bg-white/20 text-white">
            <x-icon name="leaf" class="size-9" />
        </span>
    @endif
    <div class="flex-1 self-center rounded-2xl rounded-bl-md bg-white px-4 py-3 shadow-sm">
        <p class="text-sm font-bold text-brand-600">Lorie</p>
        <p class="mt-0.5 text-sm leading-snug text-slate-600">{{ $slot }}</p>
    </div>
</div>
