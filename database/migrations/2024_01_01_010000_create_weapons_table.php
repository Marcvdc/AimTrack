<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weapons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('weapon_type', 50);
            $table->string('caliber', 50);
            $table->string('serial_number')->nullable();
            $table->string('storage_location')->nullable();
            $table->date('owned_since')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'serial_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weapons');
    }
};
