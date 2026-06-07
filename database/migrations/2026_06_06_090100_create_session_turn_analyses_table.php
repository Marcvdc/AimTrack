<?php

use App\Models\Session;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_turn_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Session::class)->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('turn_index');
            $table->boolean('needs_review')->default(false);
            $table->float('overall_confidence')->nullable();
            $table->unsignedSmallInteger('expected_shot_count')->nullable();
            $table->unsignedSmallInteger('detected_count')->default(0);
            $table->boolean('count_matches_expected')->default(false);
            $table->float('calibration_rms_mm')->nullable();
            $table->string('vision_model')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'turn_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_turn_analyses');
    }
};
