<?php

namespace App\Support;

class WaterCalculator
{
    /**
     * What a drink can be logged as. The key is stored with each log and names
     * the icon ("water-{key}").
     *
     * @var array<string, array{label: string, ml: int}>
     */
    public const CONTAINERS = [
        'small-cup' => ['label' => 'Small cup', 'ml' => 150],
        'medium-cup' => ['label' => 'Medium cup', 'ml' => 250],
        'big-cup' => ['label' => 'Big cup', 'ml' => 350],
        'bottle' => ['label' => 'Bottle', 'ml' => 500],
        'large-bottle' => ['label' => 'Large bottle', 'ml' => 1000],
    ];

    /** Baseline need: millilitres of water per kilogram of body weight. */
    public const ML_PER_KG = 33;

    /** Extra millilitres on top of the baseline, by activity level. */
    public const ACTIVITY_EXTRA_ML = [
        'sedentary' => 0,
        'light' => 250,
        'moderate' => 500,
        'active' => 750,
        'athlete' => 1000,
    ];

    /** Used when weight isn't known. */
    public const DEFAULT_GOAL_ML = [
        'male' => 2500,
        'female' => 2000,
        'other' => 2200,
    ];

    public const MIN_GOAL_ML = 1500;

    public const MAX_GOAL_ML = 5000;

    /**
     * Recommend a daily water goal in millilitres.
     *
     * Water needs track body weight (not BMI): a baseline per kilogram, plus
     * more for an active day. Without a weight it falls back to a typical
     * figure by gender. Rounded to the nearest 100 ml and kept within a sane
     * range.
     */
    public static function recommendedGoalMl(?string $gender, ?float $weightKg = null, ?string $activity = null): int
    {
        if (! $weightKg) {
            return self::DEFAULT_GOAL_ML[$gender] ?? self::DEFAULT_GOAL_ML['other'];
        }

        $goal = $weightKg * self::ML_PER_KG + (self::ACTIVITY_EXTRA_ML[$activity] ?? self::ACTIVITY_EXTRA_ML['moderate']);
        $goal = (int) (round($goal / 100) * 100);

        return max(self::MIN_GOAL_ML, min(self::MAX_GOAL_ML, $goal));
    }

    /**
     * Write an amount for people: "150 ml", "1 L", "2.75 L".
     */
    public static function format(int $ml): string
    {
        return $ml < 1000 ? "{$ml} ml" : self::litres($ml);
    }

    /**
     * Litres only, for totals shown against a goal: "0.4 L", "2.75 L".
     */
    public static function litres(int $ml): string
    {
        return rtrim(rtrim(number_format($ml / 1000, 2), '0'), '.').' L';
    }
}
