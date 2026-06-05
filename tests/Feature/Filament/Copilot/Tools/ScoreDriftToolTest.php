<?php

declare(strict_types=1);

use App\Filament\Copilot\Tools\ScoreDriftTool;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\User;
use Laravel\Ai\Tools\Request;

function makeScoreDriftTool(User $user): ScoreDriftTool
{
    return (new ScoreDriftTool)
        ->forPanel('admin')
        ->forUser($user)
        ->forTenant(null)
        ->forConversation(null);
}

it('summarises score drift and the weakest window', function (): void {
    $user = User::factory()->create();
    $session = Session::factory()->for($user)->create(['date' => now()->subDay()]);
    foreach ([10, 10, 10, 10, 10, 8, 8, 8, 8, 8] as $i => $score) {
        SessionShot::factory()->for($session)->create(['turn_index' => 0, 'shot_index' => $i, 'ring' => $score, 'score' => $score]);
    }

    $output = (string) makeScoreDriftTool($user)->handle(new Request);

    expect($output)->toContain('Score-drift')
        ->and($output)->toContain('Zwakste venster');
});

it('reports when there is not enough data', function (): void {
    $output = (string) makeScoreDriftTool(User::factory()->create())->handle(new Request);

    expect($output)->toContain('onvoldoende');
});
