<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WnbaMatchupSummary extends Model
{
    protected $fillable = [
        'season',
        'team_a_id',
        'team_b_id',
        'games_played',
        'team_a_wins',
        'team_b_wins',
        'avg_total_points',
        'avg_margin',
        'avg_pace',
        'last_meeting_date',
        'recent_meetings',
        'formula_version',
        'computed_at',
        'agent_run_id',
    ];

    protected $casts = [
        'season' => 'integer',
        'games_played' => 'integer',
        'team_a_wins' => 'integer',
        'team_b_wins' => 'integer',
        'avg_total_points' => 'float',
        'avg_margin' => 'float',
        'avg_pace' => 'float',
        'last_meeting_date' => 'date',
        'recent_meetings' => 'array',
        'computed_at' => 'datetime',
    ];
}
