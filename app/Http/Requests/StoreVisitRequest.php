<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $trip = $this->route('trip');

        return [
            'city_id' => ['required', 'integer', 'exists:cities,id'],

            'visited_from' => [
                'required',
                'date',
                'after_or_equal:' . $trip->start_date->format('Y-m-d'),
                'before_or_equal:' . $trip->end_date->format('Y-m-d'),
            ],

            'visited_until' => [
                'required',
                'date',
                'after_or_equal:visited_from',
                'before_or_equal:' . $trip->end_date->format('Y-m-d'),
            ],

            'notes' => ['nullable', 'string'],
        ];
    }
}