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
    ];

    protected $casts = [
        'positives' => 'array',
        'improvements' => 'array',
    ];

    public function session()
    {
        return $this->belongsTo(Session::class);
    }
}
