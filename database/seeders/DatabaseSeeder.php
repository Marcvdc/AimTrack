<?php

namespace Database\Seeders;

use App\Models\AmmoType;
use App\Models\Location;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\User;
use App\Models\Weapon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Seeding database...');

        // Create or update main user
        $mainUser = User::updateOrCreate(
            ['email' => 'marcvdcrommert@gmail.com'],
            [
                'name' => 'Marc van der Crommert',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✓ Main user created');

        // Create some additional test users
        $testUsers = User::factory()->count(2)->create();
        $allUsers = collect([$mainUser])->merge($testUsers);

        $this->command->info('✓ Test users created');

        // Create locations for the main user
        $storageLocation = Location::factory()->storage()->create([
            'user_id' => $mainUser->id,
            'name' => 'Thuis - Wapenkluis',
        ]);

        $rangeLocations = Location::factory()->range()->count(2)->create([
            'user_id' => $mainUser->id,
        ]);

        $this->command->info('✓ Locations created');

        // Create ammo types for the main user
        $ammoTypes = AmmoType::factory()->count(3)->create([
            'user_id' => $mainUser->id,
        ]);

        $this->command->info('✓ Ammo types created');

        // Create weapons for the main user
        $weapons = Weapon::factory()->count(3)->create([
            'user_id' => $mainUser->id,
        ]);

        $this->command->info('✓ Weapons created');

        // Create sessions with shots for the main user
        $sessions = Session::factory()->count(5)->create([
            'user_id' => $mainUser->id,
        ]);

        foreach ($sessions as $session) {
            // Create 15-30 shots per session across 3 turns
            $shotCount = rand(15, 30);
            $turnsCount = 3;
            $shotsPerTurn = (int) ceil($shotCount / $turnsCount);

            for ($turn = 0; $turn < $turnsCount; $turn++) {
                for ($shot = 0; $shot < $shotsPerTurn; $shot++) {
                    SessionShot::factory()->create([
                        'session_id' => $session->id,
                        'turn_index' => $turn,
                        'shot_index' => $shot,
                    ]);
                }
            }
        }

        $this->command->info('✓ Sessions with shots created');

        // Create a few sessions for test users
        foreach ($testUsers as $user) {
            $userSessions = Session::factory()->count(2)->create([
                'user_id' => $user->id,
            ]);

            foreach ($userSessions as $session) {
                for ($i = 0; $i < 10; $i++) {
                    SessionShot::factory()->create([
                        'session_id' => $session->id,
                        'turn_index' => 0,
                        'shot_index' => $i,
                    ]);
                }
            }
        }

        $this->command->info('✓ Test user sessions created');

        $this->command->info('');
        $this->command->info('🎉 Database seeded successfully!');
        $this->command->info('');
        $this->command->info('📧 Login credentials:');
        $this->command->info('   Email: marcvdcrommert@gmail.com');
        $this->command->info('   Password: password');
        $this->command->info('');
        $this->command->info('📊 Summary:');
        $this->command->info('   • Users: '.User::count());
        $this->command->info('   • Weapons: '.Weapon::count());
        $this->command->info('   • Locations: '.Location::count());
        $this->command->info('   • Ammo Types: '.AmmoType::count());
        $this->command->info('   • Sessions: '.Session::count());
        $this->command->info('   • Shots: '.SessionShot::count());

        $this->call([DevSeeder::class]);
    }
}
