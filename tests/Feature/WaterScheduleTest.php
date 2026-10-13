<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DailyFoodFact;
use App\Support\WaterDay;
use App\Support\WaterSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Throughout: a 2 L goal paced over 7:00 AM – 9:00 PM. That is 840 minutes,
 * so 250 ml covers 1 h 45 m and 500 ml covers 3 h 30 m.
 */
class WaterScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();

        $this->user = User::factory()->onboarded()->create(['daily_water_goal_ml' => 2000]);
        $this->actingAs($this->user);
    }

    private function at(string $time): void
    {
        $this->travelTo("2026-10-13 {$time}");
        Cache::put(DailyFoodFact::key(), 'Bananas are berries.', now()->addDay());
    }

    private function schedule(int $drunkMl): WaterSchedule
    {
        return WaterSchedule::for($this->user, 2000, $drunkMl);
    }

    private function drink(int $ml, string $container = 'bottle'): void
    {
        $this->user->waterLogs()->create(['amount_ml' => $ml, 'container' => $container, 'consumed_on' => today()]);
    }

    /** @param list<Carbon> $times */
    private function clock(array $times): array
    {
        return array_map(fn (Carbon $time) => $time->format('H:i'), $times);
    }

    public function test_a_bigger_drink_buys_a_longer_gap(): void
    {
        $this->at('09:00');
        $schedule = $this->schedule(0);

        $this->assertSame(63, $schedule->minutesFor(150));
        $this->assertSame(105, $schedule->minutesFor(250));
        $this->assertSame(210, $schedule->minutesFor(500));
        $this->assertSame(420, $schedule->minutesFor(1000));
    }

    public function test_the_next_drink_is_due_when_the_pace_catches_up_with_what_was_drunk(): void
    {
        // 750 ml covers 315 minutes from 7:00 → on pace until 12:15.
        $this->at('10:30');
        $schedule = $this->schedule(750);

        $this->assertSame(WaterSchedule::ON_TRACK, $schedule->status());
        $this->assertSame('12:15', $schedule->nextDrinkAt()->format('H:i'));
        $this->assertSame(0, $schedule->behindMl());

        // Drinking a 500 ml bottle now pushes it out by 3 h 30 m.
        $this->assertSame('15:45', $this->schedule(1250)->nextDrinkAt()->format('H:i'));
    }

    public function test_falling_behind_means_drink_now(): void
    {
        // Half the window has gone, so 1 L was expected; only 500 ml drunk.
        $this->at('14:00');
        $schedule = $this->schedule(500);

        $this->assertSame(WaterSchedule::BEHIND, $schedule->status());
        $this->assertSame(1000, $schedule->expectedMl());
        $this->assertSame(500, $schedule->behindMl());
        $this->assertSame('14:00', $schedule->nextDrinkAt()->format('H:i'));
    }

    public function test_following_the_plan_reaches_the_goal_inside_the_window(): void
    {
        // On pace: each drink comes one drink's worth of time after the last.
        $this->at('10:30');
        $plan = $this->schedule(750)->plan(250);

        $this->assertSame(['12:15', '14:00', '15:45', '17:30', '19:15'], $this->clock($plan));
        $this->assertSame(2000, 750 + count($plan) * 250);

        // Behind: start now, and squeeze the rest evenly into the time left.
        $this->at('14:00');
        $plan = $this->schedule(500)->plan(250);

        $this->assertSame(['14:00', '15:10', '16:20', '17:30', '18:40', '19:50'], $this->clock($plan));
        $this->assertSame(2000, 500 + count($plan) * 250);

        // Bigger drinks, fewer of them, further apart.
        $this->assertSame(['14:00', '16:20', '18:40'], $this->clock($this->schedule(500)->plan(500)));
    }

    public function test_the_edges_of_the_day(): void
    {
        $this->at('06:00');
        $this->assertSame(WaterSchedule::BEFORE, $this->schedule(0)->status());
        $this->assertSame('07:00', $this->schedule(0)->nextDrinkAt()->format('H:i'));

        $this->at('15:00');
        $this->assertSame(WaterSchedule::DONE, $this->schedule(2000)->status());
        $this->assertNull($this->schedule(2000)->nextDrinkAt());
        $this->assertSame([], $this->schedule(2000)->plan(250));

        $this->at('21:30');
        $this->assertSame(WaterSchedule::ENDED, $this->schedule(1500)->status());
        $this->assertSame(500, $this->schedule(1500)->remainingMl());
        $this->assertSame([], $this->schedule(1500)->plan(250));
    }

    public function test_lengths_of_time_are_written_for_people(): void
    {
        $this->assertSame('45m', WaterSchedule::duration(45));
        $this->assertSame('2h', WaterSchedule::duration(120));
        $this->assertSame('1h 45m', WaterSchedule::duration(105));
    }

    public function test_the_dashboard_says_when_to_drink_next(): void
    {
        $this->at('10:30');
        $this->drink(750);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Next drink by', '12:15 PM', '(in 1h 45m)']);

        $this->at('14:00');

        $this->get(route('dashboard'))->assertSee('Time to drink.')->assertSee('250 ml behind');
    }

    public function test_only_today_has_a_schedule(): void
    {
        $this->at('10:30');

        $this->assertNotNull(WaterDay::for($this->user, '2026-10-13')->schedule);
        $this->assertNull(WaterDay::for($this->user, '2026-10-12')->schedule);

        $this->get(route('dashboard', ['date' => '2026-10-12']))->assertOk()->assertDontSee('Time to drink.')->assertDontSee('Next drink by');
    }

    public function test_the_cup_picker_shows_what_each_drink_covers_and_the_plan(): void
    {
        $this->at('10:30');
        $this->drink(750, 'medium-cup');
        $this->user->waterLogs()->latest('id')->first()->update(['amount_ml' => 250]);
        $this->drink(500);

        // 750 ml drunk; the last drink was a 500 ml bottle, so the plan uses that size.
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['150 ml', '1h 3m', '250 ml', '1h 45m', '350 ml', '2h 27m', '500 ml', '3h 30m', '1 L', '7h'])
            ->assertSee('To finish today with 500 ml drinks')
            ->assertSeeInOrder(['12:15 PM', '3:45 PM', '7:15 PM']);
    }

    public function test_logging_a_drink_returns_the_updated_schedule(): void
    {
        $this->at('10:30');
        $this->drink(750);

        $parts = $this->postJson(route('water.store'), ['container' => 'bottle'])->assertOk()->json('parts');

        $this->assertStringContainsString('data-water-part="schedule"', $parts);
        $this->assertStringContainsString('3:45 PM', $parts);
    }

    public function test_drinking_hours_are_set_on_the_profile(): void
    {
        $profile = ['display_name' => 'Edrian', 'gender' => 'male', 'daily_goal' => 2000];

        $this->assertSame(['07:00', '21:00'], [$this->user->water_starts_at, $this->user->water_ends_at]);

        $this->put(route('profile.update'), [...$profile, 'water_starts_at' => '06:00', 'water_ends_at' => '22:00'])->assertSessionHasNoErrors();
        $this->assertSame(['06:00', '22:00'], [$this->user->refresh()->water_starts_at, $this->user->water_ends_at]);

        // 16 hours instead of 14: the same cup now covers more time.
        $this->at('09:00');
        $this->assertSame(120, WaterSchedule::for($this->user, 2000, 0)->minutesFor(250));

        $this->put(route('profile.update'), [...$profile, 'water_starts_at' => '20:00', 'water_ends_at' => '08:00'])->assertSessionHasErrors('water_ends_at');
        $this->put(route('profile.update'), [...$profile, 'water_starts_at' => 'morning'])->assertSessionHasErrors('water_starts_at');
    }
}
