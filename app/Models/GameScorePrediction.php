<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GameScorePrediction extends Model
{
    protected $fillable = [
        'game_id',
        'season',
        'game_date',
        'home_team_abbr',
        'away_team_abbr',
        'predicted_winner',
        'predicted_home_score',
        'predicted_away_score',
        'predicted_total',
        'predicted_spread',
        'win_probability_home',
        'win_probability_away',
        'confidence',
        'predicted_at',
        'actual_home_score',
        'actual_away_score',
        'actual_winner',
        'winner_correct',
        'home_score_error',
        'away_score_error',
        'total_error',
        'total_within_5',
        'spread_direction_correct',
        'graded_at',
    ];

    protected $casts = [
        'game_date' => 'date',
        'predicted_home_score' => 'float',
        'predicted_away_score' => 'float',
        'predicted_total' => 'float',
        'predicted_spread' => 'float',
        'win_probability_home' => 'float',
        'win_probability_away' => 'float',
        'confidence' => 'float',
        'predicted_at' => 'datetime',
        'actual_home_score' => 'integer',
        'actual_away_score' => 'integer',
        'winner_correct' => 'boolean',
        'home_score_error' => 'float',
        'away_score_error' => 'float',
        'total_error' => 'float',
        'total_within_5' => 'boolean',
        'spread_direction_correct' => 'boolean',
        'graded_at' => 'datetime',
    ];

    public function scopeUngraded(Builder $query): Builder
    {
        return $query->whereNull('graded_at');
    }

    public function scopeGraded(Builder $query): Builder
    {
        return $query->whereNotNull('graded_at');
    }
}
