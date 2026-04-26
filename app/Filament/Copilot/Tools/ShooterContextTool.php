<?php

declare(strict_types=1);

namespace App\Filament\Copilot\Tools;

use App\Models\Session;
use App\Models\SessionWeapon;
use App\Models\User;
use App\Models\Weapon;
use EslamRedaDiv\FilamentCopilot\Tools\BaseTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Stringable;

class ShooterContextTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Haalt schiet-context op van de huidige gebruiker: recente sessies (wapen, afstand, aantal schoten, afwijking, notities), per-wapen aggregaties en de meest recente AI-reflectie. Gebruik dit voor vragen over trends, voortgang of wapen-specifieke prestaties.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'weapon_id' => $schema->integer()->description('Optioneel: filter op één specifiek wapen (id van het Weapon model).'),
            'limit' => $schema->integer()->description('Aantal recente sessies (default 10, max 25).'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->user instanceof User) {
            return 'Geen actieve schutter gevonden in de huidige sessie.';
        }

        $weaponId = isset($request['weapon_id']) ? (int) $request['weapon_id'] : null;
        $limit = isset($request['limit']) ? max(1, min(25, (int) $request['limit'])) : 10;

        $sessions = $this->recentSessions($this->user, $weaponId, $limit);
        $weaponStats = $this->weaponAggregates($this->user, $weaponId);
        $latestReflection = $this->latestReflection($this->user);

        if ($sessions->isEmpty() && $weaponStats->isEmpty()) {
            return 'Geen schietgegevens gevonden voor deze schutter (eventueel binnen het weapon_id filter).';
        }

        $sections = [];

        if ($sessions->isNotEmpty()) {
            $sections[] = "Recente sessies (max {$limit}):\n".$this->formatSessions($sessions);
        }

        if ($weaponStats->isNotEmpty()) {
            $sections[] = "Per-wapen aggregaties:\n".$this->formatWeaponStats($weaponStats);
        }

        if ($latestReflection !== null) {
            $sections[] = "Meest recente AI-reflectie:\n".$latestReflection;
        }

        return implode("\n\n", $sections);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Session>
     */
    private function recentSessions(User $user, ?int $weaponId, int $limit)
    {
        return Session::query()
            ->with(['sessionWeapons.weapon', 'aiReflection'])
            ->where('user_id', $user->id)
            ->when($weaponId, fn ($query) => $query->whereHas(
                'sessionWeapons',
                fn ($subQuery) => $subQuery->where('weapon_id', $weaponId)
            ))
            ->latest('date')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Session>  $sessions
     */
    private function formatSessions($sessions): string
    {
        return $sessions->map(function (Session $session): string {
            $weaponLines = $session->sessionWeapons
                ->map(fn (SessionWeapon $entry) => sprintf(
                    '  • %s | %sm | %s schoten | afwijking: %s | %s',
                    $entry->weapon?->name ?? 'Onbekend wapen',
                    $entry->distance_m ?? '-',
                    $entry->rounds_fired ?? '-',
                    $entry->deviation?->value ?? '-',
                    Str::limit($entry->group_quality_text ?? '-', 80),
                ))
                ->implode("\n");

            return sprintf(
                "- Sessie %s (%s)\n%s",
                $session->date?->format('Y-m-d') ?? 'onbekend',
                $session->range_name ?: ($session->location ?? 'onbekende locatie'),
                $weaponLines !== '' ? $weaponLines : '  • (geen wapenregels)'
            );
        })->implode("\n");
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function weaponAggregates(User $user, ?int $weaponId)
    {
        return Weapon::query()
            ->where('user_id', $user->id)
            ->when($weaponId, fn ($query) => $query->whereKey($weaponId))
            ->withCount('sessionWeapons as session_entries_count')
            ->withSum('sessionWeapons as total_rounds', 'rounds_fired')
            ->withMax('sessionWeapons as last_session_at', 'created_at')
            ->orderByDesc('last_session_at')
            ->get()
            ->map(fn (Weapon $weapon): array => [
                'name' => $weapon->name,
                'caliber' => $weapon->caliber,
                'sessions' => (int) $weapon->session_entries_count,
                'rounds' => (int) ($weapon->total_rounds ?? 0),
                'last' => $weapon->last_session_at,
            ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $stats
     */
    private function formatWeaponStats($stats): string
    {
        return $stats->map(fn (array $row): string => sprintf(
            '- %s (%s): %d sessies, %d schoten, laatst: %s',
            $row['name'],
            $row['caliber'] ?: 'onbekend kaliber',
            $row['sessions'],
            $row['rounds'],
            $row['last'] ? Carbon::parse($row['last'])->format('Y-m-d') : 'nooit',
        ))->implode("\n");
    }

    private function latestReflection(User $user): ?string
    {
        $reflection = Session::query()
            ->where('user_id', $user->id)
            ->whereHas('aiReflection')
            ->with('aiReflection')
            ->latest('date')
            ->first()
            ?->aiReflection;

        if ($reflection === null) {
            return null;
        }

        $positives = collect($reflection->positives ?? [])->implode('; ');
        $improvements = collect($reflection->improvements ?? [])->implode('; ');

        return trim(sprintf(
            "Samenvatting: %s\nPositief: %s\nVerbeterpunten: %s\nVolgende focus: %s",
            $reflection->summary ?: '-',
            $positives ?: '-',
            $improvements ?: '-',
            $reflection->next_focus ?: '-',
        ));
    }
}
