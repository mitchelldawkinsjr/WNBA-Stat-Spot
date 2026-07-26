<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WnbaDailyInsight extends Model
{
    protected $fillable = [
        'season', 'insight_date', 'insight_type', 'entity_type', 'entity_id',
        'title', 'body', 'priority', 'payload', 'formula_version', 'computed_at', 'agent_run_id',
    ];

    protected $casts = [
        'season' => 'integer',
        'insight_date' => 'date',
        'priority' => 'integer',
        'payload' => 'array',
        'computed_at' => 'datetime',
    ];
}
