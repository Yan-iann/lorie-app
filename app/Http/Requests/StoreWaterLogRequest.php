<?php

namespace App\Http\Requests;

use App\Support\WaterCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWaterLogRequest extends FormRequest
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
            'container' => ['required', Rule::in(array_keys(WaterCalculator::CONTAINERS))],
            'consumed_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'container.required' => 'Pick what you drank from.',
            'container.in' => 'Pick what you drank from.',
        ];
    }
}
