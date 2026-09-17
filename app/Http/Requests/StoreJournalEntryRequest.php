<?php

namespace App\Http\Requests;

use App\Models\JournalDay;
use App\Models\Place;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreJournalEntryRequest extends FormRequest
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
            'journal_day_id' => ['required', 'integer', 'exists:journal_days,id'],
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'place_id' => ['nullable', 'integer', 'exists:places,id'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Add business rules after the basic validation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $cityId = $this->input('city_id');
            $placeId = $this->input('place_id');
            $journalDayId = $this->input('journal_day_id');

            $journalDay = JournalDay::find($journalDayId);

            if ($journalDay) {
                $journalTrip = $journalDay
                    ->journal
                    ->trip;

                if ($journalTrip->user_id !== $this->user()->id) {
                    $validator->errors()->add(
                        'journal_day_id',
                        'El día del diario no pertenece al usuario autenticado.'
                    );
                }
            }

            if (!$placeId) {
                return;
            }

            $place = Place::find($placeId);

            if (!$place) {
                return;
            }

            if ($cityId && $place->city_id !== (int) $cityId) {
                $validator->errors()->add(
                    'place_id',
                    'El lugar seleccionado no pertenece a la ciudad indicada.'
                );
            }
        });
    }
}