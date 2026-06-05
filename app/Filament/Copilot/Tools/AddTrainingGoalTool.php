<?php

declare(strict_types=1);

namespace App\Filament\Copilot\Tools;

use App\Enums\TrainingGoalSource;
use App\Models\User;
use App\Services\TrainingGoalService;
use EslamRedaDiv\FilamentCopilot\Tools\BaseTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class AddTrainingGoalTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Legt een concreet trainingsdoel vast voor de huidige schutter (verschijnt onder "Voorgestelde doelen"). Gebruik dit alleen nadat de schutter akkoord is met een voorgesteld doel, of als de schutter expliciet om een doel vraagt. Houd de titel kort en actiegericht (bv. "Micro-pauze na schot 30").';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Korte, actiegerichte titel van het trainingsdoel (max ~60 tekens).')->required(),
            'detail' => $schema->string()->description('Optionele toelichting: hoe het doel uit te voeren of waarom.'),
            'target_month' => $schema->string()->description('Optionele doelmaand in formaat JJJJ-MM, bv. "2026-06".'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->user instanceof User) {
            return 'Geen actieve schutter gevonden in de huidige sessie.';
        }

        $title = trim((string) ($request['title'] ?? ''));

        if ($title === '') {
            return 'Een trainingsdoel heeft minimaal een titel nodig.';
        }

        $detail = filled($request['detail'] ?? null) ? (string) $request['detail'] : null;
        $targetMonth = null;

        if (filled($request['target_month'] ?? null) && preg_match('/^\d{4}-\d{2}$/', (string) $request['target_month']) === 1) {
            $targetMonth = (string) $request['target_month'];
        }

        $goal = app(TrainingGoalService::class)->add(
            user: $this->user,
            title: $title,
            detail: $detail,
            source: TrainingGoalSource::Ai,
            targetMonth: $targetMonth,
        );

        return sprintf(
            'Trainingsdoel toegevoegd (#%d): "%s"%s. Het staat nu onder Voorgestelde doelen.',
            $goal->id,
            $goal->title,
            $targetMonth !== null ? " voor {$targetMonth}" : '',
        );
    }
}
