<?php

declare(strict_types=1);

namespace App\Filament\Copilot\Tools;

use App\Models\Session;
use App\Models\SessionWeapon;
use App\Models\User;
use EslamRedaDiv\FilamentCopilot\Tools\BaseTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Stringable;

class SessionLookupTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Haalt de volledige details van één sessie op (datum, baan, wapenregels, schoten, AI-reflectie). Gebruik dit als de gebruiker naar een specifieke sessie verwijst.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'session_id' => $schema->integer()->description('Het id van de Session die opgehaald moet worden.')->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->user instanceof User) {
            return 'Geen actieve schutter gevonden in de huidige sessie.';
        }

        $sessionId = (int) ($request['session_id'] ?? 0);

        if ($sessionId <= 0) {
            return 'session_id ontbreekt of is ongeldig.';
        }

        $session = Session::query()
            ->with(['sessionWeapons.weapon', 'sessionWeapons.ammoType', 'shots', 'aiReflection'])
            ->where('user_id', $this->user->id)
            ->find($sessionId);

        if ($session === null) {
            return "Geen sessie gevonden met id {$sessionId} voor deze schutter.";
        }

        $weaponBlock = $session->sessionWeapons
            ->map(fn (SessionWeapon $entry) => sprintf(
                '  - %s | %sm | %s schoten | munitie: %s | afwijking: %s | %s',
                $entry->weapon?->name ?? 'Onbekend wapen',
                $entry->distance_m ?? '-',
                $entry->rounds_fired ?? '-',
                $entry->ammoType?->name ?? ($entry->ammo_type ?: '-'),
                $entry->deviation?->value ?? '-',
                Str::limit($entry->group_quality_text ?? '-', 100),
            ))
            ->implode("\n");

        $shotsTotal = $session->shots->count();
        $avgScore = $shotsTotal > 0
            ? round($session->shots->whereNotNull('score')->avg('score') ?? 0, 2)
            : null;

        $reflection = $session->aiReflection
            ? sprintf(
                'Samenvatting: %s | Volgende focus: %s',
                $session->aiReflection->summary ?: '-',
                $session->aiReflection->next_focus ?: '-',
            )
            : 'Geen AI-reflectie aanwezig.';

        return trim(sprintf(
            "Sessie #%d op %s\nBaan: %s | Locatie: %s\nNotities: %s\nHandmatige reflectie: %s\nWapenregels:\n%s\nIndividuele schoten: %d (gemiddelde score: %s)\nAI-reflectie: %s",
            $session->id,
            $session->date?->format('Y-m-d') ?? 'onbekend',
            $session->range_name ?: '-',
            $session->location ?: '-',
            Str::limit($session->notes_raw ?? '-', 200),
            Str::limit($session->manual_reflection ?? '-', 200),
            $weaponBlock !== '' ? $weaponBlock : '  - (geen wapenregels)',
            $shotsTotal,
            $avgScore !== null ? (string) $avgScore : '-',
            $reflection,
        ));
    }
}
