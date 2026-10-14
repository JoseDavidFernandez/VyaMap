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
     * Los vuelos se comparan con su fecha local de salida y de llegada, el
     * mismo criterio que se usa al crearlos.
     */
    public function after(): array
    {
        return [
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

                $flightsOutside = $trip->flights()
                    ->with(['originAirport:id,timezone', 'destinationAirport:id,timezone'])
                    ->get()
                    ->filter(function ($flight) use ($start, $end) {
                        $departure = $flight->departure_at
                            ->setTimezone($flight->originAirport->timezone ?? 'UTC')
                            ->startOfDay();
                        $arrival = $flight->arrival_at
                            ->setTimezone($flight->destinationAirport->timezone ?? 'UTC')
                            ->startOfDay();

                        return $departure->lt($start) || $arrival->gt($end);
                    })
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
