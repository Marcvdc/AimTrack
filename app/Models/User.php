<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    /**
     * Bekende voorkeuren + hun defaults. Dient als whitelist: onbekende keys
     * worden door setPreference() genegeerd.
     *
     * @var array<string, bool>
     */
    public const PREFERENCE_DEFAULTS = [
        'board_show_rings' => false,
        'decimal_notation' => true,
        'auto_ai_reflection' => false,
    ];

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
        'preferences' => 'array',
    ];

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Lees een gebruikersvoorkeur; valt terug op de default uit
     * PREFERENCE_DEFAULTS, of null voor een onbekende key.
     */
    public function preference(string $key): mixed
    {
        $stored = $this->preferences ?? [];

        return $stored[$key] ?? self::PREFERENCE_DEFAULTS[$key] ?? null;
    }

    /**
     * Schrijf een bekende voorkeur weg. Onbekende keys worden genegeerd
     * (whitelist via PREFERENCE_DEFAULTS).
     */
    public function setPreference(string $key, mixed $value): void
    {
        if (! array_key_exists($key, self::PREFERENCE_DEFAULTS)) {
            return;
        }

        $preferences = $this->preferences ?? [];
        $preferences[$key] = $value;
        $this->preferences = $preferences;
        $this->save();
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
