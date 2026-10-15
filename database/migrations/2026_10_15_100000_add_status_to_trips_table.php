<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Estado del viaje: planned, completed o cancelled. "En curso" y "pendiente
 * de confirmar" no se guardan: se deducen de las fechas de un viaje planned.
 *
 * Clasificación de los viajes existentes: los que terminan hoy o después
 * (en app.user_timezone) pasan a planned; el resto queda completed. Nunca se
 * completa un viaje solo porque su fecha haya pasado: esta migración solo
 * clasifica el historial que ya existía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('status', 20)->default('completed')->after('end_date');
            $table->index(['user_id', 'status']);
        });

        $today = Carbon::now(config('app.user_timezone'))->toDateString();

        DB::table('trips')
            ->where('end_date', '>=', $today)
            ->update(['status' => 'planned']);
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
