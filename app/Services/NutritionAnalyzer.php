<?php

namespace App\Services;

use App\Services\Contracts\NutritionAssistant;
use App\Services\Usda\UsdaFoodClient;

class NutritionAnalyzer
{
    public function __construct(
        private readonly NutritionAssistant $ai,
        private readonly UsdaFoodClient $usda,
    ) {}

    /**
     * Analyze a free-text food: Gemini decomposes it, USDA provides the real
     * per-ingredient nutrition, and Gemini verifies the computed totals.
     *
     * @return array{name: string, quantity: string, calories: int, protein_g: float, carbs_g: float, fat_g: float, fiber_g: float, sugar_g: float, sodium_mg: float, source: string, confidence: string, verification_note: string, breakdown: array<int, array<string, mixed>>}
     *
     * @throws CalorieEstimationException
     */
    public function analyze(string $description): array
    {
        return $this->analyzeDescription($description);
    }

    /**
     * Identify a food from a photo, then analyze it through the normal pipeline.
     *
     * @return array{name: string, quantity: string, calories: int, protein_g: float, carbs_g: float, fat_g: float, fiber_g: float, sugar_g: float, sodium_mg: float, source: string, confidence: string, verification_note: string, breakdown: array<int, array<string, mixed>>}
     *
     * @throws CalorieEstimationException
     */
    public function analyzeImage(string $imageBase64, string $mimeType): array
    {
        $description = trim($this->ai->identifyFood($imageBase64, $mimeType));

        if ($description === '' || str_contains(strtoupper($description), 'NO_FOOD')) {
            throw new CalorieEstimationException('No food was recognised in that photo. Try a clearer picture.');
        }

        return $this->analyzeDescription($description);
    }

    /**
     * @return array{name: string, quantity: string, calories: int, protein_g: float, carbs_g: float, fat_g: float, fiber_g: float, sugar_g: float, sodium_mg: float, source: string, confidence: string, verification_note: string, breakdown: array<int, array<string, mixed>>}
     *
     * @throws CalorieEstimationException
     */
    private function analyzeDescription(string $description): array
    {
        $decomposed = $this->ai->decompose($description);

        $breakdown = [];
        $usedUsda = false;
        $usedAi = false;

        foreach ($decomposed['ingredients'] as $ingredient) {
            $breakdown[] = $this->resolveIngredient($ingredient, $usedUsda, $usedAi);
        }

        $totals = $this->sumTotals($breakdown);

        try {
            $verification = $this->ai->verify($description, $breakdown, $totals);
        } catch (CalorieEstimationException) {
            // Verification is advisory — never block logging if it fails.
            $verification = ['confidence' => 'medium', 'reasonable' => true, 'note' => 'Verification unavailable.'];
        }

        $source = match (true) {
            $usedUsda && $usedAi => 'hybrid',
            $usedUsda => 'usda',
            default => 'ai',
        };

        $totalGrams = (int) round(collect($breakdown)->sum('grams'));

        return [
            'name' => $decomposed['dish_name'],
            'quantity' => "≈ {$totalGrams}g total",
            ...$totals,
            'source' => $source,
            'confidence' => $verification['confidence'],
            'verification_note' => $verification['note'],
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Look an ingredient up in USDA; fall back to Gemini's estimate per ingredient.
     *
     * @param  array<string, mixed>  $ingredient
     * @return array<string, mixed>
     */
    private function resolveIngredient(array $ingredient, bool &$usedUsda, bool &$usedAi): array
    {
        $grams = (float) $ingredient['grams'];
        $factor = $grams / 100;

        $match = $this->usda->searchBestMatch($ingredient['usda_query']);

        if ($match !== null && $match['per_100g']['calories'] > 0) {
            $usedUsda = true;
            $n = $match['per_100g'];

            return [
                'name' => $ingredient['name'],
                'grams' => round($grams, 1),
                'calories' => (int) round($n['calories'] * $factor),
                'protein_g' => round($n['protein_g'] * $factor, 1),
                'carbs_g' => round($n['carbs_g'] * $factor, 1),
                'fat_g' => round($n['fat_g'] * $factor, 1),
                'fiber_g' => round($n['fiber_g'] * $factor, 1),
                'sugar_g' => round($n['sugar_g'] * $factor, 1),
                'sodium_mg' => round($n['sodium_mg'] * $factor, 1),
                'source' => 'usda',
                'matched' => $match['description'],
            ];
        }

        // No USDA match — use Gemini's per-ingredient computation.
        $usedAi = true;

        return [
            'name' => $ingredient['name'],
            'grams' => round($grams, 1),
            'calories' => (int) round($ingredient['est_calories']),
            'protein_g' => round($ingredient['est_protein_g'], 1),
            'carbs_g' => round($ingredient['est_carbs_g'], 1),
            'fat_g' => round($ingredient['est_fat_g'], 1),
            'fiber_g' => 0.0,
            'sugar_g' => 0.0,
            'sodium_mg' => 0.0,
            'source' => 'ai',
            'matched' => null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $breakdown
     * @return array{calories: int, protein_g: float, carbs_g: float, fat_g: float, fiber_g: float, sugar_g: float, sodium_mg: float}
     */
    private function sumTotals(array $breakdown): array
    {
        $rows = collect($breakdown);

        return [
            'calories' => (int) $rows->sum('calories'),
            'protein_g' => round($rows->sum('protein_g'), 1),
            'carbs_g' => round($rows->sum('carbs_g'), 1),
            'fat_g' => round($rows->sum('fat_g'), 1),
            'fiber_g' => round($rows->sum('fiber_g'), 1),
            'sugar_g' => round($rows->sum('sugar_g'), 1),
            'sodium_mg' => round($rows->sum('sodium_mg'), 1),
        ];
    }
}
