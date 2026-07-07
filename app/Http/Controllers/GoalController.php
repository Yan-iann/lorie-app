<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateGoalRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;

class GoalController extends Controller
{
    public function update(UpdateGoalRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Setting::put('daily_goal', (string) $validated['daily_goal']);

        if (filled($validated['display_name'] ?? null)) {
            Setting::put('display_name', trim($validated['display_name']));
        }

        return back()->with('success', 'Settings updated.');
    }
}
