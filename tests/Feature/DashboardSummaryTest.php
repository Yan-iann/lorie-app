<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DailyFoodFact;
use App\Support\WeekSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();
        $this->travelTo('2026-10-13 14:00');
        Cache::put(DailyFoodFact::key(), 'Bananas are berries.', now()->addDay());

        $this->user = User::factory()->onboarded()->create(['daily_goal' => 2000, 'weight_kg' => 70]);
        $this->actingAs($this->user);
    }

    private function eat(int $calories, string $date): void
    {
        $this->user->foodEntries()->create(['name' => 'Meal', 'calories' => $calories, 'consumed_on' => $date]);
    }

    public function test_calories_and_water_are_summarised_together(): void
    {
        $this->eat(1300, '2026-10-13');
        $this->user->waterLogs()->create(['amount_ml' => 1000, 'container' => 'large-bottle', 'consumed_on' => '2026-10-13']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Calories', '65%', '1,300', 'of 2,000 kcal', '700 kcal left', 'Water', '1 L', 'of 2.8 L', '1.8 L to go']);
    }

    public function test_going_over_is_called_out(): void
    {
        $this->eat(2350, '2026-10-13');

        $this->get(route('dashboard'))->assertSee('350 kcal over')->assertDontSee('kcal left');
    }

    public function test_the_date_is_shown_once(): void
    {
        $response = $this->get(route('dashboard'))->assertOk()->assertSee('Tuesday, Oct 13');

        // The long form that used to sit above the greeting is gone.
        $response->assertDontSee('Tuesday, October 13');
    }

    public function test_the_week_covers_the_last_seven_days_ending_today(): void
    {
        $this->eat(1800, '2026-10-13');
        $this->eat(2600, '2026-10-11');
        $this->eat(400, '2026-10-11');
        $this->eat(1500, '2026-10-07');
        $this->eat(9999, '2026-10-06'); // eight days ago: outside the window

        $week = WeekSummary::for($this->user, '2026-10-11');

        $this->assertSame(['2026-10-07', '2026-10-08', '2026-10-09', '2026-10-10', '2026-10-11', '2026-10-12', '2026-10-13'], array_column($week->days, 'date'));
        $this->assertSame([1500, 0, 0, 0, 3000, 0, 1800], array_column($week->days, 'calories'));
        $this->assertSame([true, false, false, false, true, false, true], array_column($week->days, 'logged'));
        $this->assertSame([false, false, false, false, true, false, false], array_column($week->days, 'over'));

        $this->assertTrue($week->days[6]['isToday']);
        $this->assertTrue($week->days[4]['isViewed']);

        $this->assertSame(3, $week->loggedDays());
        $this->assertSame(2100, $week->averageCalories());

        // Bars are drawn against the tallest day; the goal line sits two thirds of the way up.
        $this->assertSame(100.0, $week->barHeight(3000));
        $this->assertSame(66.7, $week->goalHeight());
    }

    public function test_the_week_strip_is_on_the_dashboard_and_links_to_each_day(): void
    {
        $this->eat(1800, '2026-10-12');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('This week')
            ->assertSee('1 of 7 days · avg 1,800 kcal')
            ->assertSee(route('dashboard', ['date' => '2026-10-12']), false)
            ->assertSee('Mon, Oct 12: 1,800 kcal');
    }

    public function test_the_week_belongs_to_the_signed_in_user(): void
    {
        $someoneElse = User::factory()->onboarded()->create();
        $someoneElse->foodEntries()->create(['name' => 'Theirs', 'calories' => 1234, 'consumed_on' => '2026-10-12']);

        $this->get(route('dashboard'))->assertSee('Nothing logged yet')->assertDontSee('1,234');
    }
}
