<?php

namespace App\Http\Requests;

use App\Models\Trip;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('trip'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * Las nuevas fechas no pueden dejar fuera visitas ni vuelos existentes.
     * Los vuelos se comparan con su fecha local de salida, el mismo criterio
     * que se usa al crearlos (la llegada no se limita).
     */
    public function after(): array
    {
        return [
            // Un viaje completado ya ocurrió: no puede empezar en el futuro.
            function (Validator $validator): void {
                $trip = $this->route('trip');

                if (
                    $trip instanceof Trip
                    && $trip->status === Trip::COMPLETED
                    && ! $validator->errors()->has('start_date')
                    && $this->date('start_date')?->toDateString() > Trip::today()
                ) {
                    $validator->errors()->add(
                        'start_date',
                        'A completed trip cannot start in the future. Mark it as planned first.'
                    );
                }
            },
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                    return;
                }

                /** @var Trip $trip */
                $trip = $this->route('trip');

                $start = CarbonImmutable::parse($this->input('start_date'))->startOfDay();
                $end = CarbonImmutable::parse($this->input('end_date'))->startOfDay();

                $visitsOutside = $trip->visits()
                    ->where(fn ($query) => $query
                        ->whereDate('visited_from', '<', $start)
                        ->orWhereDate('visited_until', '>', $end))
                    ->count();

                // Misma regla que al crear un vuelo (D3): la fecha local de salida
                // debe quedar dentro del viaje; la llegada no se limita.
                $flightsOutside = $trip->flights()
                    ->where(fn ($query) => $query
                        ->where('departure_date', '<', $start->toDateString())
                        ->orWhere('departure_date', '>', $end->toDateString()))
                    ->count();

                if ($visitsOutside > 0) {
                    $validator->errors()->add(
                        'start_date',
                        trans_choice(
                            '{1} One visit falls outside the new dates. Adjust it first.|[2,*] :count visits fall outside the new dates. Adjust them first.',
                            $visitsOutside
                        )
                    );
                }

                if ($flightsOutside > 0) {
                    $validator->errors()->add(
                        'end_date',
                        trans_choice(
                            '{1} One flight falls outside the new dates. Adjust it first.|[2,*] :count flights fall outside the new dates. Adjust them first.',
                            $flightsOutside
                        )
                    );
                }
            },
        ];
    }
}
