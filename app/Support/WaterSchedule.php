<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Paces today's water across the user's drinking hours.
 *
 * The goal is spread evenly over the window, so at any moment there is an
 * amount you "should" have drunk by now. Every drink buys time in proportion
 * to its size: the next drink is due when that steady pace catches up with
 * what you have actually had. A big bottle pushes the next drink far out, a
 * small cup only a little — and following it always reaches the goal before
 * the window closes.
 */
class WaterSchedule
{
    public const BEFORE = 'before';     // the window hasn't opened yet

    public const ON_TRACK = 'on_track'; // ahead of the pace; next drink is in the future

    public const BEHIND = 'behind';     // the pace has passed what's been drunk

    public const DONE = 'done';         // goal reached

    public const ENDED = 'ended';       // window closed with the goal not reached

    public function __construct(
        public readonly Carbon $start,
        public readonly Carbon $end,
        public readonly int $goalMl,
        public readonly int $totalMl,
        public readonly Carbon $now,
    ) {}

    public static function for(User $user, int $goalMl, int $totalMl, ?Carbon $now = null): self
    {
        $now ??= now();

        return new self(
            start: $now->copy()->setTimeFromTimeString($user->water_starts_at),
            end: $now->copy()->setTimeFromTimeString($user->water_ends_at),
            goalMl: $goalMl,
            totalMl: $totalMl,
            now: $now,
        );
    }

    public function status(): string
    {
        return match (true) {
            $this->totalMl >= $this->goalMl => self::DONE,
            $this->now->gte($this->end) => self::ENDED,
            $this->now->lt($this->start) => self::BEFORE,
            $this->paceCatchesUpAt()->lte($this->now) => self::BEHIND,
            default => self::ON_TRACK,
        };
    }

    /**
     * How much the steady pace says should have been drunk by now.
     */
    public function expectedMl(): int
    {
        $elapsed = max(0, min($this->windowMinutes(), (int) $this->start->diffInMinutes($this->now)));

        return (int) round($this->goalMl * $elapsed / $this->windowMinutes());
    }

    public function behindMl(): int
    {
        return max(0, $this->expectedMl() - $this->totalMl);
    }

    public function remainingMl(): int
    {
        return max(0, $this->goalMl - $this->totalMl);
    }

    /**
     * When to drink next: now if behind, otherwise the moment the pace catches
     * up with what's been drunk. Null once there is nothing left to schedule.
     */
    public function nextDrinkAt(): ?Carbon
    {
        return match ($this->status()) {
            self::DONE, self::ENDED => null,
            self::BEHIND => $this->now->copy(),
            default => $this->paceCatchesUpAt(),
        };
    }

    /**
     * How long a drink of this size keeps you on pace.
     */
    public function minutesFor(int $ml): int
    {
        return (int) round($ml / $this->goalMl * $this->windowMinutes());
    }

    /**
     * The rest of today as a list of times, if every drink were this size.
     * On pace, each is one drink's worth of time after the last. Behind, the
     * first is now and the rest are squeezed evenly into the time left, so the
     * goal is still met inside the window.
     *
     * @return list<Carbon>
     */
    public function plan(int $ml, int $limit = 12): array
    {
        $first = $this->nextDrinkAt();

        if ($first === null || $ml <= 0) {
            return [];
        }

        $drinks = (int) ceil($this->remainingMl() / $ml);
        $gap = $this->status() === self::BEHIND
            ? (int) floor($this->now->diffInMinutes($this->end) / $drinks)
            : $this->minutesFor($ml);

        $times = [];

        for ($i = 0; $i < min($drinks, $limit); $i++) {
            $times[] = $first->copy()->addMinutes($i * $gap);
        }

        return $times;
    }

    /**
     * Write a length of time for people: "45m", "2h", "1h 45m".
     */
    public static function duration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return match (true) {
            $hours === 0 => "{$rest}m",
            $rest === 0 => "{$hours}h",
            default => "{$hours}h {$rest}m",
        };
    }

    private function windowMinutes(): int
    {
        return max(1, (int) $this->start->diffInMinutes($this->end));
    }

    /**
     * The moment the steady pace reaches the amount already drunk.
     */
    private function paceCatchesUpAt(): Carbon
    {
        return $this->start->copy()->addMinutes((int) round($this->totalMl / $this->goalMl * $this->windowMinutes()));
    }
}
