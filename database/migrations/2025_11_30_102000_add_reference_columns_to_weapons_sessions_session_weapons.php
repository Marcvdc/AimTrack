<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weapons', function (Blueprint $table) {
            $table->foreignId('storage_location_id')
                ->nullable()
                ->after('storage_location')
                ->constrained('locations')
                ->nullOnDelete();
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->foreignId('location_id')
                ->nullable()
                ->after('location')
                ->constrained('locations')
                ->nullOnDelete();

            $table->foreignId('range_location_id')
                ->nullable()
                ->after('location_id')
                ->constrained('locations')
                ->nullOnDelete();
        });

        Schema::table('session_weapons', function (Blueprint $table) {
            $table->foreignId('ammo_type_id')
                ->nullable()
                ->after('ammo_type')
                ->constrained('ammo_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('session_weapons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ammo_type_id');
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('range_location_id');
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::table('weapons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storage_location_id');
        });
    }
};
