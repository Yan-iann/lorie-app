<?php

namespace App\Support;

use App\Models\FoodEntry;
use App\Models\User;
use App\Models\WaterLog;
use Illuminate\Support\Carbon;

/**
 * The last seven days of calories and water (ending today) for the
 * dashboard's "This week" strip.
 *
 * Calories and water are different units with different goals, so each is
 * drawn as a share of its own goal. That puts both on one scale with a single
 * goal line at 100%.
 */
class WeekSummary
{
    private const DAYS = 7;

    /**
     * @param  list<array{date: string, label: string, calories: int, waterMl: int, logged: bool, over: bool, isToday: bool, isViewed: bool}>  $days  oldest first
     */
    public function __construct(
        public readonly array $days,
        public readonly int $goal,
        public readonly int $waterGoalMl,
    ) {}

    /**
     * @param  string  $viewedDate  the day the dashboard is showing, so the strip can mark it
     */
    public static function for(User $user, string $viewedDate): self
    {
        $start = today()->subDays(self::DAYS - 1);
        $from = $start->toDateString();
        $to = today()->toDateString();

        $calories = $user->foodEntries()
            ->whereDate('consumed_on', '>=', $from)
            ->whereDate('consumed_on', '<=', $to)
            ->selectRaw('consumed_on, SUM(calories) as total_calories')
            ->groupBy('consumed_on')
            ->get()
            ->mapWithKeys(fn (FoodEntry $day) => [$day->consumed_on->toDateString() => (int) $day->total_calories]);

        $water = $user->waterLogs()
            ->whereDate('consumed_on', '>=', $from)
            ->whereDate('consumed_on', '<=', $to)
            ->selectRaw('consumed_on, SUM(amount_ml) as total_ml')
            ->groupBy('consumed_on')
            ->get()
            ->mapWithKeys(fn (WaterLog $day) => [$day->consumed_on->toDateString() => (int) $day->total_ml]);

        $goal = $user->daily_goal;
        $days = [];

        for ($offset = 0; $offset < self::DAYS; $offset++) {
            $day = $start->copy()->addDays($offset);
            $date = $day->toDateString();

            $days[] = [
                'date' => $date,
                'label' => $day->format('D'),
                'calories' => $calories->get($date, 0),
                'waterMl' => $water->get($date, 0),
                'logged' => $calories->has($date),
                'over' => $calories->get($date, 0) > $goal,
                'isToday' => $day->isToday(),
                'isViewed' => $date === $viewedDate,
            ];
        }

        return new self($days, $goal, $user->waterGoalMl());
    }

    /**
     * Days with any food logged.
     */
    public function loggedDays(): int
    {
        return count(array_filter($this->days, fn (array $day) => $day['logged']));
    }

    /**
     * Average calories across the days that have food logged.
     */
    public function averageCalories(): int
    {
        $logged = array_filter($this->days, fn (array $day) => $day['logged']);

        return $logged === [] ? 0 : (int) round(array_sum(array_column($logged, 'calories')) / count($logged));
    }

    /**
     * How tall to draw a day's calorie bar, as a percentage of the chart's height.
     */
    public function calorieHeight(int $calories): float
    {
        return $this->height($this->goal > 0 ? $calories / $this->goal : 0);
    }

    /**
     * How tall to draw a day's water bar, as a percentage of the chart's height.
     */
    public function waterHeight(int $waterMl): float
    {
        return $this->height($this->waterGoalMl > 0 ? $waterMl / $this->waterGoalMl : 0);
    }

    /**
     * Where the goal line sits, as a percentage up from the bottom. It is the
     * top of the chart unless some day went over one of its goals.
     */
    public function goalHeight(): float
    {
        return $this->height(1);
    }

    /**
     * Turn "share of goal" into a percentage of the chart's height. The chart
     * is as tall as the biggest share drawn, and never shorter than the goal.
     */
    private function height(float $shareOfGoal): float
    {
        $tallest = 1.0;

        foreach ($this->days as $day) {
            $tallest = max(
                $tallest,
                $this->goal > 0 ? $day['calories'] / $this->goal : 0,
                $this->waterGoalMl > 0 ? $day['waterMl'] / $this->waterGoalMl : 0,
            );
        }

        return round($shareOfGoal / $tallest * 100, 1);
    }

    /**
     * One line describing a day, for tooltips and screen readers.
     */
    public static function describe(array $day): string
    {
        $food = $day['logged'] ? number_format($day['calories']).' kcal' : 'no food logged';
        $water = $day['waterMl'] > 0 ? WaterCalculator::litres($day['waterMl']).' water' : 'no water';

        return Carbon::parse($day['date'])->format('D, M j').": {$food}, {$water}";
    }
}
