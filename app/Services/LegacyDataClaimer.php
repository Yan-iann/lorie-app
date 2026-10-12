<?php

namespace App\Services;

use App\Models\FoodEntry;
use App\Models\Setting;
use App\Models\User;
use App\Support\CalorieCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Before accounts existed the app held one person's food log and profile with
 * no owner. The first account ever created inherits that data, so upgrading
 * an existing install doesn't lose its history.
 */
class LegacyDataClaimer
{
    public function claimFor(User $user): void
    {
        if (User::query()->whereKeyNot($user->getKey())->exists()) {
            return;
        }

        DB::transaction(function () use ($user) {
            FoodEntry::query()->whereNull('user_id')->update(['user_id' => $user->getKey()]);

            $this->claimProfile($user);
        });
    }

    private function claimProfile(User $user): void
    {
        $settings = Setting::query()->pluck('value', 'key');

        if ($settings->isEmpty()) {
            return;
        }

        $activity = $settings->get('activity_level');

        $user->forceFill([
            'gender' => $settings->get('gender') ?: null,
            'height_cm' => is_numeric($settings->get('height_cm')) ? (float) $settings->get('height_cm') : null,
            'weight_kg' => is_numeric($settings->get('weight_kg')) ? (float) $settings->get('weight_kg') : null,
            'activity_level' => isset(CalorieCalculator::ACTIVITY_FACTORS[$activity]) ? $activity : 'moderate',
            'daily_goal' => (int) ($settings->get('daily_goal') ?: 2000),
            'profile_completed_at' => $settings->get('profile_completed') === '1' ? now() : null,
        ])->save();

        Setting::query()->delete();
    }
}
