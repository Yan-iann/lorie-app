<?php

namespace App\Support;

use App\Models\FoodEntry;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The last seven days of calories (ending today) for the dashboard's
 * "This week" strip.
 */
class WeekSummary
{
    private const DAYS = 7;

    /**
     * @param  list<array{date: string, label: string, calories: int, logged: bool, over: bool, isToday: bool, isViewed: bool}>  $days  oldest first
     */
    public function __construct(
        public readonly array $days,
        public readonly int $goal,
    ) {}

    /**
     * @param  string  $viewedDate  the day the dashboard is showing, so the strip can mark it
     */
    public static function for(User $user, string $viewedDate): self
    {
        $start = today()->subDays(self::DAYS - 1);

        $totals = $user->foodEntries()
            ->whereDate('consumed_on', '>=', $start->toDateString())
            ->whereDate('consumed_on', '<=', today()->toDateString())
            ->selectRaw('consumed_on, SUM(calories) as total_calories')
            ->groupBy('consumed_on')
            ->get()
            ->mapWithKeys(fn (FoodEntry $day) => [$day->consumed_on->toDateString() => (int) $day->total_calories]);

        $goal = $user->daily_goal;
        $days = [];

        for ($offset = 0; $offset < self::DAYS; $offset++) {
            $day = $start->copy()->addDays($offset);
            $date = $day->toDateString();
            $calories = $totals->get($date, 0);

            $days[] = [
                'date' => $date,
                'label' => $day->format('D'),
                'calories' => $calories,
                'logged' => $totals->has($date),
                'over' => $calories > $goal,
                'isToday' => $day->isToday(),
                'isViewed' => $date === $viewedDate,
            ];
        }

        return new self($days, $goal);
    }

    public function loggedDays(): int
    {
        return count(array_filter($this->days, fn (array $day) => $day['logged']));
    }

    /**
     * Average calories across the days that have anything logged.
     */
    public function averageCalories(): int
    {
        $logged = array_filter($this->days, fn (array $day) => $day['logged']);

        return $logged === [] ? 0 : (int) round(array_sum(array_column($logged, 'calories')) / count($logged));
    }

    /**
     * The value the tallest bar (or the goal line, if higher) is drawn against.
     */
    public function scale(): int
    {
        return max(1, $this->goal, ...array_column($this->days, 'calories'));
    }

    /**
     * How tall to draw a day's bar, as a percentage of the chart's height.
     */
    public function barHeight(int $calories): float
    {
        return round($calories / $this->scale() * 100, 1);
    }

    /**
     * Where the goal line sits, as a percentage up from the bottom.
     */
    public function goalHeight(): float
    {
        return round($this->goal / $this->scale() * 100, 1);
    }

    public static function describe(string $date): string
    {
        return Carbon::parse($date)->format('D, M j');
    }
}
