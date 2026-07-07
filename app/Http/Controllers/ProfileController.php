<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\Setting;
use App\Support\CalorieCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile', [
            'onboarding' => Setting::get('profile_completed') !== '1',
            'name' => Setting::get('display_name', ''),
            'gender' => Setting::get('gender', ''),
            'heightCm' => Setting::get('height_cm', ''),
            'weightKg' => Setting::get('weight_kg', ''),
            'activity' => Setting::get('activity_level', 'moderate'),
            'goal' => (int) Setting::get('daily_goal', '2000'),
            'activityFactors' => CalorieCalculator::ACTIVITY_FACTORS,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $wasOnboarding = Setting::get('profile_completed') !== '1';

        Setting::put('display_name', trim($data['display_name']));
        Setting::put('gender', $data['gender']);
        Setting::put('height_cm', (string) ($data['height_cm'] ?? ''));
        Setting::put('weight_kg', (string) ($data['weight_kg'] ?? ''));
        Setting::put('activity_level', $data['activity_level'] ?? 'moderate');
        Setting::put('daily_goal', (string) $data['daily_goal']);
        Setting::put('profile_completed', '1');

        if ($wasOnboarding) {
            return redirect()->route('dashboard')->with('success', "You're all set — let's start tracking!");
        }

        return back()->with('success', 'Profile updated.');
    }
}
