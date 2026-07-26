<?php

declare(strict_types=1);

namespace App\Enums;

enum VerenigingRol: string
{
    case Member = 'member';
    case Coach = 'coach';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Lid',
            self::Coach => 'Coach',
            self::Admin => 'Beheerder',
        };
    }

    public function canCoach(): bool
    {
        return $this === self::Coach || $this === self::Admin;
    }

    public function canManage(): bool
    {
        return $this === self::Admin;
    }
}
