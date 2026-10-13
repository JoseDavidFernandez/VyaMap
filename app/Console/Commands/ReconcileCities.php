<?php

namespace App\Console\Commands;

use App\Exceptions\CityReconciliationException;
use App\Models\City;
use App\Models\CityReconciliation;
use App\Services\CityReconciliationService;
use Illuminate\Console\Command;

/**
 * Fusiona lógicamente una ciudad duplicada en su ciudad canónica.
 *
 * Por defecto solo simula (no escribe nada). Ejemplos:
 *   php artisan cities:reconcile 3139 --into=637            (simulación)
 *   php artisan cities:reconcile 3139 --into=637 --execute  (aplica, pide confirmación)
 *   php artisan cities:reconcile --revert=1 --execute       (revierte)
 */
class ReconcileCities extends Command
{
    protected $signature = 'cities:reconcile
        {duplicate? : ID de la ciudad duplicada}
        {--into= : ID de la ciudad canónica que se conserva}
        {--without-external-id : No asigna el identificador externo del duplicado a la ciudad canónica}
        {--revert= : ID de una reconciliación a revertir}
        {--execute : Aplica los cambios (sin esta opción solo se simula)}';

    protected $description = 'Fusiona de forma reversible una ciudad duplicada en otra (simulación por defecto)';

    public function handle(CityReconciliationService $service): int
    {
        if ($this->option('revert') !== null) {
            return $this->revert($service, (int) $this->option('revert'));
        }

        $duplicate = City::with('country')->find($this->argument('duplicate'));
        $canonical = City::with('country')->find($this->option('into'));

        if ($duplicate === null || $canonical === null) {
            $this->error('Indica una ciudad duplicada y una canónica existentes (duplicate y --into).');

            return self::INVALID;
        }

        $assignExternalId = ! $this->option('without-external-id');
        $plan = $service->plan($duplicate, $canonical, $assignExternalId);

        $this->info("Duplicada {$duplicate->id} -> canónica {$canonical->id}");
        $this->line('Distancia: ' . ($plan['distance_km'] === null ? 'desconocida' : $plan['distance_km'] . ' km'));
        $this->line('Asignar identificador externo: ' . ($plan['assigns_external_id'] ? 'sí (verificado en Geoapify)' : 'no'));
        $this->table(
            ['Tabla', 'Filas que se moverían'],
            collect($plan['references'])->map(fn ($count, $table) => [$table, $count])->values()->all()
        );

        if ($plan['issues'] !== []) {
            foreach ($plan['issues'] as $issue) {
                $this->error($issue);
            }

            return self::FAILURE;
        }

        if (! $this->option('execute')) {
            $this->warn('Simulación: no se ha modificado nada. Usa --execute para aplicar.');

            return self::SUCCESS;
        }

        if (! $this->confirm('¿Aplicar la fusión?', false)) {
            $this->warn('Cancelado.');

            return self::FAILURE;
        }

        try {
            $reconciliation = $service->reconcile($duplicate, $canonical, $assignExternalId);
        } catch (CityReconciliationException $exception) {
            foreach ($exception->issues as $issue) {
                $this->error($issue);
            }

            return self::FAILURE;
        }

        $this->info("Fusión aplicada. Para revertirla: php artisan cities:reconcile --revert={$reconciliation->id} --execute");

        return self::SUCCESS;
    }

    private function revert(CityReconciliationService $service, int $id): int
    {
        $reconciliation = CityReconciliation::find($id);

        if ($reconciliation === null) {
            $this->error("No existe la reconciliación {$id}.");

            return self::INVALID;
        }

        $this->info("Reconciliación {$id}: ciudad {$reconciliation->duplicate_city_id} fusionada en {$reconciliation->canonical_city_id}");

        foreach ($reconciliation->changes['moved'] as $table => $ids) {
            $this->line(sprintf('  %s: %d fila(s) volverían al duplicado', $table, count($ids)));
        }

        if (! $this->option('execute')) {
            $this->warn('Simulación: no se ha modificado nada. Usa --execute para revertir.');

            return self::SUCCESS;
        }

        if (! $this->confirm('¿Revertir la fusión?', false)) {
            $this->warn('Cancelado.');

            return self::FAILURE;
        }

        try {
            $service->revert($reconciliation);
        } catch (CityReconciliationException $exception) {
            foreach ($exception->issues as $issue) {
                $this->error($issue);
            }

            return self::FAILURE;
        }

        $this->info('Fusión revertida.');

        return self::SUCCESS;
    }
}
