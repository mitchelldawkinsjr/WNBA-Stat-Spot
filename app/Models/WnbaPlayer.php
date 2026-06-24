<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WnbaPlayer extends Model
{
    protected $fillable = [
        'athlete_id',
        'espn_athlete_id',
        'tank01_player_id',
        'athlete_display_name',
        'athlete_short_name',
        'athlete_jersey',
        'athlete_headshot_href',
        'athlete_position_name',
        'athlete_position_abbreviation',
    ];

    public function playerGames(): HasMany
    {
        return $this->hasMany(WnbaPlayerGame::class, 'player_id');
    }

    public static function findByExternalId(string $id): ?self
    {
        return static::query()
            ->where('athlete_id', $id)
            ->orWhere('espn_athlete_id', $id)
            ->orWhere('tank01_player_id', $id)
            ->first();
    }

    public function plays(): HasMany
    {
        return $this->hasMany(WnbaPlay::class, 'player_id');
    }
}
