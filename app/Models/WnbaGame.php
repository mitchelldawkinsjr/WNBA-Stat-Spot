<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WnbaGame extends Model
{
    protected $fillable = [
        'game_id',
        'espn_game_id',
        'tank01_game_id',
        'season',
        'season_type',
        'game_date',
        'game_date_time',
        'venue_id',
        'venue_name',
        'venue_city',
        'venue_state',
        'venue_country',
        'venue_capacity',
        'venue_surface',
        'venue_indoor',
        'status_id',
        'status_name',
        'status_type',
        'status_abbreviation',
        'sportsblaze_game_id',
        'source_id',
        'raw_payload_id',
        'ingested_at',
        'validation_status',
    ];

    protected $casts = [
        'game_date' => 'date',
        'game_date_time' => 'datetime',
        'venue_capacity' => 'integer',
        'venue_indoor' => 'boolean',
        'ingested_at' => 'datetime',
    ];

    public function gameTeams(): HasMany
    {
        return $this->hasMany(WnbaGameTeam::class, 'game_id');
    }

    public function playerGames(): HasMany
    {
        return $this->hasMany(WnbaPlayerGame::class, 'game_id');
    }

    public function plays(): HasMany
    {
        return $this->hasMany(WnbaPlay::class, 'game_id');
    }
}
