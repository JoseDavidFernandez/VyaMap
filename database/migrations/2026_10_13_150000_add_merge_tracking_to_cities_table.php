<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fusión lógica de ciudades duplicadas (p. ej. una ciudad importada y la misma
 * ciudad creada después desde Geoapify).
 *
 * - cities.merged_into_id: la ciudad duplicada se conserva, pero apunta a la
 *   ciudad canónica y deja de aparecer en búsquedas y selecciones.
 * - city_reconciliations: registro de cada fusión con los valores anteriores,
 *   suficiente para revertirla (php artisan cities:reconcile --revert=ID).
 * - Índice único (external_provider, external_id): impide duplicar una misma
 *   ciudad externa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->foreignId('merged_into_id')
                ->nullable()
                ->after('external_id')
                ->constrained('cities')
                ->restrictOnDelete();

            $table->unique(
                ['external_provider', 'external_id'],
                'cities_external_provider_id_unique'
            );
        });

        Schema::create('city_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('duplicate_city_id')->constrained('cities')->restrictOnDelete();
            $table->foreignId('canonical_city_id')->constrained('cities')->restrictOnDelete();
            $table->json('changes');
            $table->timestamp('reconciled_at');
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();

            $table->index(['duplicate_city_id', 'reverted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_reconciliations');

        Schema::table('cities', function (Blueprint $table) {
            $table->dropUnique('cities_external_provider_id_unique');
            $table->dropConstrainedForeignId('merged_into_id');
        });
    }
};
