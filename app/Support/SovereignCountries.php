<?php

namespace App\Support;

/**
 * Clasificación de códigos de país para las estadísticas.
 *
 * @see config/countries.php
 */
class SovereignCountries
{
    public const SOVEREIGN = 'sovereign';

    public const PENDING_DECISION = 'pending_decision';

    public const SPECIAL = 'special';

    public const TERRITORY = 'territory';

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_values(array_unique([
            ...config('countries.un_member_states', []),
            ...config('countries.additional_sovereign', []),
        ]));
    }

    public static function total(): int
    {
        return count(self::codes());
    }

    public static function isSovereign(?string $isoCode): bool
    {
        return $isoCode !== null
            && in_array(strtoupper($isoCode), self::codes(), true);
    }

    public static function classify(?string $isoCode): string
    {
        $isoCode = strtoupper((string) $isoCode);

        return match (true) {
            self::isSovereign($isoCode) => self::SOVEREIGN,
            in_array($isoCode, config('countries.pending_decision', []), true) => self::PENDING_DECISION,
            in_array($isoCode, config('countries.special_codes', []), true) => self::SPECIAL,
            default => self::TERRITORY,
        };
    }

    /**
     * Número de países soberanos distintos entre los códigos dados.
     *
     * @param  iterable<string|null>  $isoCodes
     */
    public static function countVisited(iterable $isoCodes): int
    {
        $visited = [];

        foreach ($isoCodes as $isoCode) {
            if (self::isSovereign($isoCode)) {
                $visited[strtoupper($isoCode)] = true;
            }
        }

        return count($visited);
    }

    /**
     * Porcentaje redondeado a un decimal, limitado a 0-100.
     *
     * @param  iterable<string|null>  $isoCodes
     */
    public static function percentage(iterable $isoCodes): float
    {
        $total = self::total();

        if ($total === 0) {
            return 0.0;
        }

        return round(min(100, self::countVisited($isoCodes) / $total * 100), 1);
    }
}
