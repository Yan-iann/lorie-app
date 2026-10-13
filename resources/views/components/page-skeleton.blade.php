{{-- Placeholder swapped in for the page content while the next page loads (see app.js) --}}
<div id="page-skeleton" class="animate-fade-in hidden" role="status">
    <span class="sr-only">Loading…</span>

    <div class="flex animate-pulse flex-col gap-6 motion-reduce:animate-none" aria-hidden="true">
        <div class="flex flex-col gap-2.5">
            <div class="h-3 w-32 rounded-full bg-slate-200"></div>
            <div class="h-8 w-56 rounded-xl bg-slate-200"></div>
        </div>

        <div class="h-24 rounded-2xl bg-slate-200"></div>

        <div class="flex items-center gap-6 rounded-2xl border border-slate-200 bg-white p-6">
            <div class="size-24 shrink-0 rounded-full bg-slate-200"></div>
            <div class="flex flex-1 flex-col gap-3">
                <div class="h-4 w-3/4 rounded-full bg-slate-200"></div>
                <div class="h-4 w-1/2 rounded-full bg-slate-200"></div>
                <div class="h-4 w-2/3 rounded-full bg-slate-200"></div>
            </div>
        </div>

        <div class="flex flex-col gap-2.5">
            @foreach (range(1, 3) as $row)
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-4 py-4">
                    <div class="flex flex-1 flex-col gap-2">
                        <div class="h-4 w-2/5 rounded-full bg-slate-200"></div>
                        <div class="h-3 w-3/5 rounded-full bg-slate-200"></div>
                    </div>
                    <div class="h-6 w-14 rounded-lg bg-slate-200"></div>
                </div>
            @endforeach
        </div>
    </div>
</div>
