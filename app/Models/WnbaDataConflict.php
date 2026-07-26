<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WnbaDataConflict extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_key',
        'field',
        'candidates',
        'selected_value',
        'selected_source',
        'resolution_reason',
        'confidence',
        'requires_review',
        'resolved_at',
        'agent_run_id',
    ];

    protected $casts = [
        'candidates' => 'array',
        'confidence' => 'float',
        'requires_review' => 'boolean',
        'resolved_at' => 'datetime',
    ];
}
