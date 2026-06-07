<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('anthropic_api_key')->nullable()->after('is_admin');
            $table->timestamp('ai_key_verified_at')->nullable()->after('anthropic_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['ai_key_verified_at', 'anthropic_api_key']);
        });
    }
};
