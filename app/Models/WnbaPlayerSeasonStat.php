<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WnbaPlayerSeasonStat extends Model
{
    protected $fillable = [
        'player_id',
        'season',
        'team_id',
        'games_played',
        'games_started',
        'minutes_total',
        'points_total',
        'rebounds_total',
        'offensive_rebounds_total',
        'defensive_rebounds_total',
        'assists_total',
        'steals_total',
        'blocks_total',
        'turnovers_total',
        'fouls_total',
        'field_goals_made_total',
        'field_goals_attempted_total',
        'three_point_made_total',
        'three_point_attempted_total',
        'free_throws_made_total',
        'free_throws_attempted_total',
        'points_avg',
        'rebounds_avg',
        'assists_avg',
        'steals_avg',
        'blocks_avg',
        'turnovers_avg',
        'minutes_avg',
        'fg_pct',
        'three_pct',
        'ft_pct',
        'efg_pct',
        'ts_pct',
        'splits',
        'formula_version',
        'computed_at',
        'agent_run_id',
    ];

    protected $casts = [
        'season' => 'integer',
        'games_played' => 'integer',
        'games_started' => 'integer',
        'minutes_total' => 'float',
        'points_avg' => 'float',
        'rebounds_avg' => 'float',
        'assists_avg' => 'float',
        'steals_avg' => 'float',
        'blocks_avg' => 'float',
        'turnovers_avg' => 'float',
        'minutes_avg' => 'float',
        'fg_pct' => 'float',
        'three_pct' => 'float',
        'ft_pct' => 'float',
        'efg_pct' => 'float',
        'ts_pct' => 'float',
        'splits' => 'array',
        'computed_at' => 'datetime',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(WnbaPlayer::class, 'player_id');
    }
}
