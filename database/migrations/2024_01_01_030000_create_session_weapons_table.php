<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_weapons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->foreignId('weapon_id')->constrained('weapons')->cascadeOnDelete();
            $table->unsignedInteger('distance_m')->nullable();
            $table->unsignedInteger('rounds_fired')->default(0);
            $table->string('ammo_type')->nullable();
            $table->string('group_quality_text')->nullable();
            $table->string('deviation', 20)->nullable();
            $table->unsignedInteger('flyers_count')->default(0);
            $table->timestamps();

            $table->unique(['session_id', 'weapon_id', 'distance_m']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_weapons');
    }
};
