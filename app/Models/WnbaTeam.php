<?php

namespace App\Models;

use App\Services\WNBA\Data\Support\TeamCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WnbaTeam extends Model
{
    protected $fillable = [
        'team_id',
        'espn_team_id',
        'tank01_team_id',
        'team_name',
        'team_location',
        'team_abbreviation',
        'team_display_name',
        'team_uid',
        'team_slug',
        'team_logo',
        'team_color',
        'team_alternate_color',
    ];

    public function scopeLeague(Builder $query): Builder
    {
        return $query->whereNotIn('team_id', TeamCatalog::excludedTeamIds());
    }

    public function gameTeams(): HasMany
    {
        return $this->hasMany(WnbaGameTeam::class, 'team_id');
    }

    public function opponentGameTeams(): HasMany
    {
        return $this->hasMany(WnbaGameTeam::class, 'opponent_team_id');
    }

    public function playerGames(): HasMany
    {
        return $this->hasMany(WnbaPlayerGame::class, 'team_id');
    }

    public function plays(): HasMany
    {
        return $this->hasMany(WnbaPlay::class, 'team_id');
    }

    public function scorePlays(): HasMany
    {
        return $this->hasMany(WnbaPlay::class, 'score_team_id');
    }
}
