<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WnbaPlayerVsDefense extends Model
{
    protected $table = 'wnba_player_vs_defense';

    protected $fillable = [
        'player_id',
        'season',
        'defense_bucket',
        'games',
        'minutes_avg',
        'points_avg',
        'rebounds_avg',
        'assists_avg',
        'fg_pct',
        'three_pct',
        'ts_pct',
        'usage_pct_avg',
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
        'three_pct' => 'float',
        'ts_pct' => 'float',
        'usage_pct_avg' => 'float',
        'computed_at' => 'datetime',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(WnbaPlayer::class, 'player_id');
    }
}
