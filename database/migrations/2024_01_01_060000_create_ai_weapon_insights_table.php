<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_weapon_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weapon_id')->unique()->constrained('weapons')->cascadeOnDelete();
            $table->text('summary');
            $table->json('patterns')->nullable();
            $table->json('suggestions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_weapon_insights');
    }
};
