<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Zet de eigenaar van een nieuw record serverside vast op de ingelogde
 * gebruiker.
 *
 * De vier persoonlijke resources (sessies, wapens, locaties, munitietypes)
 * haalden user_id eerder uit een Hidden-veld met ->default(auth()->id()).
 * Een default is echter geen dwang: Filament neemt aan wat er uit het
 * formulier terugkomt, dus een aangepaste request kon een record op naam van
 * een ander account wegschrijven.
 *
 * Deze hook draait op de Create-pagina, ná de formulierafhandeling, en
 * overschrijft user_id onvoorwaardelijk. Ook wanneer er onverhoopt weer een
 * invulbaar veld met die naam in het schema belandt, blijft het eigenaarschap
 * daar dus kloppen. Let op de reikwijdte: een CreateAction die het formulier
 * in een modal opent, draait deze paginahook niet. Op dat pad leunt het
 * eigenaarschap op het ontbreken van het veld in het schema.
 *
 * Zie GHSA-w8rm-7p6x-jrrr.
 */
trait AssignsOwnerOnCreate
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();

        return $data;
    }
}
