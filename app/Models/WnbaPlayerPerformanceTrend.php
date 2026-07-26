<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WnbaPlayerPerformanceTrend extends Model
{
    protected $fillable = [
        'player_id',
        'season',
        'window',
        'games',
        'minutes_avg',
        'points_avg',
        'rebounds_avg',
        'assists_avg',
        'fg_pct',
        'ts_pct',
        'points_slope',
        'rebounds_slope',
        'assists_slope',
        'formula_version',
        'computed_at',
        'agent_run_id',
    ];

    protected $casts = [
        'season' => 'integer',
        'games' => 'integer',
        'minutes_avg' => 'float',
        'points_avg' => 'float',
        'rebounds_avg' => 'float',
        'assists_avg' => 'float',
        'fg_pct' => 'float',
        'ts_pct' => 'float',
        'points_slope' => 'float',
        'rebounds_slope' => 'float',
        'assists_slope' => 'float',
        'computed_at' => 'datetime',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(WnbaPlayer::class, 'player_id');
    }
}
