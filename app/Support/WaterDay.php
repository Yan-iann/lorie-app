<?php

namespace App\Support;

use App\Models\User;
use App\Models\WaterLog;
use Illuminate\Database\Eloquent\Collection;

/**
 * One person's water for one day: what they drank, against their goal.
 */
class WaterDay
{
    /**
     * @param  Collection<int, WaterLog>  $logs  newest first
     */
    public function __construct(
        public readonly string $date,
        public readonly Collection $logs,
        public readonly int $goalMl,
    ) {}

    public static function for(User $user, string $date): self
    {
        return new self(
            date: $date,
            logs: $user->waterLogs()->forDate($date)->latest('id')->get(),
            goalMl: $user->waterGoalMl(),
        );
    }

    public function totalMl(): int
    {
        return (int) $this->logs->sum('amount_ml');
    }

    public function remainingMl(): int
    {
        return max(0, $this->goalMl - $this->totalMl());
    }

    public function goalReached(): bool
    {
        return $this->totalMl() >= $this->goalMl;
    }

    /**
     * Whole-number progress toward the goal. Can pass 100.
     */
    public function percent(): int
    {
        return $this->goalMl > 0 ? (int) round($this->totalMl() / $this->goalMl * 100) : 0;
    }

    /**
     * How full to draw the glass, from 0 (empty) to 1 (full).
     */
    public function level(): float
    {
        return $this->goalMl > 0 ? round(min(1, $this->totalMl() / $this->goalMl), 3) : 0.0;
    }
}
