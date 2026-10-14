<?php

namespace App\Services;

use App\Services\Contracts\NutritionAssistant;
use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Log;

/**
 * Wraps a primary and a secondary NutritionAssistant. Requests go to the
 * primary; when it is rate-limited or unavailable, they transparently fall
 * over to the secondary.
 *
 * When the primary reports its quota is exhausted, a cached "breaker" is opened
 * for a cooldown period so subsequent requests skip straight to the secondary
 * instead of hammering the exhausted primary. Once the cooldown lapses the
 * primary is tried again automatically (self-healing when the quota resets).
 *
 * Any other failure (a 5xx, a timeout) opens the breaker too, but only
 * briefly: those usually clear quickly, yet while they last every request
 * would otherwise sit through the primary's slow failures before falling over.
 */
class FallbackNutritionAssistant implements NutritionAssistant
{
    private const BREAKER_KEY = 'ai:primary-unavailable';

    /** How long to skip the primary after a failure that isn't a quota limit. */
    private const OUTAGE_COOLDOWN_MINUTES = 5;

    public function __construct(
        private readonly NutritionAssistant $primary,
        private readonly NutritionAssistant $secondary,
        private readonly Cache $cache,
        private readonly int $cooldownMinutes = 30,
    ) {}

    public function isConfigured(): bool
    {
        return $this->primary->isConfigured() || $this->secondary->isConfigured();
    }

    public function identifyFood(string $imageBase64, string $mimeType): string
    {
        return $this->withFallback(
            'identifyFood',
            fn (NutritionAssistant $a) => $a->identifyFood($imageBase64, $mimeType),
        );
    }

    public function decompose(string $description): array
    {
        return $this->withFallback(
            'decompose',
            fn (NutritionAssistant $a) => $a->decompose($description),
        );
    }

    public function verify(string $description, array $breakdown, array $totals): array
    {
        return $this->withFallback(
            'verify',
            fn (NutritionAssistant $a) => $a->verify($description, $breakdown, $totals),
        );
    }

    public function shortText(string $prompt, float $temperature = 0.9): string
    {
        return $this->withFallback(
            'shortText',
            fn (NutritionAssistant $a) => $a->shortText($prompt, $temperature),
        );
    }

    /**
     * Run $call against the primary, transparently falling back to the secondary
     * when the primary is rate-limited or otherwise unavailable.
     */
    private function withFallback(string $operation, Closure $call): mixed
    {
        // Primary is in cooldown after a recent failure — go straight to the secondary.
        if ($this->breakerOpen() && $this->secondary->isConfigured()) {
            return $this->runSecondary($call);
        }

        try {
            return $call($this->primary);
        } catch (ProviderUnavailableException $e) {
            $this->openBreaker($e->quotaExhausted ? $this->cooldownMinutes : min($this->cooldownMinutes, self::OUTAGE_COOLDOWN_MINUTES));

            Log::warning("AI primary unavailable during {$operation}; using fallback provider.", [
                'quota_exhausted' => $e->quotaExhausted,
                'message' => $e->getMessage(),
            ]);

            if (! $this->secondary->isConfigured()) {
                throw new CalorieEstimationException('The AI service is busy right now. Please try again in a moment.', previous: $e);
            }

            return $this->runSecondary($call);
        }
    }

    private function runSecondary(Closure $call): mixed
    {
        try {
            return $call($this->secondary);
        } catch (ProviderUnavailableException $e) {
            throw new CalorieEstimationException('The AI service is busy right now. Please try again in a moment.', previous: $e);
        }
    }

    private function breakerOpen(): bool
    {
        return (bool) $this->cache->get(self::BREAKER_KEY, false);
    }

    private function openBreaker(int $minutes): void
    {
        if ($minutes > 0) {
            $this->cache->put(self::BREAKER_KEY, true, now()->addMinutes($minutes));
        }
    }
}
