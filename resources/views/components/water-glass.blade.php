@props(['level' => 0])

{{--
    A glass that fills to `level` (0 to 1). The water is one group that slides
    up and down inside the glass's outline, with two wave shapes drifting
    across its surface (see app.css).
--}}
<svg {{ $attributes->merge(['class' => 'water-glass']) }} viewBox="0 0 100 130" style="--level: {{ $level }}"
     role="img" aria-label="{{ round($level * 100) }}% of the water goal">
    <defs>
        <clipPath id="water-glass-inside">
            <path d="M13.6 7.5 H86.4 L78.5 117.8 Q78 122.5 73 122.5 H27 Q22 122.5 21.5 117.8 Z" />
        </clipPath>
    </defs>

    <path class="fill-sky-50" d="M12 6 H88 L80 118 Q79.3 124 73 124 H27 Q20.7 124 20 118 Z" />

    <g clip-path="url(#water-glass-inside)">
        <g class="water-glass__water">
            <path class="water-glass__wave water-glass__wave--back fill-sky-300"
                  d="M-100 6 Q-87.5 0 -75 6 T-50 6 T-25 6 T0 6 T25 6 T50 6 T75 6 T100 6 T125 6 T150 6 T175 6 T200 6 V140 H-100 Z" />
            <path class="water-glass__wave fill-sky-400"
                  d="M-100 8 Q-87.5 13 -75 8 T-50 8 T-25 8 T0 8 T25 8 T50 8 T75 8 T100 8 T125 8 T150 8 T175 8 T200 8 V140 H-100 Z" />
        </g>
    </g>

    <path class="fill-none stroke-slate-300" stroke-width="3" stroke-linejoin="round"
          d="M12 6 H88 L80 118 Q79.3 124 73 124 H27 Q20.7 124 20 118 Z" />
</svg>
