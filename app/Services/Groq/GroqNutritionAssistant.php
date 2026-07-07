<?php

namespace App\Services\Groq;

use App\Services\CalorieEstimationException;
use App\Services\Contracts\NutritionAssistant;
use App\Services\ProviderUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GroqNutritionAssistant implements NutritionAssistant
{
    private const ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';

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
        $prompt = <<<'PROMPT'
        You are a nutrition assistant. Look at this photo and describe the food it contains
        in ONE concise phrase suitable for a nutrition database lookup, including the main
        items and an estimated portion if visible.
        Examples: "a bowl of beef pho with rice noodles", "two fried eggs with two strips of bacon and white toast".
        If the image does not clearly contain food, reply with exactly: NO_FOOD
        PROMPT;

        $content = $this->request($this->visionModel, [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$imageBase64}"]],
                ],
            ],
        ]);

        return trim($content);
    }

    public function decompose(string $description): array
    {
        $prompt = <<<PROMPT
        You are a nutrition expert preparing a food for lookup in the USDA FoodData Central database.
        Break the following food or dish into its component ingredients.
        If no quantity is given, assume one normal single serving of the whole dish.

        Respond ONLY with a JSON object of exactly this shape:
        {
          "dish_name": "string - a clean name for the whole dish",
          "ingredients": [
            {
              "name": "string - human readable ingredient name",
              "usda_query": "string - 2-4 simple keywords likely to match a USDA food, e.g. 'chicken thigh cooked', 'white rice cooked', 'soy sauce'. No brand names.",
              "grams": number - estimated weight in grams for one serving,
              "est_calories": number,
              "est_protein_g": number,
              "est_carbs_g": number,
              "est_fat_g": number
            }
          ]
        }
        The est_* values are a rough per-ingredient backup used only if USDA has no match.

        Food: "{$description}"
        PROMPT;

        $data = $this->chat($prompt);

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
        It was broken into ingredients and looked up in the USDA nutrition database. Computed breakdown:

        {$lines}

        Totals: {$totals['calories']} kcal, protein {$totals['protein_g']}g, carbs {$totals['carbs_g']}g, fat {$totals['fat_g']}g.

        As a nutrition expert, judge whether these totals are realistic for the described food and serving size.
        Respond ONLY with a JSON object of exactly this shape:
        {
          "confidence": "high" | "medium" | "low",
          "reasonable": true or false (false if totals look clearly wrong),
          "note": "one short sentence for the user confirming it looks right or warning what seems off"
        }
        PROMPT;

        try {
            $data = $this->chat($prompt);
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

    public function dailyTip(): string
    {
        $today = today()->format('l, F j');

        $prompt = "Give ONE short, practical healthy-eating tip for the day ({$today}) — about nutrition, food choices, portion sizes, hydration, or meal prep. Make it specific and actionable, under 20 words. Reply with ONLY the tip text: no numbering, no quotation marks, no preamble.";

        return trim($this->request($this->model, [
            ['role' => 'system', 'content' => 'You reply with a single short, practical tip and nothing else.'],
            ['role' => 'user', 'content' => $prompt],
        ]));
    }

    /**
     * Run a JSON-mode chat completion against the text model and decode the result.
     *
     * @return array<string, mixed>
     *
     * @throws CalorieEstimationException
     */
    private function chat(string $prompt): array
    {
        $content = $this->request(
            model: $this->model,
            messages: [
                ['role' => 'system', 'content' => 'You are a precise nutrition assistant that always replies with valid JSON only.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            jsonMode: true,
        );

        try {
            return json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new CalorieEstimationException('Could not understand the AI response. Please try again.');
        }
    }

    /**
     * Low-level Groq chat completion returning the raw message content string.
     *
     * @param  array<int, array<string, mixed>>  $messages
     *
     * @throws CalorieEstimationException
     */
    private function request(string $model, array $messages, bool $jsonMode = false): string
    {
        if (! $this->isConfigured()) {
            throw new CalorieEstimationException('The Groq API key is not configured. Add GROQ_API_KEY to your .env file.');
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.2,
        ];

        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(45)
                ->connectTimeout(10)
                ->retry(2, 500, throw: false)
                ->post(self::ENDPOINT, $payload);
        } catch (ConnectionException $e) {
            $this->throwConnectionFailure($e);
        }

        if ($response->failed()) {
            $this->throwHttpFailure($response->status(), $response->body());
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($content)) {
            throw new CalorieEstimationException('The AI service returned an unexpected response. Please try again.');
        }

        return $content;
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
        Log::warning('Groq returned an error response', ['status' => $status, 'body' => $body]);

        if ($status === 429 || $status >= 500) {
            throw new ProviderUnavailableException(
                "Groq request failed with HTTP {$status}.",
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
        Log::warning('Groq request failed to connect', ['message' => $e->getMessage()]);

        throw new ProviderUnavailableException('Could not reach Groq.', previous: $e);
    }
}
