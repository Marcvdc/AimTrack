<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * De uitkomst van de foto-analyse van één beurt.
 *
 * Bestaat los van de schoten omdat een analyse ook iets te zeggen heeft als er
 * nul schoten uitkwamen: dan wil je juist weten dat er gecontroleerd moet worden.
 */
class SessionTurnAnalysis extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'session_id',
        'turn_index',
        'status',
        'needs_review',
        'review_reason',
        'expected_shot_count',
        'detected_count',
        'dropped_low_confidence',
        'rejected_by_model',
        'overall_confidence',
        'photo_path',
        'model',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'turn_index' => 'integer',
            'needs_review' => 'boolean',
            'expected_shot_count' => 'integer',
            'detected_count' => 'integer',
            'dropped_low_confidence' => 'integer',
            'rejected_by_model' => 'integer',
            'overall_confidence' => 'float',
            'metadata' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
