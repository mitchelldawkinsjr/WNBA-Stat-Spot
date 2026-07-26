<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WnbaTeamPercentileRank extends Model
{
    protected $fillable = [
        'team_id', 'season', 'sample_size', 'offensive_rating_pctl', 'defensive_rating_pctl',
        'net_rating_pctl', 'pace_pctl', 'efg_pct_pctl', 'tov_pct_pctl',
        'formula_version', 'computed_at', 'agent_run_id',
    ];

    protected $casts = [
        'season' => 'integer',
        'sample_size' => 'integer',
        'offensive_rating_pctl' => 'float',
        'defensive_rating_pctl' => 'float',
        'net_rating_pctl' => 'float',
        'pace_pctl' => 'float',
        'efg_pct_pctl' => 'float',
        'tov_pct_pctl' => 'float',
        'computed_at' => 'datetime',
    ];
}
