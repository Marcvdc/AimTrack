<?php

declare(strict_types=1);

namespace App\Filament\Copilot\Tools;

use App\Models\SessionWeapon;
use App\Models\User;
use App\Models\Weapon;
use EslamRedaDiv\FilamentCopilot\Tools\BaseTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Stringable;

class WeaponLookupTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Haalt details van één wapen op (type, kaliber, AI-inzicht en de laatste 5 sessie-entries). Gebruik dit als de gebruiker naar een specifiek wapen verwijst.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'weapon_id' => $schema->integer()->description('Het id van het Weapon dat opgehaald moet worden.')->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->user instanceof User) {
            return 'Geen actieve schutter gevonden in de huidige sessie.';
        }

        $weaponId = (int) ($request['weapon_id'] ?? 0);

        if ($weaponId <= 0) {
            return 'weapon_id ontbreekt of is ongeldig.';
        }

        $weapon = Weapon::query()
            ->with(['aiWeaponInsight'])
            ->where('user_id', $this->user->id)
            ->find($weaponId);

        if ($weapon === null) {
            return "Geen wapen gevonden met id {$weaponId} voor deze schutter.";
        }

        $recentEntries = $weapon->sessionWeapons()
            ->with('session')
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (SessionWeapon $entry) => sprintf(
                '  - %s | %sm | %s schoten | afwijking: %s | %s',
                $entry->session?->date?->format('Y-m-d') ?? 'onbekend',
                $entry->distance_m ?? '-',
                $entry->rounds_fired ?? '-',
                $entry->deviation?->value ?? '-',
                Str::limit($entry->group_quality_text ?? '-', 100),
            ))
            ->implode("\n");

        $insight = $weapon->aiWeaponInsight
            ? sprintf(
                "Samenvatting: %s\nPatronen: %s\nSuggesties: %s",
                $weapon->aiWeaponInsight->summary ?: '-',
                collect($weapon->aiWeaponInsight->patterns ?? [])->implode('; ') ?: '-',
                collect($weapon->aiWeaponInsight->suggestions ?? [])->implode('; ') ?: '-',
            )
            : 'Geen AI-inzicht beschikbaar.';

        return trim(sprintf(
            "Wapen #%d: %s\nType: %s | Kaliber: %s\nSerienummer: %s\nOpslaglocatie: %s\nActief: %s\nNotities: %s\n\nLaatste sessie-entries:\n%s\n\nAI-inzicht:\n%s",
            $weapon->id,
            $weapon->name,
            $weapon->weapon_type?->value ?? '-',
            $weapon->caliber ?: '-',
            $weapon->serial_number ?: '-',
            $weapon->storage_location ?: '-',
            $weapon->is_active ? 'ja' : 'nee',
            Str::limit($weapon->notes ?? '-', 200),
            $recentEntries !== '' ? $recentEntries : '  - (geen sessies geregistreerd)',
            $insight,
        ));
    }
}
