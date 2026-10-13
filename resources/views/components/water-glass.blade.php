@props(['level' => 0])

{{--
    The water gauge: a circle that fills with water to `level` (0 to 1). It is
    drawn on the same 160-unit grid as the calorie ring beside it, with the
    same track, so the two gauges are the same shape and size.

    The water is one group that slides up and down inside the circle, with two
    wave shapes drifting across its surface (see app.css). The class names
    still say "glass" from when this was drawn as a tumbler.
--}}
<svg {{ $attributes->merge(['class' => 'water-glass']) }} viewBox="0 0 160 160" style="--level: {{ $level }}"
     role="img" aria-label="{{ round($level * 100) }}% of the water goal">
    <defs>
        <clipPath id="water-glass-inside">
            <circle cx="80" cy="80" r="57.5" />
        </clipPath>
    </defs>

    <g clip-path="url(#water-glass-inside)">
        <g class="water-glass__water">
            <path class="water-glass__wave water-glass__wave--back fill-sky-300"
                  d="M-100 22 Q-87.5 15 -75 22 T-50 22 T-25 22 T0 22 T25 22 T50 22 T75 22 T100 22 T125 22 T150 22 T175 22 T200 22 T225 22 T250 22 T275 22 T300 22 V220 H-100 Z" />
            <path class="water-glass__wave fill-sky-400"
                  d="M-100 25 Q-87.5 31 -75 25 T-50 25 T-25 25 T0 25 T25 25 T50 25 T75 25 T100 25 T125 25 T150 25 T175 25 T200 25 T225 25 T250 25 T275 25 T300 25 V220 H-100 Z" />
        </g>
    </g>

    {{-- The same track as the calorie ring --}}
    <circle cx="80" cy="80" r="64" fill="none" stroke="currentColor" stroke-width="13" class="text-slate-100" />
</svg>
