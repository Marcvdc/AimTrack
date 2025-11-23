<?php

namespace App\Http\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function redirectTo($request): ?string
    {
        if (! $request->expectsJson()) {
            return route('login');
        }

        return null;
    }

    protected function unauthenticated($request, array $guards): void
    {
        throw new AuthenticationException('Unauthenticated.', $guards, $this->redirectTo($request));
    }
}
