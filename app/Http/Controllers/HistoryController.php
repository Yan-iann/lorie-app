<?php

namespace App\Http\Controllers;

use App\Models\FoodEntry;
use App\Models\User;
use App\Models\WaterLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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
            'water' => $this->waterTotals($user, $days->getCollection()),
        ]);
    }

    /**
     * Water drunk on each of the given days, in millilitres, keyed by date.
     *
     * @param  Collection<int, FoodEntry>  $days
     * @return Collection<string, int>
     */
    private function waterTotals(User $user, Collection $days): Collection
    {
        if ($days->isEmpty()) {
            return collect();
        }

        $dates = $days->map(fn (FoodEntry $day) => $day->consumed_on->toDateString());

        return $user->waterLogs()
            ->whereDate('consumed_on', '>=', $dates->min())
            ->whereDate('consumed_on', '<=', $dates->max())
            ->selectRaw('consumed_on, SUM(amount_ml) as total_ml')
            ->groupBy('consumed_on')
            ->get()
            ->mapWithKeys(fn (WaterLog $day) => [$day->consumed_on->toDateString() => (int) $day->total_ml]);
    }
}
