<?php

namespace App\Http\Requests;

use App\Models\Airport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Throwable;

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
                'different:origin_airport_id',
                'exists:airports,id',
            ],

            'flight_number' => [
                'required',
                'string',
                'max:20',
            ],

            'departure_at' => [
                'required',
                'date_format:Y-m-d\TH:i',
                'after_or_equal:' . $trip->start_date->format('Y-m-d') . ' 00:00:00',
                'before_or_equal:' . $trip->end_date->format('Y-m-d') . ' 23:59:59',
            ],

            'arrival_at' => [
                'required',
                'date_format:Y-m-d\TH:i',
                'after_or_equal:' . $trip->start_date->format('Y-m-d') . ' 00:00:00',
                'before_or_equal:' . $trip->end_date->format('Y-m-d') . ' 23:59:59',
            ],

            'airline' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $validator->errors()->has('origin_airport_id') ||
                    $validator->errors()->has('destination_airport_id') ||
                    $validator->errors()->has('departure_at') ||
                    $validator->errors()->has('arrival_at')
                ) {
                    return;
                }

                $origin = Airport::find($this->input('origin_airport_id'));
                $destination = Airport::find($this->input('destination_airport_id'));

                if (!$origin || !$destination) {
                    return;
                }

                if (!$origin->timezone || !$destination->timezone) {
                    $validator->errors()->add(
                        'origin_airport_id',
                        'Both airports must have a configured time zone.'
                    );

                    return;
                }

                try {
                    $departure = $this->parseLocalDateTime(
                        $this->input('departure_at'),
                        $origin->timezone
                    );

                    $arrival = $this->parseLocalDateTime(
                        $this->input('arrival_at'),
                        $destination->timezone
                    );
                } catch (Throwable $exception) {
                    $validator->errors()->add(
                        'departure_at',
                        'The flight date or time is invalid for the selected airport time zones.'
                    );

                    return;
                }

                if ($departure->greaterThanOrEqualTo($arrival)) {
                    $validator->errors()->add(
                        'arrival_at',
                        'The arrival must occur after the departure in real time.'
                    );
                }
            },
        ];
    }

    private function parseLocalDateTime(
        string $value,
        string $timezone
    ): CarbonImmutable {
        $date = CarbonImmutable::createFromFormat(
            '!Y-m-d\TH:i',
            $value,
            $timezone
        );

        if (!$date || $date->format('Y-m-d\TH:i') !== $value) {
            throw new \InvalidArgumentException(
                'Invalid local date or time.'
            );
        }

        return $date->utc();
    }
}
