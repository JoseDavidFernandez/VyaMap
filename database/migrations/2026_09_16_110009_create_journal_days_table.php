<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_days', function (Blueprint $table) {
            $table->id();

            $table->foreignId('journal_id')
                ->constrained('journals')
                ->cascadeOnDelete();

            $table->unsignedInteger('day_number');

            $table->date('date')->nullable();
            $table->string('title', 200)->nullable();

            $table->timestamps();

            $table->unique(['journal_id', 'day_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_days');
    }
};