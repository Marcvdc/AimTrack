<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vereniging_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vereniging_id')->constrained('verenigingen')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['vereniging_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vereniging_user');
    }
};
