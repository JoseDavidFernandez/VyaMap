<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'trip_id' => ['nullable', 'integer', 'exists:trips,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'visited_from' => ['nullable', 'date'],
            'visited_until' => ['nullable', 'date', 'after_or_equal:visited_from'],
            'notes' => ['nullable', 'string'],
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