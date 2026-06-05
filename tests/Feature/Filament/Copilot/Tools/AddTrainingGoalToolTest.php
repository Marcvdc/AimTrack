<?php

declare(strict_types=1);

use App\Enums\TrainingGoalSource;
use App\Filament\Copilot\Tools\AddTrainingGoalTool;
use App\Models\TrainingGoal;
use App\Models\User;
use Laravel\Ai\Tools\Request;

function makeAddTrainingGoalTool(User $user): AddTrainingGoalTool
{
    return (new AddTrainingGoalTool)
        ->forPanel('admin')
        ->forUser($user)
        ->forTenant(null)
        ->forConversation(null);
}

it('adds an AI-sourced training goal for the active shooter', function (): void {
    $user = User::factory()->create();

    $output = (string) makeAddTrainingGoalTool($user)->handle(new Request([
        'title' => 'Micro-pauze na schot 30',
        'detail' => '30s adem-reset',
        'target_month' => '2026-06',
    ]));

    expect($output)->toContain('Trainingsdoel toegevoegd');

    $goal = TrainingGoal::query()->where('user_id', $user->id)->first();
    expect($goal)->not->toBeNull()
        ->and($goal->title)->toBe('Micro-pauze na schot 30')
        ->and($goal->source)->toBe(TrainingGoalSource::Ai)
        ->and($goal->target_month)->toBe('2026-06');
});

it('rejects an empty title', function (): void {
    $user = User::factory()->create();

    $output = (string) makeAddTrainingGoalTool($user)->handle(new Request(['title' => '   ']));

    expect($output)->toContain('minimaal een titel')
        ->and(TrainingGoal::query()->count())->toBe(0);
});

it('ignores an invalid target_month format', function (): void {
    $user = User::factory()->create();

    makeAddTrainingGoalTool($user)->handle(new Request(['title' => 'Cadans vasthouden', 'target_month' => 'juni']));

    expect(TrainingGoal::query()->first()->target_month)->toBeNull();
});
