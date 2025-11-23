<?php

namespace App\Models;

use App\Enums\Deviation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionWeapon extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'weapon_id',
        'distance_m',
        'rounds_fired',
        'ammo_type',
        'group_quality_text',
        'deviation',
        'flyers_count',
    ];

    protected $casts = [
        'distance_m' => 'integer',
        'rounds_fired' => 'integer',
        'flyers_count' => 'integer',
        'deviation' => Deviation::class,
    ];

    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    public function weapon()
    {
        return $this->belongsTo(Weapon::class);
    }
}
