<?php

namespace App\Services\Gemini;

use App\Services\CalorieEstimationException;
use App\Services\Contracts\NutritionAssistant;
use App\Services\ProviderUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiNutritionAssistant implements NutritionAssistant
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly string $visionModel,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function identifyFood(string $imageBase64, string $mimeType): string
    {
        if (! $this->isConfigured()) {
            throw new CalorieEstimationException('The Gemini API key is not configured. Add GEMINI_API_KEY to your .env file.');
        }

        $prompt = <<<'PROMPT'
        Look at this photo and describe the food it contains in ONE concise phrase suitable
        for a nutrition database lookup, including the main items and an estimated portion if visible.
        Examples: "a bowl of beef pho with rice noodles", "two fried eggs with two strips of bacon and white toast".
        If the image does not clearly contain food, reply with exactly: NO_FOOD
        PROMPT;

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$this->visionModel}:generateContent";

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->timeout(45)
                ->connectTimeout(10)
                ->retry(2, 500, throw: false)
                ->post($endpoint, [
                    'contents' => [[
                        'parts' => [
                            ['text' => $prompt],
                            ['inline_data' => ['mime_type' => $mimeType, 'data' => $imageBase64]],
                        ],
                    ]],
                ]);
        } catch (ConnectionException $e) {
            $this->throwConnectionFailure($e);
        }

        if ($response->failed()) {
            $this->throwHttpFailure($response->status(), $response->body());
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! is_string($text)) {
            throw new CalorieEstimationException('The AI service returned an unexpected response. Please try again.');
        }

        return trim($text);
    }

    /**
     * Break a free-text dish into its component ingredients with portion sizes
     * and clean USDA search terms, plus a rough per-ingredient estimate used
     * only when USDA has no match.
     *
     * @return array{dish_name: string, ingredients: array<int, array{name: string, usda_query: string, grams: float, est_calories: float, est_protein_g: float, est_carbs_g: float, est_fat_g: float}>}
     *
     * @throws CalorieEstimationException
     */
    public function decompose(string $description): array
    {
        $prompt = <<<PROMPT
        You are a nutrition expert preparing a food for lookup in the USDA FoodData Central database.
        Break the following food or dish into its component ingredients.
        For each ingredient provide:
          - a clear human name
          - "usda_query": 2-4 simple keywords likely to match a USDA food (e.g. "chicken thigh cooked", "white rice cooked", "soy sauce"). Avoid brand names and adjectives.
          - "grams": the estimated weight in grams for a single typical serving of the whole dish
          - rough per-ingredient estimates (est_calories, est_protein_g, est_carbs_g, est_fat_g) for that gram amount, used only as a backup.
        If no quantity is given, assume one normal single serving of the whole dish.

        Food: "{$description}"
        PROMPT;

        $schema = [
            'type' => 'object',
            'properties' => [
                'dish_name' => ['type' => 'string'],
                'ingredients' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'usda_query' => ['type' => 'string'],
                            'grams' => ['type' => 'number'],
                            'est_calories' => ['type' => 'number'],
                            'est_protein_g' => ['type' => 'number'],
                            'est_carbs_g' => ['type' => 'number'],
                            'est_fat_g' => ['type' => 'number'],
                        ],
                        'required' => ['name', 'usda_query', 'grams', 'est_calories', 'est_protein_g', 'est_carbs_g', 'est_fat_g'],
                    ],
                ],
            ],
            'required' => ['dish_name', 'ingredients'],
        ];

        $data = $this->generate($prompt, $schema);

        $ingredients = collect(data_get($data, 'ingredients', []))
            ->map(fn ($i) => [
                'name' => (string) data_get($i, 'name', 'Ingredient'),
                'usda_query' => (string) data_get($i, 'usda_query', data_get($i, 'name', '')),
                'grams' => max(0.0, (float) data_get($i, 'grams', 0)),
                'est_calories' => max(0.0, (float) data_get($i, 'est_calories', 0)),
                'est_protein_g' => max(0.0, (float) data_get($i, 'est_protein_g', 0)),
                'est_carbs_g' => max(0.0, (float) data_get($i, 'est_carbs_g', 0)),
                'est_fat_g' => max(0.0, (float) data_get($i, 'est_fat_g', 0)),
            ])
            ->filter(fn ($i) => $i['grams'] > 0 && filled($i['usda_query']))
            ->values()
            ->all();

        if (empty($ingredients)) {
            throw new CalorieEstimationException('Could not understand that food. Try describing it differently.');
        }

        return [
            'dish_name' => (string) data_get($data, 'dish_name', $description),
            'ingredients' => $ingredients,
        ];
    }

    /**
     * Verify USDA-derived totals against the dish for plausibility.
     *
     * @param  array<int, array<string, mixed>>  $breakdown
     * @param  array<string, float|int>  $totals
     * @return array{confidence: string, reasonable: bool, note: string}
     */
    public function verify(string $description, array $breakdown, array $totals): array
    {
        $lines = collect($breakdown)
            ->map(fn ($b) => sprintf(
                '- %s (%dg): %d kcal, P%.1f C%.1f F%.1f [source: %s]',
                $b['name'], $b['grams'], $b['calories'], $b['protein_g'], $b['carbs_g'], $b['fat_g'], $b['source']
            ))
            ->implode("\n");

        $prompt = <<<PROMPT
        A user logged this food: "{$description}".
        It was broken into ingredients and looked up in the USDA nutrition database. Here is the computed breakdown:

        {$lines}

        Totals: {$totals['calories']} kcal, protein {$totals['protein_g']}g, carbs {$totals['carbs_g']}g, fat {$totals['fat_g']}g.

        As a nutrition expert, judge whether these totals are realistic for the described food and serving size.
        Return:
          - "confidence": "high", "medium", or "low"
          - "reasonable": true if the totals are plausible, false if they look clearly wrong
          - "note": one short sentence for the user (e.g. confirm it looks right, or warn what seems off).
        PROMPT;

        $schema = [
            'type' => 'object',
            'properties' => [
                'confidence' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                'reasonable' => ['type' => 'boolean'],
                'note' => ['type' => 'string'],
            ],
            'required' => ['confidence', 'reasonable', 'note'],
        ];

        try {
            $data = $this->generate($prompt, $schema);
        } catch (CalorieEstimationException) {
            // Verification is advisory — never block logging if it fails.
            return ['confidence' => 'medium', 'reasonable' => true, 'note' => 'Verification unavailable.'];
        }

        return [
            'confidence' => (string) data_get($data, 'confidence', 'medium'),
            'reasonable' => (bool) data_get($data, 'reasonable', true),
            'note' => (string) data_get($data, 'note', ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     *
     * @throws CalorieEstimationException
     */
    private function generate(string $prompt, array $schema): array
    {
        if (! $this->isConfigured()) {
            throw new CalorieEstimationException('The Gemini API key is not configured. Add GEMINI_API_KEY to your .env file.');
        }

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->timeout(30)
                ->connectTimeout(10)
                ->retry(2, 500, throw: false)
                ->post($endpoint, [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseSchema' => $schema,
                    ],
                ]);
        } catch (ConnectionException $e) {
            $this->throwConnectionFailure($e);
        }

        if ($response->failed()) {
            $this->throwHttpFailure($response->status(), $response->body());
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! is_string($text)) {
            throw new CalorieEstimationException('The AI service returned an unexpected response. Please try again.');
        }

        try {
            return json_decode($text, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new CalorieEstimationException('Could not understand the AI response. Please try again.');
        }
    }

    /**
     * Turn a failed HTTP response into the right exception. 429 (quota/rate
     * limit) and 5xx (transient) become ProviderUnavailableException so the
     * fallback layer can switch providers; anything else is a hard error.
     *
     * @throws ProviderUnavailableException|CalorieEstimationException
     */
    private function throwHttpFailure(int $status, string $body): never
    {
        Log::warning('Gemini returned an error response', ['status' => $status, 'body' => $body]);

        if ($status === 429 || $status >= 500) {
            throw new ProviderUnavailableException(
                "Gemini request failed with HTTP {$status}.",
                quotaExhausted: $status === 429,
            );
        }

        throw new CalorieEstimationException('The AI service returned an error. Please try again in a moment.');
    }

    /**
     * @throws ProviderUnavailableException
     */
    private function throwConnectionFailure(ConnectionException $e): never
    {
        Log::warning('Gemini request failed to connect', ['message' => $e->getMessage()]);

        throw new ProviderUnavailableException('Could not reach Gemini.', previous: $e);
    }
}
