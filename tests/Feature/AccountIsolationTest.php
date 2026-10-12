<?php

namespace Tests\Feature;

use App\Models\FoodEntry;
use App\Models\Setting;
use App\Models\User;
use App\Services\DailyTip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccountIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();

        // Pre-seed today's tip so page renders don't call the AI.
        Cache::put(DailyTip::key(), 'Fill half your plate with vegetables.', now()->addDay());
    }

    /** @return array<string, string> */
    private function registration(string $email): array
    {
        return [
            'name' => 'Tester',
            'email' => $email,
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ];
    }

    public function test_each_user_only_sees_their_own_entries(): void
    {
        $alice = User::factory()->onboarded()->create();
        $bob = User::factory()->onboarded()->create();

        $alice->foodEntries()->create(['name' => 'Alice adobo', 'calories' => 510, 'consumed_on' => today()]);
        $bob->foodEntries()->create(['name' => 'Bob burger', 'calories' => 930, 'consumed_on' => today()]);

        $this->actingAs($alice)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Alice adobo')
            ->assertDontSee('Bob burger');

        // History totals are per account too: 510, not 510 + 930.
        $this->actingAs($alice)->get(route('history'))
            ->assertOk()
            ->assertSee('510')
            ->assertDontSee('1,440');
    }

    public function test_goals_and_streaks_are_per_user(): void
    {
        $alice = User::factory()->onboarded()->create(['name' => 'Alice', 'daily_goal' => 1500]);
        $bob = User::factory()->onboarded()->create(['name' => 'Bob', 'daily_goal' => 3000]);

        $bob->foodEntries()->create(['name' => 'Bob burger', 'calories' => 930, 'consumed_on' => today()]);

        $this->actingAs($alice)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Alice')
            ->assertSee('of 1,500 kcal')
            ->assertSee('Start your streak');

        $this->actingAs($bob)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('of 3,000 kcal')
            ->assertSee('x1 day streak!');
    }

    public function test_a_user_cannot_delete_someone_elses_entry(): void
    {
        $alice = User::factory()->onboarded()->create();
        $bob = User::factory()->onboarded()->create();

        $entry = $bob->foodEntries()->create(['name' => 'Bob burger', 'calories' => 930, 'consumed_on' => today()]);

        $this->actingAs($alice)->delete(route('entries.destroy', $entry))->assertForbidden();

        $this->assertModelExists($entry);
    }

    public function test_the_first_account_inherits_data_from_before_accounts_existed(): void
    {
        // The single-user era: an unowned food log and a key/value profile.
        $legacyEntry = FoodEntry::create(['name' => 'Old adobo', 'calories' => 500, 'consumed_on' => today()]);

        foreach ([
            'profile_completed' => '1',
            'display_name' => 'Edrian',
            'gender' => 'male',
            'height_cm' => '175',
            'weight_kg' => '',
            'activity_level' => 'active',
            'daily_goal' => '2600',
        ] as $key => $value) {
            Setting::create(['key' => $key, 'value' => $value]);
        }

        $this->post(route('register.store'), $this->registration('first@example.com'))->assertRedirect(route('dashboard'));

        $first = User::query()->where('email', 'first@example.com')->firstOrFail();

        $this->assertSame($first->id, $legacyEntry->refresh()->user_id);
        $this->assertTrue($first->hasCompletedProfile());
        $this->assertSame('male', $first->gender);
        $this->assertSame(175.0, $first->height_cm);
        $this->assertNull($first->weight_kg);
        $this->assertSame('active', $first->activity_level);
        $this->assertSame(2600, $first->daily_goal);
        $this->assertDatabaseCount('settings', 0);

        // Already onboarded, so they land straight on their old log.
        $this->get(route('dashboard'))->assertOk()->assertSee('Old adobo');
    }

    public function test_later_accounts_start_empty(): void
    {
        $first = User::factory()->onboarded()->create();
        $entry = $first->foodEntries()->create(['name' => 'Mine', 'calories' => 500, 'consumed_on' => today()]);
        $orphan = FoodEntry::create(['name' => 'Unowned', 'calories' => 100, 'consumed_on' => today()]);

        $this->post(route('register.store'), $this->registration('second@example.com'))->assertRedirect(route('dashboard'));

        $second = User::query()->where('email', 'second@example.com')->firstOrFail();

        $this->assertFalse($second->hasCompletedProfile());
        $this->assertSame(0, $second->foodEntries()->count());
        $this->assertSame($first->id, $entry->refresh()->user_id);
        $this->assertNull($orphan->refresh()->user_id);
    }
}
