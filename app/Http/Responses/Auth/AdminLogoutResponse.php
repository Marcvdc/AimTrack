<?php

namespace App\Http\Responses\Auth;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class AdminLogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $request->session()->flash('status', 'Je bent uitgelogd.');

        $redirectUrl = Filament::hasLogin()
            ? Filament::getLoginUrl()
            : Filament::getUrl();

        return redirect()->to($redirectUrl);
    }
}
