<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Markeert elk bestaand account als geverifieerd, zodat niemand zichzelf
 * buitensluit zodra AUTH_REGISTRATION_ENABLED (en daarmee de verplichte
 * e-mailverificatie) aan gaat. Accounts van vóór deze migratie zijn door
 * een beheerder aangemaakt of hebben zich geregistreerd toen verificatie
 * nog niet bestond. Bewust via de query builder: een datamigratie mag niet
 * afhangen van hoe het User-model er later uitziet.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    /**
     * Niet terug te draaien: na up() is niet meer te zien welke accounts
     * hier geraakt zijn.
     */
    public function down(): void {}
};
