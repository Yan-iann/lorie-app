<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasCompletedProfile()) {
            return redirect()->route('profile');
        }

        $days = $user->foodEntries()
            ->selectRaw('consumed_on, COUNT(*) as entries_count, SUM(calories) as total_calories, SUM(protein_g) as total_protein, SUM(carbs_g) as total_carbs, SUM(fat_g) as total_fat')
            ->groupBy('consumed_on')
            ->orderByDesc('consumed_on')
            ->paginate(14);

        return view('history', [
            'days' => $days,
            'goal' => $user->daily_goal,
        ]);
    }
}
