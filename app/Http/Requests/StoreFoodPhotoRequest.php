<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFoodPhotoRequest extends FormRequest
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
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'consumed_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Please choose or take a photo first.',
            'photo.image' => 'That file is not a valid image.',
            'photo.mimes' => 'Please use a JPG, PNG or WebP photo.',
            'photo.max' => 'The photo is too large (max 10MB).',
        ];
    }
}
