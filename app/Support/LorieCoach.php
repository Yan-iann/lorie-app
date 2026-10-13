<?php

namespace App\Support;

use App\Models\User;

/**
 * Writes Lorie's message on the dashboard: one line about the day's food and
 * one about water, in the language the user picked. The wording lives in
 * lang/{language}/lorie.php.
 */
class LorieCoach
{
    /**
     * Languages Lorie can speak, in the order the profile page lists them.
     * "mix" is casual English with Tagalog thrown in.
     *
     * @var array<string, string>
     */
    public const LANGUAGES = [
        'en' => 'English',
        'tl' => 'Tagalog',
        'ceb' => 'Bisaya / Cebuano',
        'mix' => 'Mix (English + Tagalog)',
    ];

    public const DEFAULT_LANGUAGE = 'mix';

    public function message(User $user, int $entryCount, int $remainingKcal, int $percent, WaterDay $water): string
    {
        $language = isset(self::LANGUAGES[$user->lorie_language]) ? $user->lorie_language : self::DEFAULT_LANGUAGE;

        return $this->food($language, $user, $entryCount, $remainingKcal, $percent).' '.$this->water($language, $water);
    }

    /**
     * The same message, working the food side out from the database. Used when
     * water is logged in the background and Lorie's bubble needs refreshing
     * without the dashboard being rebuilt.
     */
    public function messageForDay(User $user, WaterDay $water): string
    {
        $entries = $user->foodEntries()->forDate($water->date);
        $calories = (int) $entries->sum('calories');
        $goal = $user->daily_goal;

        return $this->message(
            $user,
            $entries->count(),
            $goal - $calories,
            $goal > 0 ? min(100, (int) round($calories / $goal * 100)) : 0,
            $water,
        );
    }

    private function food(string $language, User $user, int $entryCount, int $remainingKcal, int $percent): string
    {
        $situation = match (true) {
            $entryCount === 0 => 'none',
            $remainingKcal < 0 => 'over',
            $percent >= 80 => 'close',
            default => 'steady',
        };

        return __("lorie.food.{$situation}", [
            'name' => $user->name,
            'kcal' => number_format(abs($remainingKcal)),
        ], $language);
    }

    private function water(string $language, WaterDay $water): string
    {
        $situation = match (true) {
            $water->goalReached() => 'done',
            $water->totalMl() === 0 => 'none',
            default => 'going',
        };

        return __("lorie.water.{$situation}", [
            'amount' => WaterCalculator::format($water->remainingMl()),
        ], $language);
    }
}
