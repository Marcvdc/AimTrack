<?php

namespace App\Filament\Resources\WeaponResource\Pages;

use App\Filament\Resources\WeaponResource;
use App\Models\AmmoType;
use App\Support\StarterTemplates;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

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
     * de matchende StarterTemplate-rij wordt over de form-defaults heen
     * gevuld zodat de gebruiker direct kan opslaan. Wanneer er nog geen
     * matchend kaliber als AmmoType bestaat, maakt mount() er een aan
     * zodat de Select::make('caliber')-options de waarde kunnen tonen.
     */
    private function applyStarterTemplate(): void
    {
        $key = request()->query('template');

        if (! is_string($key) || $key === '') {
            return;
        }

        $template = StarterTemplates::findWeapon($key);

        if ($template === null) {
            return;
        }

        AmmoType::query()->firstOrCreate(
            ['user_id' => Auth::id(), 'name' => $template['caliber']],
            ['caliber' => $template['caliber']],
        );

        $this->form->fill([
            ...$this->data,
            'name' => $template['label'],
            'weapon_type' => $template['weapon_type']->value,
            'caliber' => $template['caliber'],
        ]);
    }
}
