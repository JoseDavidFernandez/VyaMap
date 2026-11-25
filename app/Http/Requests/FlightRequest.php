<?php

namespace App\Http\Requests;

use App\Models\Airport;
use App\Models\Flight;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validación única de vuelos, para las rutas del viaje
 * (/trips/{trip}/flights) y las generales (/flights).
 *
 * Obligatorios: aeropuertos de origen y destino (distintos) y la fecha local
 * de salida. Opcionales: horas, fecha de llegada, número de vuelo, aerolínea
 * y viaje. Nunca se inventan horas: sin hora no hay instante ni duración.
 *
 * - La hora de llegada solo se admite con hora de salida (D4).
 * - Las horas se interpretan en la zona del aeropuerto correspondiente; una
 *   hora inexistente por el cambio de horario se rechaza.
 * - Con viaje, la fecha local de salida fuera de sus fechas no se bloquea:
 *   la ficha del viaje lo avisa (D3; ver TripFlightService).
 */
class FlightRequest extends FormRequest
{
    private ?CarbonImmutable $departureInstant = null;

    private ?CarbonImmutable $arrivalInstant = null;

    public function authorize(): bool
    {
        $trip = $this->route('trip');
        $flight = $this->route('flight');

        if ($trip instanceof Trip) {
            return $this->user()?->can('update', $trip) === true;
        }

        if ($flight instanceof Flight) {
            return $this->user()?->can('update', $flight) === true;
        }

        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('flight_number'))) {
            $this->merge(['flight_number' => strtoupper(trim($this->input('flight_number'))) ?: null]);
        }
    }

    public function rules(): array
    {
        return [
            'origin_airport_id' => ['required', 'integer', 'exists:airports,id'],
            'destination_airport_id' => ['required', 'integer', 'different:origin_airport_id', 'exists:airports,id'],
            'departure_date' => ['required', 'date_format:Y-m-d'],
            'departure_time' => ['nullable', 'date_format:H:i'],
            'arrival_date' => ['nullable', 'date_format:Y-m-d'],
            'arrival_time' => ['nullable', 'date_format:H:i'],
            'flight_number' => ['nullable', 'string', 'max:20'],
            'airline' => ['nullable', 'string', 'max:100'],
            // Solo en las rutas generales; en las del viaje manda el viaje de la URL.
            'trip_id' => ['nullable', 'integer', Rule::exists('trips', 'id')->where('user_id', $this->user()?->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'destination_airport_id.different' => 'Origin and destination must be different airports.',
            'trip_id.exists' => 'Choose one of your trips.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateTimes($validator);
            },
        ];
    }

    /**
     * Atributos del vuelo ya convertidos (instantes en UTC o null).
     */
    public function flightAttributes(): array
    {
        return [
            'origin_airport_id' => (int) $this->validated('origin_airport_id'),
            'destination_airport_id' => (int) $this->validated('destination_airport_id'),
            'departure_date' => $this->validated('departure_date'),
            'departure_at' => $this->departureInstant,
            'arrival_at' => $this->arrivalInstant,
            'flight_number' => $this->validated('flight_number'),
            'airline' => $this->validated('airline'),
        ];
    }

    // Viaje de la URL o, en las rutas generales, el trip_id elegido (o ninguno).
    public function trip(): ?Trip
    {
        $trip = $this->route('trip');

        if ($trip instanceof Trip) {
            return $trip;
        }

        $tripId = $this->input('trip_id');

        return $tripId ? Trip::where('user_id', $this->user()->id)->find($tripId) : null;
    }

    private function validateTimes(Validator $validator): void
    {
        $departureTime = $this->input('departure_time');
        $arrivalTime = $this->input('arrival_time');
        $arrivalDate = $this->input('arrival_date');

        if ($arrivalTime && ! $departureTime) {
            $validator->errors()->add('arrival_time', 'Add the departure time before the arrival time.');

            return;
        }

        if ($arrivalDate && ! $arrivalTime) {
            $validator->errors()->add('arrival_date', 'An arrival date needs an arrival time.');

            return;
        }

        if (! $departureTime) {
            return;
        }

        $origin = Airport::find($this->input('origin_airport_id'));
        $destination = Airport::find($this->input('destination_airport_id'));

        if (! $origin?->timezone || ($arrivalTime && ! $destination?->timezone)) {
            $validator->errors()->add('departure_time', 'Times need airports with a configured time zone. Leave them empty or choose other airports.');

            return;
        }

        $departureDate = $this->input('departure_date');
        $this->departureInstant = $this->localInstant($departureDate, $departureTime, $origin->timezone);

        if ($this->departureInstant === null) {
            $validator->errors()->add('departure_time', 'This departure time does not exist in the origin time zone (daylight saving change).');

            return;
        }

        if (! $arrivalTime) {
            return;
        }

        $arrivalDate ??= $departureDate;
        $offset = (int) CarbonImmutable::parse($departureDate)->diffInDays(CarbonImmutable::parse($arrivalDate), false);

        if ($offset < -1 || $offset > 2) {
            $validator->errors()->add('arrival_date', 'The arrival date must be between one day before and two days after the departure date.');

            return;
        }

        $this->arrivalInstant = $this->localInstant($arrivalDate, $arrivalTime, $destination->timezone);

        if ($this->arrivalInstant === null) {
            $validator->errors()->add('arrival_time', 'This arrival time does not exist in the destination time zone (daylight saving change).');

            return;
        }

        if ($this->arrivalInstant->lessThanOrEqualTo($this->departureInstant)) {
            $validator->errors()->add('arrival_time', 'The arrival must occur after the departure in real time.');
        }
    }

    // Fecha y hora locales a instante UTC; null si la hora no existe en esa zona.
    private function localInstant(string $date, string $time, string $timezone): ?CarbonImmutable
    {
        $value = "{$date} {$time}";
        $local = CarbonImmutable::createFromFormat('!Y-m-d H:i', $value, $timezone);

        if (! $local || $local->format('Y-m-d H:i') !== $value) {
            return null;
        }

        return $local->utc();
    }
}
