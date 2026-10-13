<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DailyFoodFact;
use App\Support\DailyEntryAllowance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DailyEntryLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();

        config()->set('services.ai.provider', 'groq');
        config()->set('services.ai.fallback', null);
        config()->set('services.groq.key', 'test-key');
        config()->set('services.usda.key', 'TEST_KEY');

        // A small cap keeps these tests short; the real default is checked separately.
        config()->set('services.ai.daily_entries_per_user', 2);

        Cache::put(DailyFoodFact::key(), 'Bananas are berries.', now()->addDay());

        $this->user = User::factory()->onboarded()->create();
        $this->actingAs($this->user);

        // The AI answers every decomposition and verification; USDA has no match.
        Http::fake([
            'api.groq.com/*' => fn (Request $request) => Http::response(['choices' => [['message' => ['content' => json_encode(
                str_contains($request['messages'][1]['content'], 'judge whether')
                    ? ['confidence' => 'high', 'reasonable' => true, 'note' => 'Looks right.']
                    : ['dish_name' => 'Apple', 'ingredients' => [['name' => 'Apple', 'usda_query' => 'apple raw', 'grams' => 100, 'est_calories' => 52, 'est_protein_g' => 0.3, 'est_carbs_g' => 14, 'est_fat_g' => 0.2]]]
            )]]]]),
            'api.nal.usda.gov/*' => Http::response(['foods' => []]),
        ]);
    }

    private function addEntry(): TestResponse
    {
        return $this->post(route('entries.store'), ['description' => 'an apple']);
    }

    private function aiRequests(): int
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), 'api.groq.com'))->count();
    }

    public function test_the_default_cap_is_fifteen_entries_a_day(): void
    {
        $this->assertSame(15, (int) (require config_path('services.php'))['ai']['daily_entries_per_user']);
    }

    public function test_entries_are_allowed_up_to_the_cap_and_refused_after_it(): void
    {
        $this->addEntry()->assertSessionHas('success', 'Added Apple — 52 kcal. 1 entry left today.');
        $this->addEntry()->assertSessionHas('success', "Added Apple — 52 kcal. That's your last entry for today.");

        $spent = $this->aiRequests();

        $this->addEntry()->assertRedirect()->assertSessionHas('error');

        $this->assertSame(2, $this->user->foodEntries()->count());
        // Refused before any AI work was done.
        $this->assertSame($spent, $this->aiRequests());
    }

    public function test_photo_entries_count_toward_the_same_cap(): void
    {
        $this->addEntry();
        $this->addEntry();

        $spent = $this->aiRequests();

        $this->post(route('entries.photo'), ['photo' => UploadedFile::fake()->image('lunch.jpg', 320, 240)])
            ->assertSessionHas('error');

        $this->assertSame($spent, $this->aiRequests());
    }

    public function test_deleting_an_entry_does_not_give_the_slot_back(): void
    {
        $this->addEntry();
        $this->addEntry();

        $this->delete(route('entries.destroy', $this->user->foodEntries()->first()))->assertRedirect();

        $this->addEntry()->assertSessionHas('error');
        $this->assertSame(1, $this->user->foodEntries()->count());
    }

    public function test_a_failed_attempt_does_not_use_up_an_entry(): void
    {
        $this->post(route('entries.store'), ['description' => ''])->assertSessionHasErrors('description');

        $this->assertSame(2, app(DailyEntryAllowance::class)->remaining($this->user));
    }

    public function test_the_cap_is_per_person(): void
    {
        $this->addEntry();
        $this->addEntry();

        $someoneElse = User::factory()->onboarded()->create();

        $this->actingAs($someoneElse);
        $this->addEntry()->assertSessionHas('success');

        $this->assertSame(1, $someoneElse->foodEntries()->count());
    }

    public function test_the_count_starts_again_the_next_day(): void
    {
        $this->addEntry();
        $this->addEntry();
        $this->addEntry()->assertSessionHas('error');

        $this->travelTo(now()->addDay());
        Cache::put(DailyFoodFact::key(), 'Bananas are berries.', now()->addDay());

        $this->addEntry()->assertSessionHas('success');
        $this->assertSame(1, app(DailyEntryAllowance::class)->remaining($this->user));
    }

    public function test_the_popup_shows_what_is_left_and_closes_off_when_nothing_is(): void
    {
        $this->get(route('dashboard'))->assertOk()->assertSee('2 of 2 entries left today')->assertSee('Type it');

        $this->addEntry();
        $this->get(route('dashboard'))->assertSee('1 of 2 entries left today');

        $this->addEntry();
        $this->get(route('dashboard'))
            ->assertSee('all for today')
            ->assertDontSee('Type it')
            ->assertDontSee('Take a photo');
    }

    public function test_a_cap_of_zero_means_no_limit(): void
    {
        config()->set('services.ai.daily_entries_per_user', 0);

        foreach (range(1, 4) as $attempt) {
            $this->addEntry()->assertSessionHas('success', 'Added Apple — 52 kcal.');
        }

        $this->get(route('dashboard'))->assertOk()->assertDontSee('entries left today');
    }
}
