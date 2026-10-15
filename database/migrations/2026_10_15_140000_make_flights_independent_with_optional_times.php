<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * F1: vuelos independientes y datos opcionales.
 *
 * - departure_date: día local de salida en el aeropuerto de origen. Para los
 *   vuelos existentes se calcula desde departure_at (UTC) con la zona horaria
 *   del aeropuerto de origen, en PHP (MySQL no tiene las tablas de zonas, así
 *   que CONVERT_TZ no sirve). Si algún aeropuerto de origen no tiene zona
 *   válida, la migración se detiene ANTES de tocar el esquema.
 * - departure_at, arrival_at y flight_number pasan a ser opcionales. Los
 *   valores existentes no cambian.
 * - confirmed_at: confirmación explícita de que el vuelo se realizó. Queda
 *   nulo para todos los vuelos existentes (no se fabrican confirmaciones).
 * - trip_id: al borrar un viaje, sus vuelos se conservan (SET NULL).
 *
 * down() se niega a revertir si eso obligara a inventar datos (vuelos sin
 * hora o sin número) o a perder confirmaciones; en ese caso, restaurar la
 * copia de seguridad.
 */
return new class extends Migration
{
    public function up(): void
    {
        $departures = DB::table('flights')
            ->join('airports', 'airports.id', '=', 'flights.origin_airport_id')
            ->orderBy('flights.id')
            ->get(['flights.id', 'flights.departure_at', 'airports.timezone']);

        $dates = [];

        foreach ($departures as $flight) {
            if (! $flight->timezone || ! in_array($flight->timezone, timezone_identifiers_list(), true)) {
                throw new RuntimeException("Flight {$flight->id}: its origin airport has no valid time zone, so its local departure date cannot be determined. Nothing was changed.");
            }

            $dates[$flight->id] = Carbon::parse($flight->departure_at, 'UTC')
                ->setTimezone($flight->timezone)
                ->toDateString();
        }

        Schema::table('flights', function (Blueprint $table) {
            $table->date('departure_date')->nullable()->after('destination_airport_id');
            $table->timestamp('confirmed_at')->nullable()->after('airline');
        });

        foreach ($dates as $id => $date) {
            DB::table('flights')->where('id', $id)->update(['departure_date' => $date]);
        }

        if (DB::table('flights')->whereNull('departure_date')->exists()) {
            throw new RuntimeException('Some flights have no local departure date after the backfill.');
        }

        Schema::table('flights', function (Blueprint $table) {
            $table->date('departure_date')->nullable(false)->change();
            $table->string('flight_number', 20)->nullable()->change();
            $table->dateTime('departure_at')->nullable()->change();
            $table->dateTime('arrival_at')->nullable()->change();

            $table->dropForeign(['trip_id']);
            $table->foreign('trip_id')->references('id')->on('trips')->nullOnDelete();

            $table->index(['user_id', 'departure_date']);
        });
    }

    public function down(): void
    {
        $blocking = [
            'without departure time' => DB::table('flights')->whereNull('departure_at')->count(),
            'without arrival time' => DB::table('flights')->whereNull('arrival_at')->count(),
            'without flight number' => DB::table('flights')->whereNull('flight_number')->count(),
            'with a confirmation' => DB::table('flights')->whereNotNull('confirmed_at')->count(),
        ];

        $blocking = array_filter($blocking);

        if ($blocking !== []) {
            $summary = collect($blocking)->map(fn ($count, $reason) => "{$count} {$reason}")->implode(', ');

            throw new RuntimeException("Cannot roll back without inventing or losing data ({$summary}). Restore the database backup instead.");
        }

        Schema::table('flights', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'departure_date']);

            $table->dropForeign(['trip_id']);
            $table->foreign('trip_id')->references('id')->on('trips')->cascadeOnDelete();

            $table->string('flight_number', 20)->nullable(false)->change();
            $table->dateTime('departure_at')->nullable(false)->change();
            $table->dateTime('arrival_at')->nullable(false)->change();

            $table->dropColumn(['departure_date', 'confirmed_at']);
        });
    }
};
