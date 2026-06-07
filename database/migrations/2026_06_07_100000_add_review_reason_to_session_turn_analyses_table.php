<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_turn_analyses', function (Blueprint $table) {
            $table->text('review_reason')->nullable()->after('needs_review');
        });
    }

    public function down(): void
    {
        Schema::table('session_turn_analyses', function (Blueprint $table) {
            $table->dropColumn('review_reason');
        });
    }
};
