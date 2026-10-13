<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Support\CalorieCalculator;
use App\Support\LorieCoach;
use App\Support\WaterCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('profile', [
            'onboarding' => ! $user->hasCompletedProfile(),
            'name' => $user->name,
            'email' => $user->email,
            'gender' => $user->gender ?? '',
            'heightCm' => $user->height_cm ?? '',
            'weightKg' => $user->weight_kg ?? '',
            'activity' => $user->activity_level,
            'goal' => $user->daily_goal,
            'waterGoalL' => $user->daily_water_goal_ml ? $user->daily_water_goal_ml / 1000 : '',
            'waterSuggestionMl' => WaterCalculator::recommendedGoalMl($user->gender, $user->weight_kg, $user->activity_level),
            'activityFactors' => CalorieCalculator::ACTIVITY_FACTORS,
            'lorieLanguage' => $user->lorie_language,
            'lorieLanguages' => LorieCoach::LANGUAGES,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $wasOnboarding = ! $user->hasCompletedProfile();

        $user->fill([
            'name' => trim($data['display_name']),
            'gender' => $data['gender'],
            'height_cm' => $data['height_cm'] ?? null,
            'weight_kg' => $data['weight_kg'] ?? null,
            'activity_level' => $data['activity_level'] ?? 'moderate',
            'daily_goal' => $data['daily_goal'],
            'daily_water_goal_ml' => filled($data['daily_water_goal_l'] ?? null) ? (int) round($data['daily_water_goal_l'] * 1000) : null,
            'lorie_language' => $data['lorie_language'] ?? $user->lorie_language,
        ]);
        $user->profile_completed_at ??= now();
        $user->save();

        if ($wasOnboarding) {
            return redirect()->route('dashboard')->with('success', "You're all set — let's start tracking!");
        }

        return back()->with('success', 'Profile updated.');
    }
}
