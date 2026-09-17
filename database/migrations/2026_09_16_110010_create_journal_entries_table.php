<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('journal_day_id')
                ->constrained('journal_days')
                ->cascadeOnDelete();

            $table->string('title', 200);
            $table->longText('content');

            $table->foreignId('city_id')
                ->nullable()
                ->constrained('cities')
                ->restrictOnDelete();

            $table->foreignId('place_id')
                ->nullable()
                ->constrained('places')
                ->restrictOnDelete();

            $table->unsignedInteger('sort_order');

            $table->timestamps();

            $table->index(['journal_day_id', 'sort_order']);
            $table->index(['city_id']);
            $table->index(['place_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};