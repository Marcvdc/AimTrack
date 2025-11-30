<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'is_storage',
        'is_range',
        'notes',
    ];

    protected $casts = [
        'is_storage' => 'boolean',
        'is_range' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function weapons()
    {
        return $this->hasMany(Weapon::class, 'storage_location_id');
    }

    public function sessions()
    {
        return $this->hasMany(Session::class, 'location_id');
    }

    public function rangeSessions()
    {
        return $this->hasMany(Session::class, 'range_location_id');
    }
}
