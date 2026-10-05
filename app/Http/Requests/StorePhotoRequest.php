<?php

namespace App\Http\Requests;

use App\Models\JournalEntry;
use App\Models\Place;
use App\Models\Trip;
use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePhotoRequest extends FormRequest
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
                'photo' => [
                    'required',
                    'file',
                    'mimes:jpg,jpeg,png,webp',
                    'max:20480',
                ],

                'trip_id' => ['nullable', 'integer', 'exists:trips,id'],
                'visit_id' => ['nullable', 'integer', 'exists:visits,id'],
                'place_id' => ['nullable', 'integer', 'exists:places,id'],
                'journal_entry_id' => ['nullable', 'integer', 'exists:journal_entries,id'],
            ];
        }

    /**
     * Add business rules after the basic validation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $userId = $this->user()->id;

            $trip = $this->filled('trip_id')
                ? Trip::find($this->input('trip_id'))
                : null;

            $visit = $this->filled('visit_id')
                ? Visit::find($this->input('visit_id'))
                : null;

            $place = $this->filled('place_id')
                ? Place::find($this->input('place_id'))
                : null;

            $journalEntry = $this->filled('journal_entry_id')
                ? JournalEntry::find($this->input('journal_entry_id'))
                : null;

            if ($trip && $trip->user_id !== $userId) {
                $validator->errors()->add(
                    'trip_id',
                    'El viaje seleccionado no pertenece al usuario autenticado.'
                );
            }

            if ($visit && $visit->user_id !== $userId) {
                $validator->errors()->add(
                    'visit_id',
                    'La visita seleccionada no pertenece al usuario autenticado.'
                );
            }

            if ($trip && $visit && $visit->trip_id !== $trip->id) {
                $validator->errors()->add(
                    'visit_id',
                    'La visita no pertenece al viaje seleccionado.'
                );
            }
            if ($journalEntry) {
                $journalTrip = $journalEntry
                    ->journalDay
                    ->journal
                    ->trip;

                if ($journalTrip->user_id !== $userId) {
                    $validator->errors()->add(
                        'journal_entry_id',
                        'La entrada del diario no pertenece al usuario autenticado.'
                    );
                }

                if ($trip && $journalTrip->id !== $trip->id) {
                    $validator->errors()->add(
                        'journal_entry_id',
                        'La entrada del diario no pertenece al viaje seleccionado.'
                    );
                }
            }


            if ($place && $journalEntry) {
                if ($journalEntry->place_id !== null && $journalEntry->place_id !== $place->id) {
                    $validator->errors()->add(
                        'place_id',
                        'El lugar seleccionado no coincide con el lugar de la entrada del diario.'
                    );
                }
            }
        });
    }
}