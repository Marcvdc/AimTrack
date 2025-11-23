<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedJob extends Model
{
    protected $table = 'failed_jobs';

    public $timestamps = false;

    protected $casts = [
        'failed_at' => 'datetime',
    ];

    protected $appends = [
        'job_name',
        'exception_message',
    ];

    public function getJobNameAttribute(): string
    {
        $payload = json_decode($this->payload, true) ?? [];

        return (string) (data_get($payload, 'displayName')
            ?? data_get($payload, 'job')
            ?? 'Onbekende job');
    }

    public function getExceptionMessageAttribute(): string
    {
        if (! $this->exception) {
            return '';
        }

        return str($this->exception)->before("\n")->value();
    }
}
