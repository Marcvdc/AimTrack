<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TrainingGoalSource;
use App\Models\TrainingGoal;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Beheert trainingsdoelen van een schutter. Doelen komen van de gebruiker zelf
 * (manual) of van de AI-coach (ai, via de Copilot-tool) — zie het ontwerp
 * "Voorgestelde doelen" + "Voeg doel toe".
 */
final class TrainingGoalService
{
    /**
     * Open (niet-afgeronde) doelen, nieuwste eerst.
     *
     * @return Collection<int, TrainingGoal>
     */
    public function openGoals(User $user, int $limit = 5): Collection
    {
        return $user->trainingGoals()
            ->open()
            ->latest()
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function add(
        User $user,
        string $title,
        ?string $detail = null,
        TrainingGoalSource $source = TrainingGoalSource::Manual,
        ?string $targetMonth = null,
        ?int $sessionId = null,
        ?int $weaponId = null,
    ): TrainingGoal {
        return $user->trainingGoals()->create([
            'title' => $title,
            'detail' => $detail,
            'source' => $source,
            'target_month' => $targetMonth,
            'session_id' => $sessionId,
            'weapon_id' => $weaponId,
        ]);
    }

    public function complete(TrainingGoal $goal): TrainingGoal
    {
        if ($goal->completed_at === null) {
            $goal->update(['completed_at' => now()]);
        }

        return $goal;
    }
}
