<?php

namespace App\Models;

use App\Models\Concerns\MasksAiFailureSummary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiReflection extends Model
{
    use HasFactory;
    use MasksAiFailureSummary;

    public const FAILED_SUMMARY = 'De AI-reflectie kon niet gemaakt worden. Probeer het later opnieuw.';

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
