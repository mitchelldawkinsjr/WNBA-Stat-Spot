<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WnbaPlay extends Model
{
    protected $fillable = [
        'game_id',
        'play_id',
        'play_sequence_number',
        'period',
        'period_display_value',
        'clock_display_value',
        'team_id',
        'player_id',
        'play_type_id',
        'play_type_text',
        'play_type_abbreviation',
        'play_text',
        'score_value',
        'score_team_id',
        'source_id',
        'raw_payload_id',
        'ingested_at',
        'validation_status',
    ];

    protected $casts = [
        'play_sequence_number' => 'integer',
        'score_value' => 'integer',
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

    public function player(): BelongsTo
    {
        return $this->belongsTo(WnbaPlayer::class, 'player_id');
    }

    public function scoreTeam(): BelongsTo
    {
        return $this->belongsTo(WnbaTeam::class, 'score_team_id');
    }
}
