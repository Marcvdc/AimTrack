<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionTurnAnalysis extends Model
{
    protected $fillable = [
        'session_id',
        'turn_index',
        'needs_review',
        'review_reason',
        'overall_confidence',
        'expected_shot_count',
        'detected_count',
        'count_matches_expected',
        'calibration_rms_mm',
        'vision_model',
        'analyzed_at',
    ];

    protected $casts = [
        'turn_index' => 'integer',
        'needs_review' => 'boolean',
        'overall_confidence' => 'float',
        'expected_shot_count' => 'integer',
        'detected_count' => 'integer',
        'count_matches_expected' => 'boolean',
        'calibration_rms_mm' => 'float',
        'analyzed_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(Session::class);
    }
}
