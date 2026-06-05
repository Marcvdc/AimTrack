<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'demo_data_seeded_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
    ];

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function sessions()
    {
        return $this->hasMany(Session::class);
    }

    public function weapons()
    {
        return $this->hasMany(Weapon::class);
    }

    public function trainingGoals()
    {
        return $this->hasMany(TrainingGoal::class);
    }

    public function coachQuestions()
    {
        return $this->hasMany(CoachQuestion::class);
    }
}
