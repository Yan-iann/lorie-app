<?php

namespace App\Http\Controllers;

use App\Models\FoodEntry;
use App\Models\Setting;
use Illuminate\Contracts\View\View;

class HistoryController extends Controller
{
    public function index(): View
    {
        $goal = (int) Setting::get('daily_goal', '2000');

        $days = FoodEntry::query()
            ->selectRaw('consumed_on, COUNT(*) as entries_count, SUM(calories) as total_calories, SUM(protein_g) as total_protein, SUM(carbs_g) as total_carbs, SUM(fat_g) as total_fat')
            ->groupBy('consumed_on')
            ->orderByDesc('consumed_on')
            ->paginate(14);

        return view('history', [
            'days' => $days,
            'goal' => $goal,
        ]);
    }
}
