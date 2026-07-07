<?php

namespace App\Http\Controllers;

use App\Models\FoodEntry;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $date = $request->date('date')?->toDateString() ?? today()->toDateString();

        $entries = FoodEntry::forDate($date)->latest()->get();

        $goal = (int) Setting::get('daily_goal', '2000');

        $totals = [
            'calories' => (int) $entries->sum('calories'),
            'protein_g' => round($entries->sum('protein_g'), 1),
            'carbs_g' => round($entries->sum('carbs_g'), 1),
            'fat_g' => round($entries->sum('fat_g'), 1),
        ];

        $remaining = $goal - $totals['calories'];
        $percent = $goal > 0 ? min(100, (int) round($totals['calories'] / $goal * 100)) : 0;

        return view('dashboard', [
            'date' => $date,
            'entries' => $entries,
            'goal' => $goal,
            'totals' => $totals,
            'remaining' => $remaining,
            'percent' => $percent,
        ]);
    }
}
