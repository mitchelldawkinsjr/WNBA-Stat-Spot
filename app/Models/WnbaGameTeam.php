<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WnbaGameTeam extends Model
{
    protected $fillable = [
        'game_id',
        'team_id',
        'opponent_team_id',
        'home_away',
        'team_winner',
        'team_score',
        'opponent_team_score',
        'field_goals_made',
        'field_goals_attempted',
        'three_point_field_goals_made',
        'three_point_field_goals_attempted',
        'free_throws_made',
        'free_throws_attempted',
        'offensive_rebounds',
        'defensive_rebounds',
        'rebounds',
        'assists',
        'steals',
        'blocks',
        'turnovers',
        'fouls',
        'source_id',
        'raw_payload_id',
        'ingested_at',
        'validation_status',
    ];

    protected $casts = [
        'team_winner' => 'boolean',
        'team_score' => 'integer',
        'opponent_team_score' => 'integer',
        'field_goals_made' => 'integer',
        'field_goals_attempted' => 'integer',
        'three_point_field_goals_made' => 'integer',
        'three_point_field_goals_attempted' => 'integer',
        'free_throws_made' => 'integer',
        'free_throws_attempted' => 'integer',
        'offensive_rebounds' => 'integer',
        'defensive_rebounds' => 'integer',
        'rebounds' => 'integer',
        'assists' => 'integer',
        'steals' => 'integer',
        'blocks' => 'integer',
        'turnovers' => 'integer',
        'fouls' => 'integer',
        'ingested_at' => 'datetime',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(WnbaGame::class, 'game_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(WnbaTeam::class, 'team_id');
    }

    public function opponentTeam(): BelongsTo
    {
        return $this->belongsTo(WnbaTeam::class, 'opponent_team_id');
    }
}
