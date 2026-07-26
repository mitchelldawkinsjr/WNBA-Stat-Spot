<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WnbaTeamPowerRanking extends Model
{
    protected $fillable = [
        'season', 'as_of_date', 'team_id', 'rank', 'previous_rank', 'rank_delta',
        'score', 'components', 'reason', 'formula_version', 'computed_at', 'agent_run_id',
    ];

    protected $casts = [
        'season' => 'integer',
        'as_of_date' => 'date',
        'rank' => 'integer',
        'previous_rank' => 'integer',
        'rank_delta' => 'integer',
        'score' => 'float',
        'components' => 'array',
        'computed_at' => 'datetime',
    ];
}
