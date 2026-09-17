<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flights', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('trip_id')
                ->nullable()
                ->constrained('trips')
                ->nullOnDelete();

            $table->foreignId('origin_airport_id')
                ->constrained('airports')
                ->restrictOnDelete();

            $table->foreignId('destination_airport_id')
                ->constrained('airports')
                ->restrictOnDelete();

            $table->string('flight_number', 20);

            $table->dateTime('departure_at');
            $table->dateTime('arrival_at');

            $table->string('airline', 100)->nullable();

            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['trip_id']);
            $table->index(['origin_airport_id']);
            $table->index(['destination_airport_id']);
            $table->index(['departure_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flights');
    }
};