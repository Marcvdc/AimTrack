<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiReflection extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'summary',
        'positives',
        'improvements',
        'next_focus',
        'acknowledged_at',
    ];

    protected $casts = [
        'positives' => 'array',
        'improvements' => 'array',
        'acknowledged_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(Session::class);
    }
}
