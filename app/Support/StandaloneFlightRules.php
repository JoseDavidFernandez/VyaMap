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

    /*
     * Mismo mensaje para un vuelo ajeno, inexistente o ya en otro viaje: no
     * revela si el id existe. TripFlightService::attach lo repite si otra
     * petición se lleva el vuelo después de validar.
     */
    public const UNAVAILABLE = 'This flight is no longer available: it is already in another trip.';

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
            'flight_ids.*.exists' => self::UNAVAILABLE,
        ];
    }
}
