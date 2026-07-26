<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WnbaPlayerGame extends Model
{
    protected $fillable = [
        'game_id',
        'player_id',
        'team_id',
        'minutes',
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
        'plus_minus',
        'points',
        'starter',
        'ejected',
        'did_not_play',
        'reason',
        'active',
        'source_id',
        'raw_payload_id',
        'ingested_at',
        'validation_status',
    ];

    protected $casts = [
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
        'plus_minus' => 'integer',
        'points' => 'integer',
        'starter' => 'boolean',
        'ejected' => 'boolean',
        'did_not_play' => 'boolean',
        'active' => 'boolean',
        'ingested_at' => 'datetime',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(WnbaGame::class, 'game_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(WnbaPlayer::class, 'player_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(WnbaTeam::class, 'team_id');
    }
}
