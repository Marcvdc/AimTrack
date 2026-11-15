<?php

declare(strict_types=1);

use App\Models\User;
use EslamRedaDiv\FilamentCopilot\Agent\CopilotAgent;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Streaming\Events\TextDelta;

function makeCopilotAgent(User $user): CopilotAgent
{
    return app(CopilotAgent::class)
        ->forPanel('admin')
        ->forTenant(null)
        ->withTools([])
        ->forUser($user);
}

it('slaat een gestreamde copilot-beurt op in de laravel/ai-conversatietabellen', function (): void {
    CopilotAgent::fake(['Je laatste sessie liet een stabiele groepering zien.']);
    $user = User::factory()->create();

    $streamResponse = makeCopilotAgent($user)->stream('Hoe ging mijn laatste sessie?');

    $conversationId = null;
    $streamResponse->then(function ($response) use (&$conversationId): void {
        $conversationId = $response->conversationId;
    });

    $responseText = '';
    foreach ($streamResponse as $event) {
        if ($event instanceof TextDelta) {
            $responseText .= $event->delta;
        }
    }

    expect($responseText)->toBe('Je laatste sessie liet een stabiele groepering zien.')
        ->and($conversationId)->not->toBeNull();

    $this->assertDatabaseHas('agent_conversations', [
        'id' => $conversationId,
        'participant_type' => Conversation::participantType($user),
        'participant_id' => $user->getKey(),
    ]);
    $this->assertDatabaseHas('agent_conversation_messages', [
        'conversation_id' => $conversationId,
        'role' => 'user',
        'content' => 'Hoe ging mijn laatste sessie?',
    ]);
    $this->assertDatabaseHas('agent_conversation_messages', [
        'conversation_id' => $conversationId,
        'role' => 'assistant',
        'content' => 'Je laatste sessie liet een stabiele groepering zien.',
    ]);
});

it('laat een vervolgbeurt de opgeslagen conversatie als context meenemen', function (): void {
    CopilotAgent::fake(['Eerste antwoord.']);
    $user = User::factory()->create();

    $first = makeCopilotAgent($user)->prompt('Eerste vraag');

    $followUp = app(CopilotAgent::class)
        ->forPanel('admin')
        ->forTenant(null)
        ->withTools([])
        ->continue($first->conversationId, as: $user);

    expect(collect($followUp->messages())->pluck('content')->all())
        ->toBe(['Eerste vraag', 'Eerste antwoord.']);

    $followUp->prompt('Tweede vraag');

    $this->assertDatabaseCount('agent_conversations', 1);
    $this->assertDatabaseCount('agent_conversation_messages', 4);
});
