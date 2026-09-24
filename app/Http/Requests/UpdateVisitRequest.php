<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'visited_from' => ['nullable', 'date'],
            'visited_until' => ['nullable', 'date', 'after_or_equal:visited_from'],
            'notes' => ['nullable', 'string'],
        ];
    }
}