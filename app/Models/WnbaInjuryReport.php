<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WnbaInjuryReport extends Model
{
    protected $fillable = [
        'player_id',
        'player_external_id',
        'player_name',
        'team_id',
        'team_abbreviation',
        'status',
        'injury_type',
        'body_part',
        'description',
        'reported_at',
        'source_id',
        'report_hash',
        'raw_payload_id',
        'agent_run_id',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(WnbaPlayer::class, 'player_id');
    }
}
