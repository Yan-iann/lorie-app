<?php

namespace App\Services;

use App\Services\Contracts\NutritionAssistant;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Provides one AI-generated healthy-eating tip per day, cached so the AI is
 * called at most once daily. Falls back to a curated tip if the AI is
 * unavailable.
 */
class DailyTip
{
    /** @var list<string> */
    private const FALLBACKS = [
        'Fill half your plate with vegetables before anything else.',
        'Swap sugary drinks for water infused with lemon or cucumber.',
        'Prep a protein and a veggie ahead so healthy is the easy choice.',
        'Eat slowly — it takes about 20 minutes to feel full.',
        'Choose whole fruit over juice for more fibre and less sugar.',
        'Add a handful of nuts or seeds for steady, lasting energy.',
        'Season with herbs and spices instead of reaching for extra salt.',
        'Include a serving of protein at every meal to stay satisfied.',
    ];

    public function __construct(private readonly NutritionAssistant $ai) {}

    public static function key(): string
    {
        return 'daily_tip:'.today()->toDateString();
    }

    public function today(): string
    {
        $cached = Cache::get(self::key());
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $tip = $this->fromAi();
        if ($tip !== null) {
            Cache::put(self::key(), $tip, today()->endOfDay());

            return $tip;
        }

        // AI unavailable — serve a curated tip, but retry the AI again soon.
        $fallback = $this->fallback();
        Cache::put(self::key(), $fallback, now()->addMinutes(30));

        return $fallback;
    }

    private function fromAi(): ?string
    {
        try {
            return $this->clean($this->ai->dailyTip()) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function clean(string $raw): string
    {
        $tip = trim($raw);
        $tip = trim($tip, "\"'“”‘’ \t\n\r");
        $tip = preg_replace('/\s+/', ' ', $tip) ?? $tip;

        if (mb_strlen($tip) > 160) {
            $tip = rtrim(mb_substr($tip, 0, 157)).'…';
        }

        return $tip;
    }

    private function fallback(): string
    {
        $index = abs(crc32(today()->toDateString())) % count(self::FALLBACKS);

        return self::FALLBACKS[$index];
    }
}
