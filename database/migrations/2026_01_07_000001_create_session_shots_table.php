<?php

use App\Models\Session;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_shots', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Session::class)
                ->constrained()
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('turn_index');
            $table->unsignedSmallInteger('shot_index');
            $table->decimal('x_normalized', 8, 5);
            $table->decimal('y_normalized', 8, 5);
            $table->decimal('distance_from_center', 8, 5);
            $table->unsignedTinyInteger('ring')->nullable();
            $table->unsignedTinyInteger('score')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'turn_index', 'shot_index']);
            $table->index(['session_id', 'turn_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_shots');
    }
};
