<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);

            $table->foreign('trip_id')
                ->references('id')
                ->on('trips')
                ->cascadeOnDelete();
        });

        Schema::table('flights', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);

            $table->foreign('trip_id')
                ->references('id')
                ->on('trips')
                ->cascadeOnDelete();
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);

            $table->foreign('trip_id')
                ->references('id')
                ->on('trips')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);

            $table->foreign('trip_id')
                ->references('id')
                ->on('trips')
                ->nullOnDelete();
        });

        Schema::table('flights', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);

            $table->foreign('trip_id')
                ->references('id')
                ->on('trips')
                ->nullOnDelete();
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);

            $table->foreign('trip_id')
                ->references('id')
                ->on('trips')
                ->nullOnDelete();
        });
    }
};