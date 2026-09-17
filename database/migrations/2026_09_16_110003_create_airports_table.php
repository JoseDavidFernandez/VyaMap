<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('city_id')
                ->constrained('cities')
                ->restrictOnDelete();

            $table->string('name', 200);

            $table->char('iata_code', 3)->nullable()->unique();
            $table->char('icao_code', 4)->nullable()->unique();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamps();

            $table->index(['city_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airports');
    }
};