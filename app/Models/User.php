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
        'anthropic_api_key',
        'ai_key_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'anthropic_api_key',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'demo_data_seeded_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'anthropic_api_key' => 'encrypted',
        'ai_key_verified_at' => 'datetime',
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
