<?php

namespace App\Services\Usda;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UsdaFoodClient
{
    private const ENDPOINT = 'https://api.nal.usda.gov/fdc/v1/foods/search';

    /**
     * USDA nutrient numbers mapped to our field names. Values are per 100g.
     */
    private const NUTRIENTS = [
        '208' => 'calories',   // Energy (kcal)
        '203' => 'protein_g',  // Protein
        '204' => 'fat_g',      // Total lipid (fat)
        '205' => 'carbs_g',    // Carbohydrate, by difference
        '291' => 'fiber_g',    // Fiber, total dietary
        '269' => 'sugar_g',    // Sugars, total
        '307' => 'sodium_mg',  // Sodium, Na
    ];

    /**
     * Relevance-rank penalty added to branded products so generic ingredients
     * ("banana raw") don't match branded snack items, while still letting a
     * clearly more-relevant whole food win over a loosely-related one.
     */
    private const BRANDED_PENALTY = 50;

    public function __construct(private readonly string $apiKey) {}

    /**
     * Find the closest matching food and return its per-100g nutrition.
     *
     * @return array{description: string, fdc_id: int, per_100g: array<string, float>}|null
     */
    public function searchBestMatch(string $query): ?array
    {
        try {
            $response = Http::timeout(20)
                ->connectTimeout(10)
                ->retry(2, 400, throw: false)
                ->post(self::ENDPOINT.'?api_key='.$this->apiKey, [
                    'query' => $query,
                    'dataType' => ['Foundation', 'SR Legacy', 'Survey (FNDDS)', 'Branded'],
                    'pageSize' => 10,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('USDA request failed to connect', ['message' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('USDA returned an error response', [
                'status' => $response->status(),
                'query' => $query,
            ]);

            return null;
        }

        $food = $this->pickBestFood(data_get($response->json(), 'foods', []));

        if ($food === null) {
            return null;
        }

        return [
            'description' => (string) data_get($food, 'description', $query),
            'fdc_id' => (int) data_get($food, 'fdcId', 0),
            'per_100g' => $this->extractNutrients(data_get($food, 'foodNutrients', [])),
        ];
    }

    /**
     * Choose the best result. The API returns foods by relevance, which we keep
     * as the primary signal; branded products get a rank penalty so a generic
     * query doesn't latch onto a branded snack when a whole food is available.
     *
     * @param  array<int, array<string, mixed>>  $foods
     * @return array<string, mixed>|null
     */
    private function pickBestFood(array $foods): ?array
    {
        if (empty($foods)) {
            return null;
        }

        $best = null;
        $bestScore = PHP_INT_MAX;

        foreach (array_values($foods) as $index => $food) {
            $penalty = data_get($food, 'dataType') === 'Branded' ? self::BRANDED_PENALTY : 0;
            $score = $index + $penalty;

            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $food;
            }
        }

        return $best;
    }

    /**
     * @param  array<int, array<string, mixed>>  $foodNutrients
     * @return array<string, float>
     */
    private function extractNutrients(array $foodNutrients): array
    {
        $values = array_fill_keys(self::NUTRIENTS, 0.0);

        foreach ($foodNutrients as $nutrient) {
            $number = (string) data_get($nutrient, 'nutrientNumber');

            if (isset(self::NUTRIENTS[$number])) {
                $values[self::NUTRIENTS[$number]] = (float) data_get($nutrient, 'value', 0);
            }
        }

        return $values;
    }
}
