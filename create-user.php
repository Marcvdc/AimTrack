<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$email = 'marcvdcrommert@gmail.com';
$user = User::where('email', $email)->first();

if ($user) {
    echo "User exists: {$user->email}\n";
    echo "ID: {$user->id}\n";
    echo "Name: {$user->name}\n";
    echo "Created: {$user->created_at}\n";
} else {
    echo "User NOT found with email: {$email}\n";
    echo "Creating new user...\n";

    $user = User::create([
        'name' => 'Marc van der Crommert',
        'email' => $email,
        'password' => Hash::make('password'), // Change this!
    ]);

    echo "User created successfully!\n";
    echo "Email: {$user->email}\n";
    echo "Password: password (please change this!)\n";
}
