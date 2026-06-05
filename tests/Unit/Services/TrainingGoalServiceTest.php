<?php

declare(strict_types=1);

use App\Enums\TrainingGoalSource;
use App\Models\TrainingGoal;
use App\Models\User;
use App\Services\TrainingGoalService;

it('lists only open goals, newest first, scoped to the user', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $older = TrainingGoal::factory()->create(['user_id' => $user->id, 'title' => 'Ouder doel']);
    $newer = TrainingGoal::factory()->create(['user_id' => $user->id, 'title' => 'Nieuwer doel']);
    TrainingGoal::factory()->completed()->create(['user_id' => $user->id, 'title' => 'Afgerond']);
    TrainingGoal::factory()->create(['user_id' => $other->id]);

    $goals = (new TrainingGoalService)->openGoals($user);

    expect($goals)->toHaveCount(2)
        ->and($goals->first()->id)->toBe($newer->id)
        ->and($goals->pluck('title')->all())->not->toContain('Afgerond');
});

it('adds a manual goal by default', function (): void {
    $user = User::factory()->create();

    $goal = (new TrainingGoalService)->add($user, 'Micro-pauze schot 30', '30s adem-reset');

    expect($goal->user_id)->toBe($user->id)
        ->and($goal->title)->toBe('Micro-pauze schot 30')
        ->and($goal->source)->toBe(TrainingGoalSource::Manual)
        ->and($goal->completed_at)->toBeNull();
});

it('adds an AI-sourced goal', function (): void {
    $user = User::factory()->create();

    $goal = (new TrainingGoalService)->add($user, 'Cadans vasthouden', source: TrainingGoalSource::Ai, targetMonth: '2026-06');

    expect($goal->source)->toBe(TrainingGoalSource::Ai)
        ->and($goal->target_month)->toBe('2026-06');
});

it('completes a goal idempotently', function (): void {
    $goal = TrainingGoal::factory()->create();
    $service = new TrainingGoalService;

    $service->complete($goal);
    $firstCompletedAt = $goal->fresh()->completed_at;

    expect($firstCompletedAt)->not->toBeNull();

    $service->complete($goal->fresh());
    expect($goal->fresh()->completed_at->equalTo($firstCompletedAt))->toBeTrue();
});
