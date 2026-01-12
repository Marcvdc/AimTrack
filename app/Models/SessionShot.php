<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionShot extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'turn_index',
        'shot_index',
        'x_normalized',
        'y_normalized',
        'distance_from_center',
        'ring',
        'score',
        'metadata',
    ];

    protected $casts = [
        'turn_index' => 'integer',
        'shot_index' => 'integer',
        'x_normalized' => 'float',
        'y_normalized' => 'float',
        'distance_from_center' => 'float',
        'ring' => 'integer',
        'score' => 'integer',
        'metadata' => 'array',
    ];

    public function session()
    {
        return $this->belongsTo(Session::class);
    }
}
