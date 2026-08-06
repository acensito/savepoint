<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'platform_id' => ['sometimes', 'required', 'exists:platforms,id'],
            'status' => ['nullable', 'string', 'max:50'],
            'play_status' => ['nullable', 'string', 'max:50'],
            'release_date' => ['nullable', 'date'],
            'genres' => ['nullable', 'array'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:10'],
            'price_paid' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}