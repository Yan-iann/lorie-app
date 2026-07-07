<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateGoalRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;

class GoalController extends Controller
{
    public function update(UpdateGoalRequest $request): RedirectResponse
    {
        Setting::put('daily_goal', (string) $request->validated()['daily_goal']);

        return back()->with('success', 'Daily calorie goal updated.');
    }
}
