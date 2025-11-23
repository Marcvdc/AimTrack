<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'range_name',
        'location',
        'notes_raw',
        'manual_reflection',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sessionWeapons()
    {
        return $this->hasMany(SessionWeapon::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    public function aiReflection()
    {
        return $this->hasOne(AiReflection::class);
    }
}
