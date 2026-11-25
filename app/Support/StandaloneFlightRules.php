<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Reglas de validación de los vuelos existentes que se asocian a un viaje
 * (al crearlo, al editarlo o desde su ficha): ids de vuelos independientes
 * del propio usuario. Un vuelo ya asociado a otro viaje no se acepta.
 */
class StandaloneFlightRules
{
    public const MAX_FLIGHTS = 100;

    public static function rules(?int $userId, bool $required = false): array
    {
        return [
            'flight_ids' => [$required ? 'required' : 'nullable', 'array', 'max:' . self::MAX_FLIGHTS],
            'flight_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('flights', 'id')->where('user_id', $userId)->whereNull('trip_id'),
            ],
        ];
    }

    public static function messages(): array
    {
        return [
            'flight_ids.*.exists' => 'Choose flights of your own that are not in another trip.',
        ];
    }
}
