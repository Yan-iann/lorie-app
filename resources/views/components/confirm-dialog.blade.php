{{--
    One confirmation dialog for the whole app. Any form marked `data-confirm`
    opens it instead of submitting; app.js fills in the wording from the form's
    data-confirm-title / -text / -action / -icon attributes and submits the
    form if the user agrees.
--}}
<dialog id="confirm-dialog" aria-labelledby="confirm-title" aria-describedby="confirm-text"
        class="confirm-dialog m-auto w-[calc(100%-2rem)] max-w-sm rounded-3xl border border-white/70 bg-white/90 p-0 text-slate-800 shadow-2xl backdrop-blur-xl">
    <div class="p-6 text-center">
        <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-rose-100 text-rose-600">
            <x-icon name="trash" variant="duotone" class="size-7" data-confirm-icon="trash" />
            <x-icon name="rotate" class="hidden size-6" data-confirm-icon="undo" />
        </span>

        <h2 id="confirm-title" class="mt-4 text-lg font-bold tracking-tight text-slate-900">Are you sure?</h2>
        <p id="confirm-text" class="mt-1.5 text-sm leading-relaxed text-slate-500"></p>

        <div class="mt-6 grid grid-cols-2 gap-3">
            <button type="button" data-confirm-cancel
                    class="cursor-pointer rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm outline-none transition hover:bg-slate-50 focus-visible:ring-4 focus-visible:ring-slate-400/30 active:scale-95">
                Cancel
            </button>
            <button type="button" data-confirm-accept
                    class="cursor-pointer rounded-xl bg-rose-600 px-4 py-3 text-sm font-semibold text-white outline-none transition hover:bg-rose-700 focus-visible:ring-4 focus-visible:ring-rose-500/30 active:scale-95">
                Remove
            </button>
        </div>
    </div>
</dialog>
