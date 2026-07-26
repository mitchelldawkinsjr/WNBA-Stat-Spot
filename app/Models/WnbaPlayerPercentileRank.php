<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WnbaPlayerPercentileRank extends Model
{
    protected $fillable = [
        'player_id', 'season', 'sample_size', 'points_pctl', 'rebounds_pctl', 'assists_pctl',
        'steals_pctl', 'blocks_pctl', 'minutes_pctl', 'ts_pct_pctl', 'efg_pct_pctl',
        'formula_version', 'computed_at', 'agent_run_id',
    ];

    protected $casts = [
        'season' => 'integer',
        'sample_size' => 'integer',
        'points_pctl' => 'float',
        'rebounds_pctl' => 'float',
        'assists_pctl' => 'float',
        'steals_pctl' => 'float',
        'blocks_pctl' => 'float',
        'minutes_pctl' => 'float',
        'ts_pct_pctl' => 'float',
        'efg_pct_pctl' => 'float',
        'computed_at' => 'datetime',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(WnbaPlayer::class, 'player_id');
    }
}
