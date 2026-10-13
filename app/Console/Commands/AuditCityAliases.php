<?php

namespace App\Console\Commands;

use App\Models\CityAlias;
use App\Support\CityAliasPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Revisa los alias aprendidos con las reglas de CityAliasPolicy.
 *
 *   php artisan cities:audit-aliases            (solo lista; no modifica nada)
 *   php artisan cities:audit-aliases --delete   (borra los rechazados, pide confirmación)
 */
class AuditCityAliases extends Command
{
    protected $signature = 'cities:audit-aliases {--delete : Borra los alias que no cumplen las reglas (pide confirmación)}';

    protected $description = 'Lista (y opcionalmente borra) los alias de ciudades que no cumplen las reglas';

    public function handle(): int
    {
        $rejected = CityAlias::query()
            ->with('city')
            ->orderBy('normalized_alias')
            ->get()
            ->map(fn (CityAlias $alias) => [
                'alias' => $alias,
                'reason' => $alias->city
                    ? CityAliasPolicy::rejectionReason($alias->normalized_alias, $alias->city)
                    : 'ciudad inexistente',
            ])
            ->filter(fn (array $row) => $row['reason'] !== null)
            ->values();

        if ($rejected->isEmpty()) {
            $this->info('Todos los alias cumplen las reglas.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'alias', 'ciudad', 'motivo'],
            $rejected->map(fn (array $row) => [
                $row['alias']->id,
                $row['alias']->normalized_alias,
                $row['alias']->city ? "{$row['alias']->city->name} (#{$row['alias']->city_id})" : "#{$row['alias']->city_id}",
                $row['reason'],
            ])->all()
        );

        if (! $this->option('delete')) {
            $this->warn("{$rejected->count()} alias no cumplen las reglas. Simulación: no se ha borrado nada.");

            return self::SUCCESS;
        }

        if (! $this->confirm("¿Borrar {$rejected->count()} alias?", false)) {
            $this->warn('Cancelado.');

            return self::FAILURE;
        }

        $deleted = DB::transaction(function () use ($rejected) {
            $rows = $rejected->map(fn (array $row) => $row['alias']->only(['id', 'city_id', 'alias', 'normalized_alias', 'language']));
            CityAlias::query()->whereIn('id', $rows->pluck('id'))->delete();

            return $rows;
        });

        // Lo necesario para restaurarlos si hiciera falta.
        $this->line(json_encode($deleted->values()->all(), JSON_UNESCAPED_UNICODE));
        $this->info("Borrados {$deleted->count()} alias.");

        return self::SUCCESS;
    }
}
