<?php

declare(strict_types=1);

namespace App\Filament\Copilot\Tools;

use App\Models\User;
use App\Services\ScoreDriftService;
use EslamRedaDiv\FilamentCopilot\Tools\BaseTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class ScoreDriftTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Analyseert de score-drift van de schutter: het gemiddelde per schot-positie over de laatste sessies, plus het zwakste schot-venster (waar de concentratie wegzakt). Gebruik dit bij vragen als "waarom zakt mijn score rond schot 35?".';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'sessions' => $schema->integer()->description('Aantal recente sessies om mee te nemen (default 6).'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->user instanceof User) {
            return 'Geen actieve schutter gevonden in de huidige sessie.';
        }

        $sessions = (int) ($request['sessions'] ?? 6);
        $sessions = max(1, min(20, $sessions));

        $service = new ScoreDriftService($this->user);
        $averages = $service->perShotAverage($sessions);

        if ($averages === []) {
            return 'Nog onvoldoende sessies met schoten om score-drift te berekenen.';
        }

        $window = $service->weakestWindow(min(10, count($averages)), $sessions);

        $perShot = collect($averages)
            ->map(fn (float $avg, int $pos): string => "schot {$pos}: {$avg}")
            ->implode(', ');

        $windowText = $window !== null
            ? sprintf('Zwakste venster: schot %d–%d (gemiddeld %s).', $window['from'], $window['to'], $window['average'])
            : 'Geen duidelijk zwak venster.';

        return trim(sprintf(
            "Score-drift over laatste %d sessies (gemiddelde score per schot-positie):\n%s\n\n%s",
            $sessions,
            $perShot,
            $windowText,
        ));
    }
}
