<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->string('external_provider', 50)->nullable()->after('longitude');
            $table->string('external_id', 255)->nullable()->after('external_provider');

            $table->index(
                ['external_provider', 'external_id'],
                'cities_external_provider_id_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropIndex('cities_external_provider_id_index');
            $table->dropColumn([
                'external_provider',
                'external_id',
            ]);
        });
    }
};