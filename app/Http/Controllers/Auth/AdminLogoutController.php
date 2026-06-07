<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class AdminLogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->to(
                URL::temporarySignedRoute(
                    'filament.admin.auth.logout.get',
                    now()->addSeconds(5),
                ),
            );
        }

        Filament::auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return app(LogoutResponseContract::class)->toResponse($request);
    }
}
