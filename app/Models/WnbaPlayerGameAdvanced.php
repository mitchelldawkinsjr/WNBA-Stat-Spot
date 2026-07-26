<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WnbaPlayerGameAdvanced extends Model
{
    protected $table = 'wnba_player_game_advanced';

    protected $fillable = [
        'player_game_id',
        'game_id',
        'player_id',
        'minutes_decimal',
        'ts_pct',
        'efg_pct',
        'usage_pct',
        'game_score',
        'points_per_shot',
        'formula_version',
        'computed_at',
        'agent_run_id',
    ];

    protected $casts = [
        'minutes_decimal' => 'float',
        'ts_pct' => 'float',
        'efg_pct' => 'float',
        'usage_pct' => 'float',
        'game_score' => 'float',
        'points_per_shot' => 'float',
        'computed_at' => 'datetime',
    ];

    public function playerGame(): BelongsTo
    {
        return $this->belongsTo(WnbaPlayerGame::class, 'player_game_id');
    }
}
