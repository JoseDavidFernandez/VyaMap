<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('trip_id')
                ->nullable()
                ->constrained('trips')
                ->nullOnDelete();

            $table->foreignId('city_id')
                ->constrained('cities')
                ->restrictOnDelete();

            $table->date('visited_from')->nullable();
            $table->date('visited_until')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['trip_id']);
            $table->index(['city_id']);
            $table->index(['user_id', 'visited_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};