<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Caps how many food entries each person can add per day. Every entry costs
 * AI requests from a quota the whole app shares, so without a cap one person
 * could use it up for everyone.
 *
 * Counts entries as they are added rather than counting rows, so deleting an
 * entry doesn't hand the slot back — the AI work was already spent. The count
 * starts again at midnight (app timezone).
 */
class DailyEntryAllowance
{
    /**
     * Entries allowed per person per day. Zero means no cap.
     */
    public function limit(): int
    {
        return max(0, (int) config('services.ai.daily_entries_per_user'));
    }

    public function isCapped(): bool
    {
        return $this->limit() > 0;
    }

    public function used(User $user): int
    {
        return (int) RateLimiter::attempts($this->key($user));
    }

    /**
     * Entries the user can still add today, or null when there is no cap.
     */
    public function remaining(User $user): ?int
    {
        return $this->isCapped() ? max(0, $this->limit() - $this->used($user)) : null;
    }

    public function exhausted(User $user): bool
    {
        return $this->remaining($user) === 0;
    }

    public function record(User $user): void
    {
        // The date is part of the key, so a day is long enough for the count to live.
        RateLimiter::hit($this->key($user), 86400);
    }

    private function key(User $user): string
    {
        return "food-entries:{$user->getKey()}:".today()->toDateString();
    }
}
