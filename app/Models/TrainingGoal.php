<?php

namespace App\Models;

use App\Enums\TrainingGoalSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingGoal extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingGoalFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'detail',
        'source',
        'target_month',
        'session_id',
        'weapon_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => TrainingGoalSource::class,
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function weapon(): BelongsTo
    {
        return $this->belongsTo(Weapon::class);
    }

    /**
     * @param  Builder<TrainingGoal>  $query
     * @return Builder<TrainingGoal>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
