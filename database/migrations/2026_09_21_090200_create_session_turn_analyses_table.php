<?php

use App\Models\Session;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * De uitkomst van een foto-analyse per beurt.
     *
     * Dit staat bewust niet op de schoten zelf: een analyse die nul schoten oplevert
     * moet ook zichtbaar blijven, en juist dan wil je weten dat er iets te
     * controleren valt. Eén rij per beurt, want een beurt wordt opnieuw geanalyseerd
     * als er een nieuwe foto voor komt.
     */
    public function up(): void
    {
        Schema::create('session_turn_analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Session::class)->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('turn_index');
            $table->string('status')->default('pending');
            $table->boolean('needs_review')->default(true);
            $table->text('review_reason')->nullable();
            $table->unsignedSmallInteger('expected_shot_count')->nullable();
            $table->unsignedSmallInteger('detected_count')->nullable();
            $table->unsignedSmallInteger('dropped_low_confidence')->default(0);
            $table->unsignedSmallInteger('rejected_by_model')->default(0);
            $table->decimal('overall_confidence', 4, 3)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('model')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'turn_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_turn_analyses');
    }
};
