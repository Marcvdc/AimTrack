<?php

namespace App\Models;

use App\Enums\WeaponType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Weapon extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'weapon_type',
        'caliber',
        'serial_number',
        'storage_location',
        'owned_since',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'owned_since' => 'date',
        'is_active' => 'boolean',
        'weapon_type' => WeaponType::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sessionWeapons()
    {
        return $this->hasMany(SessionWeapon::class);
    }

    public function aiWeaponInsight()
    {
        return $this->hasOne(AiWeaponInsight::class);
    }
}
