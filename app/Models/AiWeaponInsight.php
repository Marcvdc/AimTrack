<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiWeaponInsight extends Model
{
    use HasFactory;

    protected $fillable = [
        'weapon_id',
        'summary',
        'patterns',
        'suggestions',
    ];

    protected $casts = [
        'patterns' => 'array',
        'suggestions' => 'array',
    ];

    public function weapon()
    {
        return $this->belongsTo(Weapon::class);
    }
}
