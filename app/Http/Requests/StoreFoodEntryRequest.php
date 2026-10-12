<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFoodEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:2', 'max:255'],
            'consumed_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
