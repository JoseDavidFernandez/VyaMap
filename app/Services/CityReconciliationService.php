<?php

namespace App\Services;

use App\Exceptions\CityReconciliationException;
use App\Exceptions\GeoapifyUnavailableException;
use App\Models\City;
use App\Models\CityAlias;
use App\Models\CityReconciliation;
use App\Support\CityAliasNormalizer;
use App\Support\GeoDistance;
use Illuminate\Support\Facades\DB;

/**
 * Fusión lógica y reversible de una ciudad duplicada en su ciudad canónica.
 *
 * No se borra ningún registro: las referencias (visitas, aeropuertos, alias,
 * lugares, entradas de diario) pasan a la ciudad canónica, el duplicado queda
 * marcado con merged_into_id y todos los valores anteriores se guardan en
 * city_reconciliations para poder revertir la operación.
 */
class CityReconciliationService
{
    public const MERGED_PROVIDER_SUFFIX = '-merged';

    /**
     * Tablas cuyas filas apuntan a una ciudad mediante city_id.
     */
    private const REFERENCING_TABLES = [
        'visits',
        'airports',
        'city_aliases',
        'places',
        'journal_entries',
    ];

    public function __construct(
        private CitySearchService $citySearchService,
    ) {
    }

    /**
     * Comprobaciones sin escribir nada.
     *
     * @return array{
     *     issues: list<string>,
     *     references: array<string, int>,
     *     distance_km: float|null,
     *     assigns_external_id: bool,
     * }
     */
    public function plan(City $duplicate, City $canonical, bool $assignExternalId = true): array
    {
        $issues = [];

        if ($duplicate->is($canonical)) {
            $issues[] = 'La ciudad duplicada y la canónica son el mismo registro.';
        }

        if ($duplicate->merged_into_id !== null) {
            $issues[] = "La ciudad {$duplicate->id} ya está fusionada.";
        }

        if ($canonical->merged_into_id !== null) {
            $issues[] = "La ciudad canónica {$canonical->id} está fusionada en otra.";
        }

        if ($duplicate->country_id !== $canonical->country_id) {
            $issues[] = 'Las ciudades pertenecen a países distintos.';
        }

        if (! $this->namesMatch($duplicate, $canonical)) {
            $issues[] = 'El nombre normalizado no coincide ni es un alias de la ciudad canónica.';
        }

        $distance = $this->distance($duplicate, $canonical);

        if ($distance === null) {
            $issues[] = 'Falta alguna coordenada para comprobar la distancia.';
        } elseif ($distance > $this->maxDistance()) {
            $issues[] = sprintf('Las ciudades están a %.1f km (máximo %d km).', $distance, $this->maxDistance());
        }

        $conflicts = $this->tripConflicts($duplicate, $canonical);

        if ($conflicts > 0) {
            $issues[] = "Conflicto: {$conflicts} viaje(s) ya tienen una visita en ambas ciudades.";
        }

        $assigns = $assignExternalId && $duplicate->external_id !== null;

        if ($assigns) {
            $issues = [...$issues, ...$this->externalIdIssues($duplicate, $canonical)];
        }

        return [
            'issues' => $issues,
            'references' => $this->referenceCounts($duplicate),
            'distance_km' => $distance === null ? null : round($distance, 2),
            'assigns_external_id' => $assigns,
        ];
    }

    /**
     * @throws CityReconciliationException
     */
    public function reconcile(City $duplicate, City $canonical, bool $assignExternalId = true): CityReconciliation
    {
        $plan = $this->plan($duplicate, $canonical, $assignExternalId);

        if ($plan['issues'] !== []) {
            throw new CityReconciliationException($plan['issues']);
        }

        return DB::transaction(function () use ($duplicate, $canonical, $plan) {
            // Bloqueo y nueva comprobación dentro de la transacción.
            $duplicate = City::query()->lockForUpdate()->findOrFail($duplicate->id);
            $canonical = City::query()->lockForUpdate()->findOrFail($canonical->id);

            if ($duplicate->merged_into_id !== null || $canonical->merged_into_id !== null) {
                throw new CityReconciliationException(['Una de las ciudades se ha fusionado mientras tanto.']);
            }

            if ($this->tripConflicts($duplicate, $canonical) > 0) {
                throw new CityReconciliationException(['Han aparecido conflictos de visitas.']);
            }

            $moved = [];

            foreach (self::REFERENCING_TABLES as $table) {
                $ids = DB::table($table)->where('city_id', $duplicate->id)->orderBy('id')->pluck('id')->all();

                if ($ids !== []) {
                    DB::table($table)->whereIn('id', $ids)->update(['city_id' => $canonical->id]);
                }

                $moved[$table] = $ids;
            }

            $changes = [
                'moved' => $moved,
                'duplicate_before' => $duplicate->only(['external_provider', 'external_id', 'merged_into_id']),
                'canonical_before' => $canonical->only(['external_provider', 'external_id']),
                'external_id_assigned' => $plan['assigns_external_id'],
            ];

            $duplicateUpdate = ['merged_into_id' => $canonical->id];

            if ($plan['assigns_external_id']) {
                // El duplicado conserva su identificador con otro proveedor para
                // no chocar con el índice único; el valor original queda en $changes.
                $duplicateUpdate['external_provider'] = $duplicate->external_provider . self::MERGED_PROVIDER_SUFFIX;
            }

            $duplicate->forceFill($duplicateUpdate)->save();

            if ($plan['assigns_external_id']) {
                $canonical->forceFill([
                    'external_provider' => $changes['duplicate_before']['external_provider'],
                    'external_id' => $changes['duplicate_before']['external_id'],
                ])->save();
            }

            return CityReconciliation::create([
                'duplicate_city_id' => $duplicate->id,
                'canonical_city_id' => $canonical->id,
                'changes' => $changes,
                'reconciled_at' => now(),
            ]);
        });
    }

