<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DailyFoodFact;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request, DailyFoodFact $foodFact): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasCompletedProfile()) {
            return redirect()->route('profile');
        }

        // Fall back to today for a missing or malformed ?date= instead of erroring.
        $requested = $request->query('date');
        $date = is_string($requested) && Carbon::canBeCreatedFromFormat($requested, 'Y-m-d')
            ? $requested
            : today()->toDateString();

        $entries = $user->foodEntries()->forDate($date)->latest()->get();

        $goal = $user->daily_goal;

        $totals = [
            'calories' => (int) $entries->sum('calories'),
            'protein_g' => round($entries->sum('protein_g'), 1),
            'carbs_g' => round($entries->sum('carbs_g'), 1),
            'fat_g' => round($entries->sum('fat_g'), 1),
        ];

        $remaining = $goal - $totals['calories'];
        $percent = $goal > 0 ? min(100, (int) round($totals['calories'] / $goal * 100)) : 0;

        $name = $user->name;
        $greeting = $this->greeting();
        $streak = $this->loggingStreak($user);
        $coachMessage = $this->coachMessage($entries->count(), $remaining, $percent, $name);

        return view('dashboard', [
            'date' => $date,
            'entries' => $entries,
            'goal' => $goal,
            'totals' => $totals,
            'remaining' => $remaining,
            'percent' => $percent,
            'name' => $name,
            'greeting' => $greeting,
            'streak' => $streak,
            'coachMessage' => $coachMessage,
            'mascotName' => 'Lorie',
            'foodFact' => $foodFact->today(),
        ]);
    }

    private function greeting(): string
    {
        return match (true) {
            now()->hour < 12 => 'Good morning',
            now()->hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    /**
     * Count consecutive days (ending today, or yesterday if today isn't logged
     * yet) that have at least one food entry.
     */
    private function loggingStreak(User $user): int
    {
        $logged = $user->foodEntries()
            ->select('consumed_on')
            ->distinct()
            ->pluck('consumed_on')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();

        if ($logged === []) {
            return 0;
        }

        $set = array_flip($logged);

        // Grace period: don't reset the streak just because today isn't logged yet.
        $cursor = isset($set[today()->toDateString()]) ? today() : today()->subDay();

        $streak = 0;
        while (isset($set[$cursor->toDateString()])) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    private function coachMessage(int $count, int $remaining, int $percent, string $name): string
    {
        return match (true) {
            $count === 0 => "Fresh start, {$name}! Log your first meal and I'll crunch the numbers for you.",
            $remaining < 0 => 'Lampas na tayo by '.number_format(abs($remaining)).' kcal today — no stress, bawi tayo bukas.',
            $percent >= 80 => "You're close to your goal — maybe keep the next bite light, ha?",
            default => "You've got ".number_format($remaining).' kcal left today. Steady lang, you\'re pacing well!',
        };
    }
}
