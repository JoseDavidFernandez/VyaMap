<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFlightRequest extends FormRequest
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
            'trip_id' => ['nullable', 'integer', 'exists:trips,id'],
            'origin_airport_id' => ['required', 'integer', 'exists:airports,id'],
            'destination_airport_id' => ['required', 'integer', 'exists:airports,id'],
            'flight_number' => ['required', 'string', 'max:20'],
            'departure_at' => ['required', 'date'],
            'arrival_at' => ['required', 'date', 'after_or_equal:departure_at'],
            'airline' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Add business rules after the basic validation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tripId = $this->input('trip_id');

            if (!$tripId) {
                return;
            }

            $trip = \App\Models\Trip::find($tripId);

            if (!$trip || $trip->user_id !== $this->user()->id) {
                $validator->errors()->add(
                    'trip_id',
                    'El viaje seleccionado no pertenece al usuario autenticado.'
                );
            }
        });
    }
}