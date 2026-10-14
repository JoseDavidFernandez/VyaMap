<?php

namespace App\Http\Requests;

use App\Models\JournalDay;
use App\Models\Place;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreJournalEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Un día de diario ajeno es una denegación (403), no un error de
        // validación. Si el día no existe, lo resuelve la regla exists (422).
        $journalDayId = $this->input('journal_day_id');

        if (! is_numeric($journalDayId)) {
            return true;
        }

        $tripOwnerId = JournalDay::query()
            ->join('journals', 'journals.id', '=', 'journal_days.journal_id')
            ->join('trips', 'trips.id', '=', 'journals.trip_id')
            ->where('journal_days.id', (int) $journalDayId)
            ->value('trips.user_id');

        return $tripOwnerId === null || (int) $tripOwnerId === (int) $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'journal_day_id' => ['required', 'integer', 'exists:journal_days,id'],
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:50000'],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')->whereNull('merged_into_id')],
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