<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
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