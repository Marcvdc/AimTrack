<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Centraliseert de onboarding-state checks die door Fase 2 empty
 * states gedeeld worden. Eén plek voor "heeft deze gebruiker al
 * data?"-vragen voorkomt duplicate queries verspreid over Pages
 * en Resources.
 */
final class UserOnboardingState
{
    private ?bool $hasFirstWeapon = null;

    private ?bool $hasFirstSession = null;

    private ?int $sessionsCount = null;

    public function __construct(public readonly User $user) {}

    public function hasNoData(): bool
    {
        return ! $this->hasFirstWeapon() && ! $this->hasFirstSession();
    }

    public function hasFirstWeapon(): bool
    {
        return $this->hasFirstWeapon ??= $this->user->weapons()->exists();
    }

    public function hasFirstSession(): bool
    {
        return $this->hasFirstSession ??= $this->user->sessions()->exists();
    }

    public function sessionsCount(): int
    {
        return $this->sessionsCount ??= $this->user->sessions()->count();
    }

    public function aiCoachUnlocked(): bool
    {
        return $this->sessionsCount() >= self::aiCoachThreshold();
    }

    public static function aiCoachThreshold(): int
    {
        return 3;
    }
}
