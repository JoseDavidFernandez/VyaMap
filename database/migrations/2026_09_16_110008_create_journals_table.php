<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('trip_id')
                ->unique()
                ->constrained('trips')
                ->cascadeOnDelete();

            $table->string('title', 200)->nullable();
            $table->text('intro')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};