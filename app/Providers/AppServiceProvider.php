<?php

namespace App\Providers;

use App\Services\Contracts\NutritionAssistant;
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
        $this->app->singleton(NutritionAssistant::class, function () {
            return match (config('services.ai.provider')) {
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
        });

        $this->app->singleton(UsdaFoodClient::class, function () {
            return new UsdaFoodClient(
                apiKey: config('services.usda.key'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
