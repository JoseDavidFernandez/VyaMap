<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('trip_id')
                ->nullable()
                ->constrained('trips')
                ->nullOnDelete();

            $table->foreignId('visit_id')
                ->nullable()
                ->constrained('visits')
                ->nullOnDelete();

            $table->foreignId('place_id')
                ->nullable()
                ->constrained('places')
                ->nullOnDelete();

            $table->foreignId('journal_entry_id')
                ->nullable()
                ->constrained('journal_entries')
                ->nullOnDelete();

            $table->string('path', 500);
            $table->string('original_filename', 255)->nullable();
            $table->string('mime_type', 100)->nullable();

            $table->unsignedBigInteger('size')->nullable();

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->dateTime('taken_at')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['trip_id']);
            $table->index(['visit_id']);
            $table->index(['place_id']);
            $table->index(['journal_entry_id']);
            $table->index(['taken_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};