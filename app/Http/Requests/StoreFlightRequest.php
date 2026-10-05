<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFlightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $trip = $this->route('trip');

        return [
            'origin_airport_id' => [
                'required',
                'integer',
                'exists:airports,id',
            ],

            'destination_airport_id' => [
                'required',
                'integer',
                'exists:airports,id',
            ],

            'flight_number' => [
                'required',
                'string',
                'max:20',
            ],

            'departure_at' => [
                'required',
                'date',
                'after_or_equal:' . $trip->start_date->format('Y-m-d 00:00:00'),
                'before_or_equal:' . $trip->end_date->format('Y-m-d 23:59:59'),
            ],

            'arrival_at' => [
                'required',
                'date',
                'after_or_equal:departure_at',
                'before_or_equal:' . $trip->end_date->format('Y-m-d 23:59:59'),
            ],

            'airline' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }
}