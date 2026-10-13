<?php

namespace App\Services\Contracts;

use App\Services\CalorieEstimationException;

interface NutritionAssistant
{
    public function isConfigured(): bool;

    /**
     * Identify the food shown in a photo and return a concise description
     * suitable for the decomposition pipeline (e.g. "two fried eggs with toast").
     * Returns the literal string "NO_FOOD" when no food is recognisable.
     *
     * @throws CalorieEstimationException
     */
    public function identifyFood(string $imageBase64, string $mimeType): string;

    /**
     * Break a free-text dish into component ingredients with grams, USDA search
     * terms, and a rough per-ingredient estimate (used only when USDA misses).
     *
     * @return array{dish_name: string, ingredients: array<int, array{name: string, usda_query: string, grams: float, est_calories: float, est_protein_g: float, est_carbs_g: float, est_fat_g: float}>}
     *
     * @throws CalorieEstimationException
     */
    public function decompose(string $description): array;

    /**
     * Verify USDA-derived totals against the dish for plausibility. Failures
     * are thrown (so the fallback layer can switch providers); the caller
     * treats verification as advisory.
     *
     * @param  array<int, array<string, mixed>>  $breakdown
     * @param  array<string, float|int>  $totals
     * @return array{confidence: string, reasonable: bool, note: string}
     *
     * @throws CalorieEstimationException
     */
    public function verify(string $description, array $breakdown, array $totals): array;

    /**
     * Answer a prompt with a short piece of plain text (used for the daily
     * food fact). The default temperature is higher than the nutrition calls
     * use, so wording and choice of subject vary between requests; pass 0 for
     * a steady, repeatable answer.
     *
     * @throws CalorieEstimationException
     */
    public function shortText(string $prompt, float $temperature = 0.9): string;
}