    /**
     * @throws CityReconciliationException
     */
    public function revert(CityReconciliation $reconciliation): void
    {
        if ($reconciliation->reverted_at !== null) {
            throw new CityReconciliationException(['Esta reconciliación ya se revirtió.']);
        }

        DB::transaction(function () use ($reconciliation) {
            $changes = $reconciliation->changes;
            $duplicate = City::query()->lockForUpdate()->findOrFail($reconciliation->duplicate_city_id);
            $canonical = City::query()->lockForUpdate()->findOrFail($reconciliation->canonical_city_id);

            if ($duplicate->merged_into_id !== $canonical->id) {
                throw new CityReconciliationException(['El duplicado ya no apunta a la ciudad canónica.']);
            }

            foreach ($changes['moved'] as $table => $ids) {
                if ($ids === []) {
                    continue;
                }

                $stillThere = DB::table($table)
                    ->whereIn('id', $ids)
                    ->where('city_id', $canonical->id)
                    ->count();

                if ($stillThere !== count($ids)) {
                    throw new CityReconciliationException([
                        "Algunas filas de {$table} se han modificado o borrado después de la fusión.",
                    ]);
                }

                DB::table($table)->whereIn('id', $ids)->update(['city_id' => $duplicate->id]);
            }

            if ($changes['external_id_assigned']) {
                $canonical->forceFill($changes['canonical_before'])->save();
            }

            $duplicate->forceFill($changes['duplicate_before'])->save();

            $reconciliation->forceFill(['reverted_at' => now()])->save();
        });
    }

    private function namesMatch(City $duplicate, City $canonical): bool
    {
        $duplicateName = CityAliasNormalizer::normalize($duplicate->name);

        if ($duplicateName === CityAliasNormalizer::normalize($canonical->name)) {
            return true;
        }

        return CityAlias::query()
            ->where('city_id', $canonical->id)
            ->where('normalized_alias', $duplicateName)
            ->exists();
    }

    private function distance(City $a, City $b): ?float
    {
        if ($a->latitude === null || $a->longitude === null || $b->latitude === null || $b->longitude === null) {
            return null;
        }

        return GeoDistance::km(
            (float) $a->latitude,
            (float) $a->longitude,
            (float) $b->latitude,
            (float) $b->longitude,
        );
    }

    private function tripConflicts(City $duplicate, City $canonical): int
    {
        return DB::table('visits as d')
            ->join('visits as c', 'c.trip_id', '=', 'd.trip_id')
            ->where('d.city_id', $duplicate->id)
            ->where('c.city_id', $canonical->id)
            ->whereNotNull('d.trip_id')
            ->distinct()
            ->count('d.trip_id');
    }

    /**
     * El identificador externo solo se asigna si Geoapify confirma que
     * corresponde a una ciudad del mismo país y cercana a la canónica.
     *
     * @return list<string>
     */
    private function externalIdIssues(City $duplicate, City $canonical): array
    {
        if ($duplicate->external_provider !== 'geoapify') {
            return ['Solo se pueden verificar identificadores de Geoapify.'];
        }

        if ($canonical->external_id !== null) {
            return ['La ciudad canónica ya tiene un identificador externo.'];
        }

        try {
            $result = $this->citySearchService->verifyGeoapifyCity($canonical->name, $duplicate->external_id);
        } catch (GeoapifyUnavailableException) {
            return ['Geoapify no está disponible: no se puede verificar el identificador externo.'];
        }

        if ($result === null) {
            return ['Geoapify no devuelve ese identificador al buscar la ciudad canónica.'];
        }

        $issues = [];

        if ($result['iso_code'] !== $canonical->country?->iso_code) {
            $issues[] = 'El identificador externo corresponde a otro país.';
        }

        $distance = GeoDistance::km(
            (float) $result['latitude'],
            (float) $result['longitude'],
            (float) $canonical->latitude,
            (float) $canonical->longitude,
        );

        if ($distance > $this->maxDistance()) {
            $issues[] = sprintf('El identificador externo está a %.1f km de la ciudad canónica.', $distance);
        }

        return $issues;
    }

    /**
     * @return array<string, int>
     */
    private function referenceCounts(City $duplicate): array
    {
        $counts = [];

        foreach (self::REFERENCING_TABLES as $table) {
            $counts[$table] = DB::table($table)->where('city_id', $duplicate->id)->count();
        }

        return $counts;
    }

    private function maxDistance(): int
    {
        return (int) config('cities.same_city_max_distance_km', 25);
    }
}
