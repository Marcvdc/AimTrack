<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\User;
use App\Services\DemoDataSeeder;
use App\Services\SeedResult;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Eén gedeelde Filament Action voor alle "Demo-data inladen"-knoppen in
 * de Fase 2 empty states (Dashboard / ListSessions / ListWeapons /
 * CoachPage). Per-user gescoped en idempotent via
 * users.demo_data_seeded_at — de bevestigingsmodal beschermt tegen
 * per-ongeluk klikken in productie.
 */
trait HasSeedDemoDataAction
{
    public function seedDemoDataAction(): Action
    {
        return Action::make('seedDemoData')
            ->label('Demo-data inladen')
            ->icon('heroicon-o-sparkles')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Demo-data inladen?')
            ->modalDescription('Dit voegt 3 wapens, 5 sessies en 3 AI-reflecties toe aan jouw account zodat je de app kunt verkennen. Niet bedoeld voor echte sessies — je kunt deze records later handmatig verwijderen via de sessie- en wapenoverzichten.')
            ->modalSubmitActionLabel('Ja, laad demo-data')
            ->extraAttributes([
                'style' => 'padding: 10px 18px; border-radius: 8px; border: 1px solid var(--at-line); background: transparent; color: var(--at-text); font-size: 13px;',
                'data-testid' => 'seed-demo-data-trigger',
            ])
            ->action(function (): void {
                /** @var User $user */
                $user = Auth::user();

                $result = app(DemoDataSeeder::class)->seedFor($user);

                if ($result === SeedResult::AlreadyLoaded) {
                    Notification::make()
                        ->title('Demo-data was al geladen')
                        ->body('Op dit account is eerder demo-data ingeladen. Verwijder die handmatig als je opnieuw wilt seeden.')
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Demo-data geladen')
                    ->body('3 wapens, 5 sessies en 3 AI-reflecties zijn toegevoegd. Ververs de pagina om alles te zien.')
                    ->success()
                    ->send();
            });
    }
}
