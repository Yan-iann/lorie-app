<?php

namespace App\Http\Requests;

use App\Support\CalorieCalculator;
use App\Support\LorieCoach;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:30'],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'height_cm' => ['nullable', 'numeric', 'min:50', 'max:260'],
            'weight_kg' => ['nullable', 'numeric', 'min:20', 'max:400'],
            'activity_level' => ['nullable', Rule::in(array_keys(CalorieCalculator::ACTIVITY_FACTORS))],
            'daily_goal' => ['required', 'integer', 'min:500', 'max:10000'],
            // In litres. Blank means "use the suggested amount".
            'daily_water_goal_l' => ['nullable', 'numeric', 'min:0.5', 'max:10'],
            // The hours the day's water is paced across.
            'water_starts_at' => ['nullable', 'date_format:H:i'],
            'water_ends_at' => ['nullable', 'date_format:H:i', 'after:water_starts_at'],
            'lorie_language' => ['nullable', Rule::in(array_keys(LorieCoach::LANGUAGES))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'water_ends_at.after' => 'Your last drink time needs to be later than your first.',
        ];
    }
}
