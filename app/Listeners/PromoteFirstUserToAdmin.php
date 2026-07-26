<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Registered;

/**
 * Maakt uitsluitend de allereerste geregistreerde gebruiker automatisch
 * app-beheerder (users.is_admin). Zo heeft een self-hosted install (Docker e.d.)
 * meteen een beheerder, zonder dat elke latere registratie admin-rechten krijgt.
 *
 * Bewust gekoppeld aan het Registered-event (echte registratie), niet aan een
 * model-event: seeders/factories mogen geen users promoveren.
 */
class PromoteFirstUserToAdmin
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        if ($user->is_admin) {
            return;
        }

        if (User::query()->count() === 1) {
            $user->forceFill(['is_admin' => true])->save();
        }
    }
}
