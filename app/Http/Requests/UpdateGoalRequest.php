<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|int>>
     */
    public function rules(): array
    {
        return [
            'daily_goal' => ['required', 'integer', 'min:500', 'max:10000'],
            'display_name' => ['nullable', 'string', 'max:30'],
        ];
    }
}
