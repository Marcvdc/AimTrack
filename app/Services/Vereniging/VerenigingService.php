<?php

namespace App\Services\Vereniging;

use App\Enums\VerenigingRol;
use App\Models\User;
use App\Models\Vereniging;
use Illuminate\Support\Facades\Http;

class VerenigingService
{
    /**
     * Koppel een bestaande gebruiker (op e-mailadres) als lid aan de vereniging.
     * v1: alleen bestaande accounts; onbekende e-mails geven een nette fout.
     */
    public function voegLidToe(Vereniging $vereniging, string $email, VerenigingRol $rol): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw VerenigingException::gebruikerNietGevonden($email);
        }

        if ($vereniging->members()->whereKey($user->id)->exists()) {
            throw VerenigingException::alLid();
        }

        $vereniging->members()->attach($user, [
            'role' => $rol->value,
            'joined_at' => now(),
        ]);

        if ($user->active_vereniging_id === null) {
            $user->update(['active_vereniging_id' => $vereniging->id]);
        }

        return $user;
    }

    /**
     * Wijzig de rol van een lid. Beschermt de laatste beheerder tegen degradatie.
     */
    public function wijzigRol(Vereniging $vereniging, User $user, VerenigingRol $rol): void
    {
        $huidige = $this->rolVan($vereniging, $user);

        if ($huidige === null) {
            throw VerenigingException::geenLid();
        }

        if ($huidige === VerenigingRol::Admin
            && $rol !== VerenigingRol::Admin
            && $this->aantalBeheerders($vereniging) <= 1) {
            throw VerenigingException::laatsteBeheerder();
        }

        $vereniging->members()->updateExistingPivot($user->id, ['role' => $rol->value]);
    }

    /**
     * Verwijder een lid. Beschermt de laatste beheerder tegen verwijdering.
     */
    public function verwijderLid(Vereniging $vereniging, User $user): void
    {
        $huidige = $this->rolVan($vereniging, $user);

        if ($huidige === null) {
            throw VerenigingException::geenLid();
        }

        if ($huidige === VerenigingRol::Admin && $this->aantalBeheerders($vereniging) <= 1) {
            throw VerenigingException::laatsteBeheerder();
        }

        $vereniging->members()->detach($user->id);

        if ($user->active_vereniging_id === $vereniging->id) {
            $user->update(['active_vereniging_id' => null]);
        }
    }

    /**
     * Sla de gedeelde Claude-key op (encrypted via cast) en reset de verificatie.
     */
    public function bewaarKey(Vereniging $vereniging, string $key): void
    {
        $vereniging->update([
            'anthropic_api_key' => $key,
            'ai_key_verified_at' => null,
        ]);
    }

    public function wisKey(Vereniging $vereniging): void
    {
        $vereniging->update([
            'anthropic_api_key' => null,
            'ai_key_verified_at' => null,
        ]);
    }

    /**
     * Valideer de gedeelde key tegen Anthropic. Markeert bij succes geverifieerd.
     */
    public function testKey(Vereniging $vereniging): bool
    {
        $key = $vereniging->anthropic_api_key;

        if (blank($key)) {
            return false;
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
            $vereniging->update(['ai_key_verified_at' => now()]);

            return true;
        }

        return false;
    }

    private function rolVan(Vereniging $vereniging, User $user): ?VerenigingRol
    {
        $pivot = $vereniging->members()->whereKey($user->id)->first()?->pivot;

        return $pivot !== null ? VerenigingRol::tryFrom($pivot->role) : null;
    }

    private function aantalBeheerders(Vereniging $vereniging): int
    {
        return $vereniging->admins()->count();
    }
}
