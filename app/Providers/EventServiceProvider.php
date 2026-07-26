<?php

namespace App\Providers;

use App\Listeners\PromoteFirstUserToAdmin;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            PromoteFirstUserToAdmin::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}
