<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\FailedJobsWidget;
use App\Support\Features\AimtrackFeatureToggle;
use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;
use Filament\Actions\Action;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->registration()
            ->profile()
            ->brandLogo(fn (): HtmlString => new HtmlString(
                Blade::render('<x-aimtrack.wordmark size="28" />')
            ))
            ->darkModeBrandLogo(fn (): HtmlString => new HtmlString(
                Blade::render('<x-aimtrack.wordmark size="28" />')
            ))
            ->favicon(asset('img/aimtrack-logo.svg'))
            ->colors([
                'primary' => Color::hex('#64f4b3'),
            ])
            ->font('Inter')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->darkMode(true, isForced: true)
            ->sidebarWidth('14rem')
            ->navigationGroups(['LOG', 'INZICHT', 'BEHEER'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                FailedJobsWidget::class,
            ])
            ->userMenuItems([
                Action::make('landing-page')
                    ->label('Terug naar landingpage')
                    ->icon('heroicon-o-document-text')
                    ->url(fn (): string => route('welcome'))
                    ->openUrlInNewTab(),
            ])
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('filament.auth.login-extras')->render(),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_LOGO_AFTER,
                fn (): string => <<<'HTML'
                    <div style="margin-top: 0.375rem; font-family: var(--at-font-mono); font-size: 0.625rem; letter-spacing: 0.12em; color: var(--at-muted);">
                        self-hosted
                    </div>
                HTML,
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => $this->aimTrackDesignAssets(),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => <<<'HTML'
                    <style>
                        .copilot-chat-widget { max-width: min(1024px, 70vw) !important; }
                        /* De Copilot-widget heeft eigen CSS waarvan de dark:-varianten
                           niet consistent activeren onder het geforceerde dark panel,
                           wat wit-op-wit input gaf. Forceer leesbaar contrast. */
                        .copilot-chat-widget { background-color: var(--at-panel) !important; color: var(--at-text); }
                        .copilot-chat-widget form { background-color: var(--at-panel-2) !important; }
                        .copilot-chat-widget textarea,
                        .copilot-chat-widget input[type="text"] {
                            color: var(--at-text) !important;
                            -webkit-text-fill-color: var(--at-text) !important;
                            background-color: transparent !important;
                        }
                        .copilot-chat-widget textarea::placeholder,
                        .copilot-chat-widget input[type="text"]::placeholder {
                            color: var(--at-muted) !important;
                        }
                    </style>
                HTML,
            )
            ->middleware([
                // Core Laravel stack; uitbreidbaar met locale middleware.
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                FilamentCopilotPlugin::make()
                    ->provider('anthropic')
                    ->model('claude-haiku-4-5-20251001')
                    ->rateLimitEnabled()
                    ->memoryEnabled()
                    ->authorizeUsing(fn (): bool => app(AimtrackFeatureToggle::class)->aiEnabled()),
            ])
            ->databaseTransactions()
            ->spa();
    }

    /**
     * Inject design-token CSS variables (Signal Mint dark) and webfont links
     * into the Filament admin panel <head>. Source of truth lives at
     * resources/css/aimtrack-tokens.css and the design handoff under
     * .ai/design-handoff/project/design-tokens.jsx.
     */
    private function aimTrackDesignAssets(): string
    {
        $tokensPath = resource_path('css/aimtrack-tokens.css');
        $tokensCss = is_file($tokensPath) ? (string) file_get_contents($tokensPath) : '';

        return <<<HTML
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
            <style id="aimtrack-tokens">
            {$tokensCss}
            </style>
            HTML;
    }
}
