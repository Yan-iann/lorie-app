<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DailyFoodFact;
use App\Support\LorieCoach;
use App\Support\WaterDay;
use App\Support\WeekSummary;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request, DailyFoodFact $foodFact, LorieCoach $lorie): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasCompletedProfile()) {
            return redirect()->route('profile');
        }

        // Fall back to today for a missing, malformed or impossible ?date=
        // (e.g. Feb 31, which Postgres refuses outright) instead of erroring.
        $requested = $request->query('date');
        $date = is_string($requested) && $this->isRealDate($requested)
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
        $water = WaterDay::for($user, $date);
        $coachMessage = $lorie->message($user, $entries->count(), $remaining, $percent, $water);

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
            'water' => $water,
            'week' => WeekSummary::for($user, $date),
        ]);
    }

    /**
     * A YYYY-MM-DD string naming a day that actually exists.
     */
    private function isRealDate(string $value): bool
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
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
}
