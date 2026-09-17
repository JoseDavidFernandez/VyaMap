<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->id();

            $table->foreignId('city_id')
                ->constrained('cities')
                ->restrictOnDelete();

            $table->string('name', 200);
            $table->text('description')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('external_provider', 50)->nullable();
            $table->string('external_id', 150)->nullable();

            $table->timestamps();

            $table->index(['city_id']);
            $table->index(['external_provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};