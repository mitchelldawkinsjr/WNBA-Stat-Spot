<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WnbaAgentRun extends Model
{
    protected $fillable = [
        'run_uuid',
        'agent',
        'mode',
        'status',
        'season',
        'date_from',
        'date_to',
        'dry_run',
        'counters',
        'warnings',
        'errors',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'season' => 'integer',
        'date_from' => 'date',
        'date_to' => 'date',
        'dry_run' => 'boolean',
        'counters' => 'array',
        'warnings' => 'array',
        'errors' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
