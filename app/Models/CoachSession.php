<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachSession extends Model
{
    protected $table = 'coach_questions';
    
    protected $primaryKey = 'session_id';
    
    public $incrementing = false;
    
    protected $fillable = [
        'session_id',
        'started_at',
        'message_count',
        'last_activity',
        'first_question',
    ];
    
    protected $casts = [
        'started_at' => 'datetime',
        'last_activity' => 'datetime',
        'message_count' => 'integer',
    ];
    
    public function getKeyName()
    {
        return 'session_id';
    }
    
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId)
            ->whereNotNull('session_id')
            ->selectRaw('
                session_id, 
                MIN(asked_at) as started_at, 
                COUNT(*) as message_count, 
                MAX(asked_at) as last_activity, 
                MIN(question) as first_question
            ')
            ->groupBy('session_id');
    }
    
    public function scopeExcludeCurrent($query, $sessionId)
    {
        return $query->when($sessionId, fn ($q) => $q->where('session_id', '!=', $sessionId));
    }
}
