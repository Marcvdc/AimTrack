<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as ActionsSchema;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form as FormSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Http;

class AiSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'AI-instellingen';

    protected static ?string $title = 'AI-instellingen';

    protected static string|\UnitEnum|null $navigationGroup = 'BEHEER';

    protected string $view = 'filament.pages.ai-settings-page';

    public ?array $data = [];

    public function mount(): void
    {
        // Toon de bestaande key niet; alleen of er een key is (via placeholder).
        $this->form->fill(['anthropic_api_key' => null]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Claude API-key')
                    ->schema([
                        TextInput::make('anthropic_api_key')
                            ->label('Claude API-key')
                            ->password()
                            ->revealable()
                            ->autocomplete(false)
                            ->placeholder($this->keyPlaceholder())
                            ->helperText('Je eigen Anthropic (Claude) API-key. Wordt versleuteld opgeslagen en nooit volledig getoond. Laat leeg om je huidige key te behouden.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                FormSchema::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        ActionsSchema::make($this->getFormActions()),
                    ]),
            ]);
    }

    public function save(): void
    {
        $key = data_get($this->form->getState(), 'anthropic_api_key')
            ?? data_get($this->data, 'anthropic_api_key');

        if (blank($key)) {
            Notification::make()->title('Geen key ingevoerd')->warning()->send();

            return;
        }

        $this->currentUser()->update([
            'anthropic_api_key' => $key,
            'ai_key_verified_at' => null,
        ]);

        $this->form->fill(['anthropic_api_key' => null]);

        Notification::make()->title('Key opgeslagen')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Opslaan')
                ->submit('save')
                ->icon('heroicon-m-check'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('Test key')
                ->icon('heroicon-m-bolt')
                ->action(fn () => $this->testKey()),
            Action::make('clear')
                ->label('Wis key')
                ->color('danger')
                ->requiresConfirmation()
                ->action(fn () => $this->clearKey()),
        ];
    }

    protected function testKey(): void
    {
        $key = $this->currentUser()->anthropic_api_key;

        if (blank($key)) {
            Notification::make()->title('Geen key om te testen')->warning()->send();

            return;
        }

        $response = Http::baseUrl(config('ai.base_url', 'https://api.anthropic.com'))
            ->withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => config('ai.anthropic_version', '2023-06-01'),
            ])
            ->connectTimeout(5)
            ->timeout(20)
            ->get('/v1/models');

        if ($response->successful()) {
            $this->currentUser()->update(['ai_key_verified_at' => now()]);
            Notification::make()->title('Key is geldig')->success()->send();

            return;
        }

        Notification::make()
            ->title('Key ongeldig')
            ->body('Anthropic gaf status '.$response->status().' terug.')
            ->danger()
            ->send();
    }

    protected function clearKey(): void
    {
        $this->currentUser()->update([
            'anthropic_api_key' => null,
            'ai_key_verified_at' => null,
        ]);

        Notification::make()->title('Key gewist')->success()->send();
    }

    protected function keyPlaceholder(): string
    {
        $key = $this->currentUser()->anthropic_api_key;

        return filled($key)
            ? 'Huidige key: ••••'.substr($key, -4)
            : 'sk-ant-…';
    }

    protected function currentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
