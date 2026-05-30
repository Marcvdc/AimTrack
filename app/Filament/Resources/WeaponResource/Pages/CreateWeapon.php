<?php

namespace App\Filament\Resources\WeaponResource\Pages;

use App\Filament\Resources\WeaponResource;
use App\Services\WeaponStarterTemplateService;
use Filament\Resources\Pages\CreateRecord;

class CreateWeapon extends CreateRecord
{
    protected static string $resource = WeaponResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->applyStarterTemplate();
    }

    /**
     * Honoreer de ?template=… querystring uit de geen-wapens empty state:
     * de matchende StarterTemplate-waarden worden over de form-defaults
     * heen gevuld zodat de gebruiker direct kan opslaan. De caliber-Select
     * toont starter-kalibers als vaste opties (zie WeaponResource::form),
     * dus deze prefill heeft geen database-write nodig en mount() blijft
     * vrij van persistentie en business-logica.
     */
    private function applyStarterTemplate(): void
    {
        $data = app(WeaponStarterTemplateService::class)
            ->prefillData(request()->query('template'));

        if ($data === null) {
            return;
        }

        $this->form->fill([
            ...$this->data,
            ...$data,
        ]);
    }
}
