<?php

namespace App\Http\Requests;

use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVisitRequest extends FormRequest
{
    /**
     * Se resuelve antes que rules(): las reglas incluyen las fechas del viaje
     * y no deben evaluarse (ni aparecer en errores) para un viaje ajeno.
     */
    public function authorize(): bool
    {
        $trip = $this->route('trip');

        return $trip instanceof Trip
            && $this->user()?->can('update', $trip) === true;
    }

    public function rules(): array
    {
        $trip = $this->route('trip');

        return [
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')->whereNull('merged_into_id')],

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

            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}