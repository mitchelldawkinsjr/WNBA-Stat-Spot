<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WnbaOddsSnapshot extends Model
{
    protected $fillable = [
        'source_id',
        'event_id',
        'game_date',
        'market_type',
        'stat_type',
        'player_name',
        'bookmaker',
        'line',
        'over_odds',
        'under_odds',
        'payload',
        'snapshot_hash',
        'captured_at',
        'agent_run_id',
    ];

    protected $casts = [
        'game_date' => 'date',
        'line' => 'float',
        'over_odds' => 'integer',
        'under_odds' => 'integer',
        'payload' => 'array',
        'captured_at' => 'datetime',
    ];
}
