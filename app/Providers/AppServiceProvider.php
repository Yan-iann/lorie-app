<?php

namespace App\Providers;

use App\Services\Contracts\NutritionAssistant;
use App\Services\FallbackNutritionAssistant;
use App\Services\Gemini\GeminiNutritionAssistant;
use App\Services\Groq\GroqNutritionAssistant;
use App\Services\Usda\UsdaFoodClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NutritionAssistant::class, function ($app) {
            $primaryName = config('services.ai.provider');
            $fallbackName = config('services.ai.fallback');

            $primary = $this->makeAssistant($primaryName);

            // No distinct fallback configured — use the primary on its own.
            if (blank($fallbackName) || $fallbackName === $primaryName) {
                return $primary;
            }

            $secondary = $this->makeAssistant($fallbackName);

            // Nothing usable to fall back to → just use the primary.
            if (! $secondary->isConfigured()) {
                return $primary;
            }

            // Primary isn't configured but the fallback is → use the fallback directly.
            if (! $primary->isConfigured()) {
                return $secondary;
            }

            return new FallbackNutritionAssistant(
                primary: $primary,
                secondary: $secondary,
                cache: $app['cache']->store(),
                cooldownMinutes: (int) config('services.ai.fallback_cooldown', 30),
            );
        });

        $this->app->singleton(UsdaFoodClient::class, function () {
            return new UsdaFoodClient(
                apiKey: config('services.usda.key'),
            );
        });
    }

    /**
     * Build a concrete nutrition assistant for the given provider name.
     */
    private function makeAssistant(?string $provider): NutritionAssistant
    {
        return match ($provider) {
            'gemini' => new GeminiNutritionAssistant(
                apiKey: config('services.gemini.key'),
                model: config('services.gemini.model'),
                visionModel: config('services.gemini.vision_model'),
            ),
            default => new GroqNutritionAssistant(
                apiKey: config('services.groq.key'),
                model: config('services.groq.model'),
                visionModel: config('services.groq.vision_model'),
            ),
        };
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
