<?php

namespace App\Policies;

use App\Models\Session;
use App\Models\User;

class SessionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Session $session): bool
    {
        if ($this->owns($user, $session)) {
            return true;
        }

        return $session->user !== null && $user->isCoachVan($session->user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Session $session): bool
    {
        return $this->owns($user, $session);
    }

    public function delete(User $user, Session $session): bool
    {
        return $this->owns($user, $session);
    }

    public function restore(User $user, Session $session): bool
    {
        return $this->owns($user, $session);
    }

    public function forceDelete(User $user, Session $session): bool
    {
        return $this->owns($user, $session);
    }

    private function owns(User $user, Session $session): bool
    {
        return $session->user_id === $user->id;
    }
}
