<?php

namespace App\Models;

use App\Enums\VerenigingRol;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vereniging extends Model
{
    use HasFactory;

    protected $table = 'verenigingen';

    protected $fillable = [
        'naam',
        'slug',
        'anthropic_api_key',
        'ai_key_verified_at',
        'settings',
        'created_by',
    ];

    protected $hidden = [
        'anthropic_api_key',
    ];

    protected $casts = [
        'anthropic_api_key' => 'encrypted',
        'ai_key_verified_at' => 'datetime',
        'settings' => 'array',
    ];

    public function members()
    {
        return $this->belongsToMany(User::class, 'vereniging_user')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Users met een coach- of beheerdersrol binnen deze vereniging.
     */
    public function coaches()
    {
        return $this->members()->wherePivotIn('role', [
            VerenigingRol::Coach->value,
            VerenigingRol::Admin->value,
        ]);
    }

    /**
     * Users met de beheerdersrol binnen deze vereniging.
     */
    public function admins()
    {
        return $this->members()->wherePivot('role', VerenigingRol::Admin->value);
    }
}
