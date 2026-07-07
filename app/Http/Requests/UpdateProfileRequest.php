<?php

namespace App\Http\Requests;

use App\Support\CalorieCalculator;
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
        ];
    }
}
