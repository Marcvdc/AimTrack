<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_reflections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->unique()->constrained('sessions')->cascadeOnDelete();
            $table->text('summary');
            $table->json('positives')->nullable();
            $table->json('improvements')->nullable();
            $table->text('next_focus')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_reflections');
    }
};
