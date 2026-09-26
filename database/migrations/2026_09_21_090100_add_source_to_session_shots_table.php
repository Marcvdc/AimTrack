<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Waar een schot vandaan komt. Bestaande schoten zijn met de hand geplaatst,
     * vandaar 'manual' als standaard; schoten uit een foto krijgen 'photo', en
     * zodra iemand zo'n schot versleept wordt dat 'photo_corrected'. Dat laatste
     * onderscheid is wat later de gelabelde meetset vormt.
     */
    public function up(): void
    {
        Schema::table('session_shots', function (Blueprint $table): void {
            $table->string('source')->default('manual')->after('shot_index');
            $table->index(['session_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('session_shots', function (Blueprint $table): void {
            $table->dropIndex(['session_id', 'source']);
            $table->dropColumn('source');
        });
    }
};
