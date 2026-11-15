<?php

namespace App\Models;

use App\Models\Concerns\MasksAiFailureSummary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiWeaponInsight extends Model
{
    use HasFactory;
    use MasksAiFailureSummary;

    public const FAILED_SUMMARY = 'Het AI-wapeninzicht kon niet gemaakt worden. Probeer het later opnieuw.';

    protected $fillable = [
        'weapon_id',
        'summary',
        'patterns',
        'suggestions',
    ];

    protected $casts = [
        'patterns' => 'array',
        'suggestions' => 'array',
    ];

    public function weapon()
    {
        return $this->belongsTo(Weapon::class);
    }
}
