<?php

namespace App\Providers;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Broadcast::routes();

        require base_path('routes/channels.php');
    }
}
