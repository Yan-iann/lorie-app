{{--
    "Add food" popup, opened by any [data-add-food-toggle] button (see app.js).
    First a short menu — type it, or take a photo — then the text form if typing.
    The date comes from the page's "add-food-date" section; without one, entries
    are logged for today.
--}}
<div id="add-food-backdrop" data-open="false" class="add-food-backdrop fixed inset-0 z-20 bg-slate-900/30"></div>

<div id="add-food-panel" role="dialog" aria-modal="true" aria-labelledby="add-food-title"
     data-open="false" data-initial-view="{{ old('description') !== null ? 'type' : '' }}"
     class="add-food-panel fixed inset-x-4 bottom-[calc(env(safe-area-inset-bottom)+5.75rem)] z-40 mx-auto max-w-sm overflow-hidden rounded-3xl border border-slate-200 bg-white p-2 shadow-2xl sm:inset-x-auto sm:right-6 sm:bottom-24 sm:w-96">
    <h2 id="add-food-title" class="sr-only">Add food</h2>

    @if ($entriesLeft === 0)
        {{-- Today's entries are used up --}}
        <div data-add-food-view="menu" class="flex items-center gap-4 px-4 py-4">
            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-amber-100 text-amber-600">
                <x-icon name="clock" class="size-6" />
            </span>
            <span>
                <span class="block font-bold text-slate-900">That's all for today</span>
                <span class="block text-xs text-slate-500">You've used all {{ $entryLimit }} of today's entries. You can add more tomorrow.</span>
            </span>
        </div>
    @else
    {{-- Step 1: choose how to add --}}
    <div data-add-food-view="menu">
        <button type="button" data-add-food-show="type"
                class="flex w-full cursor-pointer items-center gap-4 rounded-2xl px-4 py-4 text-left outline-brand-500 transition hover:bg-slate-50 focus-visible:outline-2 active:scale-[0.98] active:bg-slate-100">
            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-brand-100 text-brand-600">
                <x-icon name="pencil" class="size-6" />
            </span>
            <span>
                <span class="block font-bold text-slate-900">Type it</span>
                <span class="block text-xs text-slate-500">Describe what you ate in plain words</span>
            </span>
        </button>

        <div class="mx-4 h-px bg-slate-100"></div>

        <form method="POST" action="{{ route('entries.photo') }}" enctype="multipart/form-data" data-loading="Looking at your photo…">
            @csrf
            <input type="hidden" name="consumed_on" value="@yield('add-food-date')">
            <input type="file" name="photo" id="photo-input" accept="image/*" capture="environment" class="peer sr-only" data-autosubmit>
            <label for="photo-input"
                   class="flex cursor-pointer items-center gap-4 rounded-2xl px-4 py-4 transition hover:bg-slate-50 active:scale-[0.98] active:bg-slate-100 peer-focus-visible:bg-slate-50">
                <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-sky-100 text-sky-600">
                    <x-icon name="camera" variant="duotone" class="size-6" />
                </span>
                <span>
                    <span class="block font-bold text-slate-900">Take a photo</span>
                    <span class="block text-xs text-slate-500">Snap your meal and I'll work out what it is</span>
                </span>
            </label>
        </form>

        @if ($entriesLeft !== null)
            <p class="px-4 pt-1 pb-2 text-center text-[11px] text-slate-400">
                {{ $entriesLeft }} of {{ $entryLimit }} {{ Str::plural('entry', $entryLimit) }} left today
            </p>
        @endif
    </div>

    {{-- Step 2: describe the food --}}
    <form data-add-food-view="type" method="POST" action="{{ route('entries.store') }}"
          class="hidden space-y-3 p-3" data-loading="Analyzing your food…">
        @csrf
        <input type="hidden" name="consumed_on" value="@yield('add-food-date')">

        <div class="flex items-center gap-2">
            <button type="button" data-add-food-show="menu" aria-label="Back"
                    class="-ml-1 grid size-9 cursor-pointer place-items-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 active:scale-90">
                <x-icon name="chevron-left" class="size-4" />
            </button>
            <label for="food-description" class="font-bold text-slate-900">What did you eat?</label>
        </div>

        <input id="food-description" type="text" name="description" value="{{ old('description') }}" required
               placeholder="e.g. chicken adobo with rice" autocomplete="off"
               class="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-4 py-3 text-base shadow-sm outline-none transition focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-500/15 sm:text-sm">

        <button type="submit"
                class="block w-full cursor-pointer rounded-xl bg-brand-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-brand-700 active:scale-95 focus:ring-4 focus:ring-brand-600/20">
            Estimate &amp; add
        </button>

        <p class="text-center text-xs text-slate-400">AI breaks it down and looks up real nutrition data.</p>
    </form>
    @endif
</div>
