<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateTestUser extends Command
{
    protected $signature = 'app:create-test-user';

    protected $description = 'Create a test user for development';

    public function handle(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'test@aimtrack.nl'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        $this->info("User ready: {$user->email}");
    }
}
