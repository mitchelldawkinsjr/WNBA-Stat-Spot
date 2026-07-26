<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WnbaTeamPerformanceTrend extends Model
{
    protected $fillable = [
        'team_id',
        'season',
        'window',
        'games',
        'wins',
        'losses',
        'points_for_avg',
        'points_against_avg',
        'pace_avg',
        'offensive_rating',
        'defensive_rating',
        'formula_version',
        'computed_at',
        'agent_run_id',
    ];

    protected $casts = [
        'season' => 'integer',
        'games' => 'integer',
        'wins' => 'integer',
        'losses' => 'integer',
        'points_for_avg' => 'float',
        'points_against_avg' => 'float',
        'pace_avg' => 'float',
        'offensive_rating' => 'float',
        'defensive_rating' => 'float',
        'computed_at' => 'datetime',
    ];
}
