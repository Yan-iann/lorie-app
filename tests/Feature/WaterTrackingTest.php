<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DailyFoodFact;
use App\Support\DailyEntryAllowance;
use App\Support\WaterCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WaterTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Logging water must never call an outside service.
        Http::preventStrayRequests();

        Cache::put(DailyFoodFact::key(), 'Bananas are berries.', now()->addDay());

        // 70 kg, moderately active: 70 × 33 + 500 = 2,810 → a 2.8 L goal.
        $this->user = User::factory()->onboarded()->create(['weight_kg' => 70, 'activity_level' => 'moderate']);
        $this->actingAs($this->user);
    }

    public function test_the_goal_is_worked_out_from_weight_and_activity(): void
    {
        $this->assertSame(2800, WaterCalculator::recommendedGoalMl('male', 70, 'moderate'));
        $this->assertSame(2300, WaterCalculator::recommendedGoalMl('female', 70, 'sedentary'));
        $this->assertSame(3300, WaterCalculator::recommendedGoalMl('male', 70, 'athlete'));

        // Without a weight it falls back to a typical figure by gender.
        $this->assertSame(2500, WaterCalculator::recommendedGoalMl('male'));
        $this->assertSame(2000, WaterCalculator::recommendedGoalMl('female'));

        // Kept within a sane range.
        $this->assertSame(1500, WaterCalculator::recommendedGoalMl('female', 30, 'sedentary'));
        $this->assertSame(5000, WaterCalculator::recommendedGoalMl('male', 200, 'athlete'));
    }

    public function test_amounts_are_written_for_people(): void
    {
        $this->assertSame('150 ml', WaterCalculator::format(150));
        $this->assertSame('1 L', WaterCalculator::format(1000));
        $this->assertSame('2.75 L', WaterCalculator::litres(2750));
        $this->assertSame('0.4 L', WaterCalculator::litres(400));
    }

    public function test_tapping_a_container_logs_that_much_water(): void
    {
        $this->post(route('water.store'), ['container' => 'medium-cup'])
            ->assertRedirect(route('dashboard', ['date' => today()->toDateString()]))
            ->assertSessionHas('success', 'Added 250 ml of water.');

        $this->post(route('water.store'), ['container' => 'bottle'])->assertRedirect();

        $this->assertSame(750, (int) $this->user->waterLogs()->sum('amount_ml'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('0.75 L')
            ->assertSee('of 2.8 L')
            ->assertSee('2.05 L to go')
            ->assertSee('2 drinks');
    }

    public function test_background_taps_get_the_redrawn_water_parts_and_a_toast_back(): void
    {
        $response = $this->postJson(route('water.store'), ['container' => 'large-bottle'])->assertOk();

        $response->assertJsonStructure(['parts', 'toast', 'coach']);
        // Lorie's line reflects the water just logged (1 L of a 2.8 L goal).
        $this->assertStringContainsString('1.8 L pa ng water to go', $response->json('coach'));
        $this->assertStringContainsString('data-water-part="gauge"', $response->json('parts'));
        $this->assertStringContainsString('data-water-part="controls"', $response->json('parts'));
        // The weekly chart comes back too, already showing the new drink.
        $this->assertStringContainsString('data-water-part="week"', $response->json('parts'));
        $this->assertStringContainsString('no food logged, 1 L water', $response->json('parts'));
        $this->assertStringContainsString('1 L', $response->json('parts'));
        $this->assertStringContainsString('Added 1 L of water.', $response->json('toast'));
    }

    public function test_an_unknown_container_is_rejected(): void
    {
        $this->post(route('water.store'), ['container' => 'bathtub'])->assertSessionHasErrors('container');
        $this->post(route('water.store'), [])->assertSessionHasErrors('container');

        $this->assertDatabaseCount('water_logs', 0);
    }

    public function test_reaching_the_goal_is_announced_once(): void
    {
        $this->user->update(['daily_water_goal_ml' => 1000]);

        $this->post(route('water.store'), ['container' => 'bottle'])->assertSessionHas('success', 'Added 500 ml of water.');
        $this->post(route('water.store'), ['container' => 'bottle'])->assertSessionHas('success', 'Added 500 ml of water. You hit your water goal!');
        $this->post(route('water.store'), ['container' => 'small-cup'])->assertSessionHas('success', 'Added 150 ml of water.');

        $this->get(route('dashboard'))->assertSee('Goal reached')->assertSee('115%');
    }

    public function test_water_is_kept_per_day(): void
    {
        $this->post(route('water.store'), ['container' => 'bottle', 'consumed_on' => '2026-10-10']);
        $this->post(route('water.store'), ['container' => 'small-cup']);

        $this->get(route('dashboard', ['date' => '2026-10-10']))->assertSee('0.5 L')->assertSee('1 drink');
        $this->get(route('dashboard'))->assertSee('0.15 L');
    }

    public function test_a_drink_can_be_removed(): void
    {
        $this->post(route('water.store'), ['container' => 'bottle']);
        $log = $this->user->waterLogs()->firstOrFail();

        $this->delete(route('water.destroy', $log))
            ->assertRedirect()
            ->assertSessionHas('success', 'Removed 500 ml of water.');

        $this->assertModelMissing($log);
    }

    public function test_undo_removes_the_most_recent_drink(): void
    {
        $this->post(route('water.store'), ['container' => 'small-cup']);
        $this->post(route('water.store'), ['container' => 'large-bottle']);

        $latest = $this->user->waterLogs()->latest('id')->firstOrFail();

        // The card's Undo button points at the newest drink.
        $this->get(route('dashboard'))->assertSee(route('water.destroy', $latest), false);

        $this->deleteJson(route('water.destroy', $latest))->assertOk();

        $this->assertSame(150, (int) $this->user->waterLogs()->sum('amount_ml'));
    }

    public function test_water_is_private_to_each_person(): void
    {
        $someoneElse = User::factory()->onboarded()->create();
        $theirs = $someoneElse->waterLogs()->create(['amount_ml' => 500, 'container' => 'bottle', 'consumed_on' => today()]);

        $this->get(route('dashboard'))->assertSee('0 L')->assertDontSee('1 drink');

        $this->delete(route('water.destroy', $theirs))->assertForbidden();
        $this->assertModelExists($theirs);
    }

    public function test_guests_cannot_log_water(): void
    {
        auth()->logout();

        $this->post(route('water.store'), ['container' => 'bottle'])->assertRedirect(route('login'));
        $this->assertDatabaseCount('water_logs', 0);
    }

    public function test_water_does_not_use_up_food_entries(): void
    {
        config()->set('services.ai.daily_entries_per_user', 2);

        foreach (range(1, 5) as $drink) {
            $this->post(route('water.store'), ['container' => 'small-cup'])->assertSessionHas('success');
        }

        $this->assertSame(2, app(DailyEntryAllowance::class)->remaining($this->user));
    }

    public function test_the_plus_popup_offers_water_even_when_food_entries_are_used_up(): void
    {
        config()->set('services.ai.daily_entries_per_user', 1);

        $this->get(route('dashboard'))->assertSee('Log water')->assertSee('Type it');

        app(DailyEntryAllowance::class)->record($this->user);

        $this->get(route('dashboard'))->assertSee('all for today')->assertSee('Log water')->assertDontSee('Type it');
    }

    public function test_the_goal_can_be_set_by_hand_or_left_to_the_suggestion(): void
    {
        $profile = ['display_name' => 'Edrian', 'gender' => 'male', 'weight_kg' => 70, 'activity_level' => 'moderate', 'daily_goal' => 2500];

        $this->put(route('profile.update'), [...$profile, 'daily_water_goal_l' => '3.5'])->assertSessionHasNoErrors();
        $this->assertSame(3500, $this->user->refresh()->waterGoalMl());

        // Clearing the field goes back to the suggested amount.
        $this->put(route('profile.update'), [...$profile, 'daily_water_goal_l' => ''])->assertSessionHasNoErrors();
        $this->assertNull($this->user->refresh()->daily_water_goal_ml);
        $this->assertSame(2800, $this->user->waterGoalMl());

        $this->put(route('profile.update'), [...$profile, 'daily_water_goal_l' => '40'])->assertSessionHasErrors('daily_water_goal_l');
    }

    public function test_history_shows_water_beside_each_day(): void
    {
        $this->user->foodEntries()->create(['name' => 'Rice', 'calories' => 300, 'consumed_on' => today()]);
        $this->user->foodEntries()->create(['name' => 'Eggs', 'calories' => 200, 'consumed_on' => today()->subDay()]);

        $this->user->waterLogs()->create(['amount_ml' => 500, 'container' => 'bottle', 'consumed_on' => today()]);
        $this->user->waterLogs()->create(['amount_ml' => 250, 'container' => 'medium-cup', 'consumed_on' => today()]);

        $this->get(route('history'))
            ->assertOk()
            ->assertSee('0.75 L water')
            ->assertSeeInOrder(['Today', '0.75 L water', 'Yesterday']);
    }
}
