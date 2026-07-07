<?php

namespace App\Support;

class CalorieCalculator
{
    /** Activity multipliers applied to BMR to estimate daily needs (TDEE). */
    public const ACTIVITY_FACTORS = [
        'sedentary' => 1.2,
        'light' => 1.375,
        'moderate' => 1.55,
        'active' => 1.725,
        'athlete' => 1.9,
    ];

    /**
     * Recommend a daily calorie goal.
     *
     * With height + weight it uses the Mifflin-St Jeor BMR equation times an
     * activity factor. Without them it falls back to a gender-based default.
     * The result is clamped to a sane range and rounded to the nearest 10.
     */
    public static function recommendedGoal(
        string $gender,
        ?float $heightCm = null,
        ?float $weightKg = null,
        ?string $activity = null,
    ): int {
        $factor = self::ACTIVITY_FACTORS[$activity] ?? self::ACTIVITY_FACTORS['moderate'];

        if ($heightCm && $weightKg) {
            $age = 30; // representative default — age is no longer collected
            $genderConstant = match ($gender) {
                'male' => 5,
                'female' => -161,
                default => -78, // midpoint when unspecified
            };

            $bmr = 10 * $weightKg + 6.25 * $heightCm - 5 * $age + $genderConstant;
            $goal = (int) (round($bmr * $factor / 10) * 10);
        } else {
            $goal = match ($gender) {
                'male' => 2500,
                'female' => 2000,
                default => 2200,
            };
        }

        return max(1200, min(5000, $goal));
    }
}
