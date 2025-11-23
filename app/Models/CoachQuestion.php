<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoachQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'weapon_id',
        'question',
        'answer',
        'asked_at',
        'period_from',
        'period_to',
    ];

    protected $casts = [
        'asked_at' => 'datetime',
        'period_from' => 'date',
        'period_to' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function weapon()
    {
        return $this->belongsTo(Weapon::class);
    }
}
