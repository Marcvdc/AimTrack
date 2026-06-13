<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Weapon;

class WeaponPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Weapon $weapon): bool
    {
        if ($this->owns($user, $weapon)) {
            return true;
        }

        return $weapon->user !== null && $user->isCoachVan($weapon->user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Weapon $weapon): bool
    {
        return $this->owns($user, $weapon);
    }

    public function delete(User $user, Weapon $weapon): bool
    {
        return $this->owns($user, $weapon);
    }

    public function restore(User $user, Weapon $weapon): bool
    {
        return $this->owns($user, $weapon);
    }

    public function forceDelete(User $user, Weapon $weapon): bool
    {
        return $this->owns($user, $weapon);
    }

    private function owns(User $user, Weapon $weapon): bool
    {
        return $weapon->user_id === $user->id;
    }
}
