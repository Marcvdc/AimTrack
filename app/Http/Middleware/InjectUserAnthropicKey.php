<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Ai\AiKeyResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectUserAnthropicKey
{
    public function __construct(private readonly AiKeyResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('copilot/stream')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user instanceof User) {
            $key = $this->resolver->forUser($user);

            if (filled($key)) {
                config(['ai.providers.anthropic.key' => $key]);
                // Veiligheidsnet: forceer herresolve mocht laravel/ai de provider al hebben opgebouwd.
                app(\Laravel\Ai\AiManager::class)->forgetInstance('anthropic');
            }
        }

        return $next($request);
    }
}
