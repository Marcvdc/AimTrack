<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weapons', function (Blueprint $table): void {
            $table->string('korrel_correction', 16)->nullable()->after('caliber');
            $table->string('vizier_correction', 16)->nullable()->after('korrel_correction');
            $table->unsignedSmallInteger('trigger_weight_g')->nullable()->after('vizier_correction');
            $table->string('grip_size', 32)->nullable()->after('trigger_weight_g');
        });
    }

    public function down(): void
    {
        Schema::table('weapons', function (Blueprint $table): void {
            $table->dropColumn([
                'korrel_correction',
                'vizier_correction',
                'trigger_weight_g',
                'grip_size',
            ]);
        });
    }
};
