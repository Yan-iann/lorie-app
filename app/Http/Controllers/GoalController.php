<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateGoalRequest;
use Illuminate\Http\RedirectResponse;

class GoalController extends Controller
{
    public function update(UpdateGoalRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $user->daily_goal = $validated['daily_goal'];

        if (filled($validated['display_name'] ?? null)) {
            $user->name = trim($validated['display_name']);
        }

        $user->save();

        return back()->with('success', 'Settings updated.');
    }
}
