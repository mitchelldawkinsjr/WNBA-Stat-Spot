<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WnbaTeamSeasonStat extends Model
{
    protected $fillable = [
        'team_id',
        'season',
        'games_played',
        'wins',
        'losses',
        'points_for_avg',
        'points_against_avg',
        'pace',
        'possessions_per_game',
        'offensive_rating',
        'defensive_rating',
        'net_rating',
        'efg_pct',
        'tov_pct',
        'oreb_pct',
        'ft_rate',
        'opp_efg_pct',
        'opp_tov_pct',
        'dreb_pct',
        'opp_ft_rate',
        'ts_pct',
        'three_rate',
        'splits',
        'formula_version',
        'computed_at',
        'agent_run_id',
    ];

    protected $casts = [
        'season' => 'integer',
        'games_played' => 'integer',
        'wins' => 'integer',
        'losses' => 'integer',
        'points_for_avg' => 'float',
        'points_against_avg' => 'float',
        'pace' => 'float',
        'possessions_per_game' => 'float',
        'offensive_rating' => 'float',
        'defensive_rating' => 'float',
        'net_rating' => 'float',
        'efg_pct' => 'float',
        'tov_pct' => 'float',
        'oreb_pct' => 'float',
        'ft_rate' => 'float',
        'opp_efg_pct' => 'float',
        'opp_tov_pct' => 'float',
        'dreb_pct' => 'float',
        'opp_ft_rate' => 'float',
        'ts_pct' => 'float',
        'three_rate' => 'float',
        'splits' => 'array',
        'computed_at' => 'datetime',
    ];
}
