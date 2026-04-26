<?php

use App\Filament\Pages\CoachPage;
use App\Models\User;
use Laravel\Pennant\Feature;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders the coach page for an authenticated user', function () {
    Feature::activate('aimtrack-ai');

    $response = $this->get(CoachPage::getUrl());

    $response->assertOk();
    $response->assertSee('AI-coach');
    $response->assertSee('Open de chat');
});

it('hides coach page from navigation when AI feature is disabled', function () {
    Feature::deactivate('aimtrack-ai');

    expect(CoachPage::shouldRegisterNavigation())->toBeFalse();
});

it('shows coach page in navigation when AI feature is enabled', function () {
    Feature::activate('aimtrack-ai');

    expect(CoachPage::shouldRegisterNavigation())->toBeTrue();
});

it('exposes copilot tools and description', function () {
    expect(CoachPage::copilotPageDescription())->toContain('AI-coach');
    expect(CoachPage::copilotTools())->toHaveCount(1);
    expect(CoachPage::copilotTools()[0])->toBeInstanceOf(\App\Filament\Copilot\Tools\ShooterContextTool::class);
});
