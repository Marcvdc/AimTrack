<?php

namespace App\Providers;

use App\Models\Session;
use App\Models\Weapon;
use App\Policies\SessionPolicy;
use App\Policies\WeaponPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Session::class => SessionPolicy::class,
        Weapon::class => WeaponPolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
