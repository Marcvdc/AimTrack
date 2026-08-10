<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

/**
 * Houdt de eigenaar van een bestaand record ongewijzigd bij het bewerken.
 *
 * SessionPolicy::update() en de zusterpolicies toetsen het eigenaarschap op
 * het record zoals dat in de database staat. Een wijziging van user_id in
 * dezelfde request glipt daar dus langs: de controle slaagt op de oude
 * eigenaar, waarna het record naar een ander account wordt doorgeschoven en
 * uit het overzicht van de bewerker verdwijnt.
 *
 * Door user_id uit de opslagdata te halen blijft de kolom staan zoals hij is.
 *
 * Zie GHSA-w8rm-7p6x-jrrr.
 */
trait PreservesOwnerOnSave
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['user_id']);

        return $data;
    }
}
