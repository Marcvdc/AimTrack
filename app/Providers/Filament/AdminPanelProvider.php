<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\FailedJobsWidget;
use Filament\Actions\Action;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;

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
            ->brandLogo(new HtmlString('<img src="/test.svg" alt="AimTrack" class="h-8">'))
            ->darkModeBrandLogo(new HtmlString('<img src="/test.svg" alt="AimTrack" class="h-8" style="filter: invert(1);">'))
            ->favicon('/test.svg')
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->font('Inter')
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
            ->middleware([
                // Core Laravel stack; uitbreidbaar met locale middleware.
                \Illuminate\Cookie\Middleware\EncryptCookies::class,
                \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
                \Illuminate\Session\Middleware\StartSession::class,
                \Illuminate\View\Middleware\ShareErrorsFromSession::class,
                \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
                \Illuminate\Routing\Middleware\SubstituteBindings::class,
            ])
            ->authMiddleware([
                \Illuminate\Auth\Middleware\Authenticate::class,
            ])
            ->databaseTransactions()
            ->spa();
    }
}
