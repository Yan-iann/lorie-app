<?php

namespace Tests\Feature;

use App\Models\FoodEntry;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalorieTrackerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ai.provider', 'groq');
        config()->set('services.groq.key', 'test-key');
        config()->set('services.groq.model', 'llama-3.3-70b-versatile');
        config()->set('services.groq.vision_model', 'test-vision-model');
        config()->set('services.usda.key', 'TEST_KEY');
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

    public function test_food_description_is_required(): void
    {
        $this->post(route('entries.store'), ['description' => ''])
            ->assertSessionHasErrors('description');
    }

    public function test_an_entry_can_be_deleted(): void
    {
        $entry = FoodEntry::create([
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

        $this->assertSame('1800', Setting::get('daily_goal'));
    }

    public function test_history_aggregates_days(): void
    {
        FoodEntry::create(['name' => 'A', 'calories' => 300, 'consumed_on' => today()]);
        FoodEntry::create(['name' => 'B', 'calories' => 400, 'consumed_on' => today()]);

        $this->get(route('history'))
            ->assertOk()
            ->assertSee('700');
    }
}
