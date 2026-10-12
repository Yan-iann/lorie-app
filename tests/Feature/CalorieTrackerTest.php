<?php

namespace Tests\Feature;

use App\Models\FoodEntry;
use App\Models\User;
use App\Services\DailyTip;
use App\Services\Usda\UsdaFoodClient;
use App\Support\CalorieCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalorieTrackerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Page renders shouldn't depend on a built Vite manifest.
        $this->withoutVite();

        config()->set('services.ai.provider', 'groq');
        config()->set('services.groq.key', 'test-key');
        config()->set('services.groq.model', 'llama-3.3-70b-versatile');
        config()->set('services.groq.vision_model', 'test-vision-model');
        config()->set('services.usda.key', 'TEST_KEY');

        // Signed in as an onboarded user by default; onboarding tests use a fresh one.
        $this->user = User::factory()->onboarded()->create();
        $this->actingAs($this->user);

        // Pre-seed today's tip so page renders don't call the AI.
        Cache::put(DailyTip::key(), 'Fill half your plate with vegetables.', now()->addDay());
    }

    /** @return array<string, mixed> */
    private function ai(array $json): array
    {
        return $this->aiText(json_encode($json));
    }

    /** @return array<string, mixed> */
    private function aiText(string $content): array
    {
        return [
            'choices' => [
                ['message' => ['content' => $content]],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function usda(array $nutrientsByNumber, string $description = 'Matched food'): array
    {
        $foodNutrients = [];
        foreach ($nutrientsByNumber as $number => $value) {
            $foodNutrients[] = ['nutrientNumber' => (string) $number, 'value' => $value];
        }

        return [
            'foods' => [
                ['description' => $description, 'fdcId' => 12345, 'foodNutrients' => $foodNutrients],
            ],
        ];
    }

    public function test_dashboard_loads(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Calorie Tracker');
    }

    public function test_it_decomposes_looks_up_usda_and_stores_an_entry(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::sequence()
                ->push($this->ai([
                    'dish_name' => 'Chicken with rice',
                    'ingredients' => [
                        ['name' => 'Chicken breast', 'usda_query' => 'chicken breast cooked', 'grams' => 100, 'est_calories' => 165, 'est_protein_g' => 31, 'est_carbs_g' => 0, 'est_fat_g' => 3.6],
                        ['name' => 'White rice', 'usda_query' => 'white rice cooked', 'grams' => 150, 'est_calories' => 195, 'est_protein_g' => 4, 'est_carbs_g' => 42, 'est_fat_g' => 0.4],
                    ],
                ]))
                ->push($this->ai(['confidence' => 'high', 'reasonable' => true, 'note' => 'Looks right for one serving.'])),
            'api.nal.usda.gov/*' => Http::sequence()
                // chicken breast per 100g
                ->push($this->usda([208 => 165, 203 => 31, 204 => 3.6, 205 => 0, 291 => 0, 269 => 0, 307 => 74], 'Chicken, breast, cooked'))
                // white rice per 100g
                ->push($this->usda([208 => 130, 203 => 2.7, 204 => 0.3, 205 => 28, 291 => 0.4, 269 => 0.1, 307 => 1], 'Rice, white, cooked')),
        ]);

        $response = $this->post(route('entries.store'), [
            'description' => 'chicken with rice',
            'consumed_on' => today()->toDateString(),
        ]);

        $response->assertRedirect();

        // chicken 165*1.0 = 165 ; rice 130*1.5 = 195 ; total 360
        $this->assertDatabaseHas('food_entries', [
            'name' => 'Chicken with rice',
            'calories' => 360,
            'source' => 'usda',
            'confidence' => 'high',
        ]);

        $entry = FoodEntry::first();
        $this->assertCount(2, $entry->breakdown);
        $this->assertSame('usda', $entry->breakdown[0]['source']);
    }

    public function test_it_falls_back_to_ai_estimate_when_usda_has_no_match(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::sequence()
                ->push($this->ai([
                    'dish_name' => 'Exotic dish',
                    'ingredients' => [
                        ['name' => 'Rare ingredient', 'usda_query' => 'rare ingredient', 'grams' => 100, 'est_calories' => 200, 'est_protein_g' => 5, 'est_carbs_g' => 10, 'est_fat_g' => 15],
                    ],
                ]))
                ->push($this->ai(['confidence' => 'medium', 'reasonable' => true, 'note' => 'Estimated.'])),
            'api.nal.usda.gov/*' => Http::response(['foods' => []]),
        ]);

        $this->post(route('entries.store'), ['description' => 'exotic dish'])->assertRedirect();

        $this->assertDatabaseHas('food_entries', [
            'name' => 'Exotic dish',
            'calories' => 200,
            'source' => 'ai',
        ]);
    }

    public function test_it_identifies_food_from_a_photo_and_logs_it(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::sequence()
                ->push($this->aiText('a banana'))            // vision identification
                ->push($this->ai([                            // decomposition
                    'dish_name' => 'Banana',
                    'ingredients' => [
                        ['name' => 'Banana', 'usda_query' => 'banana raw', 'grams' => 118, 'est_calories' => 105, 'est_protein_g' => 1.3, 'est_carbs_g' => 27, 'est_fat_g' => 0.4],
                    ],
                ]))
                ->push($this->ai(['confidence' => 'high', 'reasonable' => true, 'note' => 'Typical medium banana.'])),
            'api.nal.usda.gov/*' => Http::response($this->usda([208 => 89, 203 => 1.1, 204 => 0.3, 205 => 23, 291 => 2.6, 269 => 12, 307 => 1], 'Banana, raw')),
        ]);

        $file = UploadedFile::fake()->image('lunch.jpg', 320, 240);

        $response = $this->post(route('entries.photo'), [
            'photo' => $file,
            'consumed_on' => today()->toDateString(),
        ]);

        $response->assertRedirect();
        // 89 kcal/100g * 118g ≈ 105
        $this->assertDatabaseHas('food_entries', [
            'name' => 'Banana',
            'calories' => 105,
            'source' => 'usda',
        ]);
    }

    public function test_photo_with_no_recognisable_food_is_rejected(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response($this->aiText('NO_FOOD')),
        ]);

        $file = UploadedFile::fake()->image('selfie.jpg', 200, 200);

        $response = $this->post(route('entries.photo'), ['photo' => $file]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('food_entries', 0);
    }

    public function test_photo_upload_requires_an_image(): void
    {
        $this->post(route('entries.photo'), [])
            ->assertSessionHasErrors('photo');
    }

    public function test_it_handles_a_failing_decomposition_gracefully(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response('error', 500),
        ]);

        $response = $this->post(route('entries.store'), ['description' => 'mystery meal']);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('food_entries', 0);
    }

    public function test_it_fails_over_to_groq_when_gemini_is_rate_limited(): void
    {
        // Gemini is primary; Groq is the automatic fallback.
        config()->set('services.ai.provider', 'gemini');
        config()->set('services.ai.fallback', 'groq');
        config()->set('services.gemini.key', 'gemini-test-key');
        config()->set('services.gemini.model', 'gemini-3.5-flash');
        config()->set('services.gemini.vision_model', 'gemini-3.5-flash');

        Http::fake([
            // Gemini is over quota → 429 on every attempt.
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'RESOURCE_EXHAUSTED'], 429),
            // Groq (fallback) handles decomposition + verification.
            'api.groq.com/*' => Http::sequence()
                ->push($this->ai([
                    'dish_name' => 'Apple',
                    'ingredients' => [
                        ['name' => 'Apple', 'usda_query' => 'apple raw', 'grams' => 100, 'est_calories' => 52, 'est_protein_g' => 0.3, 'est_carbs_g' => 14, 'est_fat_g' => 0.2],
                    ],
                ]))
                ->push($this->ai(['confidence' => 'high', 'reasonable' => true, 'note' => 'Looks right.'])),
            'api.nal.usda.gov/*' => Http::response($this->usda([208 => 52, 203 => 0.3, 204 => 0.2, 205 => 14, 291 => 2.4, 269 => 10, 307 => 1], 'Apple, raw')),
        ]);

        $response = $this->post(route('entries.store'), [
            'description' => 'an apple',
            'consumed_on' => today()->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('error');

        // Stored via the Groq fallback even though Gemini is the primary.
        $this->assertDatabaseHas('food_entries', [
            'name' => 'Apple',
            'calories' => 52,
            'source' => 'usda',
            'confidence' => 'high',
        ]);

        // Gemini was attempted first, then Groq served the request.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.groq.com'));
    }

    public function test_verification_fails_over_when_the_primary_errors_mid_request(): void
    {
        config()->set('services.ai.provider', 'gemini');
        config()->set('services.ai.fallback', 'groq');
        config()->set('services.gemini.key', 'gemini-test-key');
        config()->set('services.gemini.model', 'gemini-3.5-flash');
        config()->set('services.gemini.vision_model', 'gemini-3.5-flash');

        $decomposition = json_encode([
            'dish_name' => 'Apple',
            'ingredients' => [
                ['name' => 'Apple', 'usda_query' => 'apple raw', 'grams' => 100, 'est_calories' => 52, 'est_protein_g' => 0.3, 'est_carbs_g' => 14, 'est_fat_g' => 0.2],
            ],
        ]);

        Http::fake([
            // Gemini decomposes fine, then goes down (5xx, so no quota breaker) for verification.
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['candidates' => [['content' => ['parts' => [['text' => $decomposition]]]]]])
                ->whenEmpty(Http::response('unavailable', 503)),
            // Groq (fallback) should pick up the verification.
            'api.groq.com/*' => Http::response($this->ai(['confidence' => 'high', 'reasonable' => true, 'note' => 'Looks right.'])),
            'api.nal.usda.gov/*' => Http::response($this->usda([208 => 52, 203 => 0.3, 204 => 0.2, 205 => 14], 'Apple, raw')),
        ]);

        $this->post(route('entries.store'), ['description' => 'an apple'])->assertRedirect();

        $this->assertDatabaseHas('food_entries', [
            'name' => 'Apple',
            'confidence' => 'high',
            'verification_note' => 'Looks right.',
        ]);
    }

    public function test_an_entry_is_still_logged_when_verification_fails(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::sequence()
                ->push($this->ai([
                    'dish_name' => 'Apple',
                    'ingredients' => [
                        ['name' => 'Apple', 'usda_query' => 'apple raw', 'grams' => 100, 'est_calories' => 52, 'est_protein_g' => 0.3, 'est_carbs_g' => 14, 'est_fat_g' => 0.2],
                    ],
                ]))
                ->whenEmpty(Http::response('unavailable', 503)),
            'api.nal.usda.gov/*' => Http::response($this->usda([208 => 52], 'Apple, raw')),
        ]);

        $this->post(route('entries.store'), ['description' => 'an apple'])
            ->assertRedirect()
            ->assertSessionMissing('error');

        $this->assertDatabaseHas('food_entries', ['name' => 'Apple', 'calories' => 52]);
    }

    public function test_usda_key_is_sent_as_a_header_and_foundation_sugars_are_read(): void
    {
        Http::fake([
            // Foundation foods report total sugars as nutrient 269.3 rather than 269.
            'api.nal.usda.gov/*' => Http::response($this->usda([208 => 85, '269.3' => 15.8], 'Bananas, overripe, raw')),
        ]);

        $match = app(UsdaFoodClient::class)->searchBestMatch('banana raw');

        $this->assertSame(15.8, $match['per_100g']['sugar_g']);

        Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'TEST_KEY')
            && ! str_contains($request->url(), 'TEST_KEY'));
    }

    public function test_dashboard_ignores_an_invalid_date(): void
    {
        $this->get('/?date=not-a-date')->assertOk();
        $this->get('/?date[]=2026-01-01')->assertOk();
        $this->get('/?date=2026-02-31')->assertOk();
    }

    public function test_food_description_is_required(): void
    {
        $this->post(route('entries.store'), ['description' => ''])
            ->assertSessionHasErrors('description');
    }

    public function test_an_entry_can_be_deleted(): void
    {
        $entry = $this->user->foodEntries()->create([
            'name' => 'Banana',
            'calories' => 105,
            'protein_g' => 1.3,
            'carbs_g' => 27,
            'fat_g' => 0.4,
            'consumed_on' => today(),
        ]);

        $this->delete(route('entries.destroy', $entry))->assertRedirect();

        $this->assertModelMissing($entry);
    }

    public function test_the_daily_goal_can_be_updated(): void
    {
        $this->put(route('goal.update'), ['daily_goal' => 1800])
            ->assertRedirect();

        $this->assertSame(1800, $this->user->refresh()->daily_goal);
    }

    public function test_a_fresh_user_is_sent_to_profile_onboarding(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertRedirect(route('profile'));
        $this->get(route('history'))->assertRedirect(route('profile'));
    }

    public function test_profile_setup_saves_details_and_completes_onboarding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'display_name' => 'Edrian',
            'gender' => 'male',
            'height_cm' => 175,
            'weight_kg' => 70,
            'activity_level' => 'moderate',
            'daily_goal' => 2600,
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertTrue($user->hasCompletedProfile());
        $this->assertSame('Edrian', $user->name);
        $this->assertSame('male', $user->gender);
        $this->assertSame(175.0, $user->height_cm);
        $this->assertSame(2600, $user->daily_goal);
    }

    public function test_daily_tip_is_generated_by_ai_cleaned_and_cached(): void
    {
        Cache::forget(DailyTip::key());

        Http::fake([
            'api.groq.com/*' => Http::response($this->aiText('“Drink a glass of water before each meal.”')),
        ]);

        // Surrounding quotes are stripped.
        $this->assertSame('Drink a glass of water before each meal.', app(DailyTip::class)->today());

        // Cached for the day — a changed API response is not used on the next call.
        Http::fake([
            'api.groq.com/*' => Http::response($this->aiText('Something different')),
        ]);
        $this->assertSame('Drink a glass of water before each meal.', app(DailyTip::class)->today());
    }

    public function test_daily_tip_falls_back_when_ai_is_unavailable(): void
    {
        Cache::forget(DailyTip::key());

        Http::fake([
            'api.groq.com/*' => Http::response('error', 500),
        ]);

        // A non-empty curated tip is returned instead of failing.
        $this->assertNotEmpty(app(DailyTip::class)->today());
    }

    public function test_calorie_calculator_recommends_from_body_metrics(): void
    {
        // male, 175 cm, 70 kg, default age 30, moderate (1.55):
        // BMR = 700 + 1093.75 - 150 + 5 = 1648.75 ; ×1.55 = 2555.56 → 2560
        $this->assertSame(2560, CalorieCalculator::recommendedGoal('male', 175, 70, 'moderate'));

        // Gender-only fallback when height/weight are missing.
        $this->assertSame(2000, CalorieCalculator::recommendedGoal('female'));
    }

    public function test_history_aggregates_days(): void
    {
        $this->user->foodEntries()->create(['name' => 'A', 'calories' => 300, 'consumed_on' => today()]);
        $this->user->foodEntries()->create(['name' => 'B', 'calories' => 400, 'consumed_on' => today()]);

        $this->get(route('history'))
            ->assertOk()
            ->assertSee('700');
    }
}
